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
            ->when($request->string('plan_id')->toString(), fn ($q, string $planId) => $q->where('plan_id', $planId))
            ->when($request->string('search')->toString(), function ($q, string $search) {
                $q->whereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            })
            ->latest('id')
            ->paginate(min(50, max(10, $request->integer('per_page', 10))))
            ->withQueryString();

        return view('super-admin.subscriptions.index', [
            'subscriptions' => $subscriptions,
            'perPage' => min(50, max(10, $request->integer('per_page', 10))),
            'statuses' => ['active', 'pending', 'past_due', 'cancelled', 'paused', 'trialing'],
            'plans' => \App\Models\Plan::orderBy('name')->get(['id', 'name']),
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
