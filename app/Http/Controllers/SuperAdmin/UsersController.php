<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UsersController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()
            ->with(['role', 'domains'])
            ->when($request->string('search')->toString(), function ($query, string $search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->string('status')->toString(), fn ($query, string $status) => $query->where('status', $status))
            ->when($request->string('plan')->toString(), function ($query, string $planSlug): void {
                $query->whereHas('subscriptions', function ($q) use ($planSlug): void {
                    $q->whereIn('status', ['active', 'trialing'])
                        ->whereHas('plan', fn ($pq) => $pq->where('slug', $planSlug));
                });
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        // Attach current plan info per user for the table.
        $userIds = $users->pluck('id')->all();
        $subscriptions = Subscription::query()
            ->whereIn('user_id', $userIds)
            ->whereIn('status', ['active', 'trialing'])
            ->with('plan:id,name,slug,tier')
            ->orderByDesc('id')
            ->get()
            ->keyBy('user_id');

        $users->getCollection()->transform(function (User $user) use ($subscriptions) {
            $sub = $subscriptions->get($user->id);
            $user->current_plan_name = $sub?->plan?->name;
            $user->current_plan_tier = $sub?->plan?->tier;
            $user->current_plan_slug = $sub?->plan?->slug;
            $user->subscription_status = $sub?->status;
            $user->is_trialing = (bool) ($sub?->is_trial && $sub->trial_ends_at && $sub->trial_ends_at->isFuture());

            return $user;
        });

        return view('super-admin.users.index', [
            'users' => $users,
            'roles' => Role::orderBy('name')->get(),
            'statuses' => ['active', 'suspended', 'pending', 'banned'],
            'plans' => \App\Models\Plan::where('is_active', true)->orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'status' => ['required', 'in:active,suspended,pending,banned'],
            'role_id' => ['nullable', 'exists:roles,id'],
            'is_admin' => ['nullable', 'boolean'],
            'is_super_admin' => ['nullable', 'boolean'],
        ]);

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'status' => $data['status'],
            'role_id' => $data['role_id'] ?? null,
            'is_admin' => (bool) ($data['is_admin'] ?? false),
            'is_super_admin' => (bool) ($data['is_super_admin'] ?? false),
        ]);

        return back()->with('status', 'User updated.');
    }

    public function status(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:active,suspended,pending,banned']]);
        $user->update(['status' => $data['status']]);

        return back()->with('status', 'User status updated.');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $newPassword = Str::random(14);
        $user->update(['password' => Hash::make($newPassword)]);

        return back()->with('status', "Password reset for {$user->email}. Temporary password: {$newPassword}");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => 'You cannot remove yourself.']);
        }
        $user->delete();

        return back()->with('status', 'User removed.');
    }

    public function impersonate(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => 'You are already this user.']);
        }
        if ($user->is_super_admin) {
            return back()->withErrors(['user' => 'You cannot impersonate another super admin.']);
        }

        $request->session()->put('impersonator_id', $request->user()->id);
        Auth::login($user);

        return redirect()->route('dashboard')->with('status', "Now signed in as {$user->email}.");
    }

    public function stopImpersonating(Request $request): RedirectResponse
    {
        $originalId = $request->session()->pull('impersonator_id');
        if (! $originalId) {
            return redirect()->route('dashboard');
        }
        $original = User::query()->find($originalId);
        if (! $original) {
            return redirect()->route('dashboard');
        }
        Auth::login($original);

        return redirect()->route('super-admin.users.index')->with('status', 'Stopped impersonating.');
    }
}
