<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Support\StatusTone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubscriptionsController extends Controller
{
    public function index(Request $request): View
    {
        $sort = (string) $request->query('sort', 'id');
        $dir = strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSorts = [
            'id' => 'subscriptions.id',
            'status' => 'subscriptions.status',
            'billing_interval' => 'subscriptions.billing_interval',
            'next_payment' => 'subscriptions.ends_at',
            'user' => 'users.name',
            'plan' => 'plans.name',
        ];
        $sortColumn = $allowedSorts[$sort] ?? 'subscriptions.id';

        $subscriptions = Subscription::query()
            ->with(['user', 'plan'])
            ->leftJoin('users', 'users.id', '=', 'subscriptions.user_id')
            ->leftJoin('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->select('subscriptions.*')
            ->when($request->string('status')->toString(), fn ($q, string $status) => $q->where('subscriptions.status', $status))
            ->when($request->string('plan_id')->toString(), fn ($q, string $planId) => $q->where('subscriptions.plan_id', $planId))
            ->when($request->string('search')->toString(), function ($q, string $search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('users.name', 'like', "%{$search}%")
                        ->orWhere('users.email', 'like', "%{$search}%");
                });
            })
            ->orderBy($sortColumn, $dir)
            ->paginate(min(50, max(10, $request->integer('per_page', 10))))
            ->withQueryString();

        return view('super-admin.subscriptions.index', [
            'subscriptions' => $subscriptions,
            'perPage' => min(50, max(10, $request->integer('per_page', 10))),
            'statuses' => ['active', 'pending', 'past_due', 'cancelled', 'paused', 'trialing'],
            'filterStatuses' => StatusTone::subscriptionFilters(),
            'plans' => \App\Models\Plan::orderBy('name')->get(['id', 'name']),
            'sort' => array_key_exists($sort, $allowedSorts) ? $sort : 'id',
            'dir' => $dir,
        ]);
    }

    public function update(Request $request, Subscription $subscription): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:active,pending,past_due,cancelled,paused,trialing']]);
        $subscription->update([
            'status' => $data['status'],
            'cancelled_at' => $data['status'] === 'cancelled' ? now() : $subscription->cancelled_at,
        ]);

        return back()->with('status', 'Subscription updated.');
    }
}
