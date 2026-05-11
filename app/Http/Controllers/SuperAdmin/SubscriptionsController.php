<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubscriptionsController extends Controller
{
    public function index(Request $request): View
    {
        $subscriptions = Subscription::with(['user', 'plan'])
            ->when($request->string('status')->toString(), fn ($q, string $status) => $q->where('status', $status))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('super-admin.subscriptions.index', [
            'subscriptions' => $subscriptions,
            'statuses' => ['active', 'pending', 'past_due', 'cancelled', 'paused', 'trialing'],
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
