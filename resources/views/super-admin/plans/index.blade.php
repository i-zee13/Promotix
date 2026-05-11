@extends('layouts.super-admin')

@section('title', 'Plans & Pricing')
@section('subtitle', 'Inline plan editing, limits, feature flags, and trial eligibility')

@section('content')
    <div class="space-y-6">
        <x-ui.card>
            <h2 class="text-base font-semibold text-night-100">Create Plan</h2>
            <form method="POST" action="{{ route('super-admin.plans.store') }}" class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-4">
                @csrf
                <div>
                    <label class="brand-label">Product</label>
                    <select name="saas_product_id" class="brand-select mt-1">
                        <option value="">No product</option>
                        @foreach ($products as $product)<option value="{{ $product->id }}">{{ $product->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="brand-label">Plan name</label>
                    <input name="name" required placeholder="Basic / Pro / Premium" class="brand-input mt-1">
                </div>
                <div>
                    <label class="brand-label">Tier</label>
                    <select name="tier" class="brand-select mt-1">
                        @foreach (['basic','pro','premium','enterprise','custom'] as $tier)<option value="{{ $tier }}">{{ ucfirst($tier) }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="brand-label">Price</label>
                    <input name="price" type="number" step="0.01" min="0" placeholder="9.99" class="brand-input mt-1">
                </div>
                <div>
                    <label class="brand-label">Currency</label>
                    <input name="currency" maxlength="3" value="USD" class="brand-input mt-1">
                </div>
                <div>
                    <label class="brand-label">Billing</label>
                    <select name="billing_interval" class="brand-select mt-1">
                        <option value="monthly">Monthly</option>
                        <option value="yearly">Yearly</option>
                        <option value="custom">Custom</option>
                    </select>
                </div>
                <div>
                    <label class="brand-label">Trial days</label>
                    <input name="trial_days" type="number" min="0" placeholder="0" class="brand-input mt-1">
                </div>
                <label class="inline-flex items-center gap-2 self-end pb-1 cursor-pointer">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" checked class="brand-checkbox">
                    <span class="text-sm text-night-200">Active</span>
                </label>
                <div class="lg:col-span-4">
                    <label class="brand-label">Feature limits</label>
                    <textarea name="features" rows="3" class="brand-input mt-1" placeholder="One per line. Example: domains: 10"></textarea>
                </div>
                <button class="brand-btn-primary lg:col-span-4">Create Plan</button>
            </form>
        </x-ui.card>

        @foreach ($plans as $plan)
            <form id="plan-form-{{ $plan->id }}" method="POST" action="{{ route('super-admin.plans.update', $plan) }}" class="hidden">
                @csrf
                @method('PUT')
            </form>
            <form id="plan-archive-{{ $plan->id }}" method="POST" action="{{ route('super-admin.plans.destroy', $plan) }}" class="hidden" onsubmit="return confirm('Archive this plan?')">
                @csrf
                @method('DELETE')
            </form>
        @endforeach

        <x-ui.card class="!p-0 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="brand-table min-w-[1100px]">
                    <thead>
                        <tr>
                            <th>Plan</th>
                            <th>Product</th>
                            <th>Price</th>
                            <th>Limits</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($plans as $plan)
                            @php $fid = 'plan-form-'.$plan->id; $aid = 'plan-archive-'.$plan->id; @endphp
                            <tr>
                                <td class="align-top">
                                    <div class="space-y-2">
                                        <input form="{{ $fid }}" name="name" value="{{ $plan->name }}" class="brand-input">
                                        <select form="{{ $fid }}" name="tier" class="brand-select">
                                            @foreach (['basic','pro','premium','enterprise','custom'] as $tier)
                                                <option value="{{ $tier }}" @selected($plan->tier === $tier)>{{ ucfirst($tier) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </td>
                                <td class="align-top">
                                    <select form="{{ $fid }}" name="saas_product_id" class="brand-select">
                                        <option value="">No product</option>
                                        @foreach ($products as $product)
                                            <option value="{{ $product->id }}" @selected($plan->saas_product_id === $product->id)>{{ $product->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="align-top">
                                    <div class="space-y-2">
                                        <input form="{{ $fid }}" name="price" type="number" step="0.01" value="{{ $plan->price_cents / 100 }}" class="brand-input w-28">
                                        <input form="{{ $fid }}" name="currency" value="{{ $plan->currency }}" class="brand-input w-20">
                                        <select form="{{ $fid }}" name="billing_interval" class="brand-select">
                                            @foreach (['monthly','yearly','custom'] as $interval)
                                                <option value="{{ $interval }}" @selected($plan->billing_interval === $interval)>{{ ucfirst($interval) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </td>
                                <td class="align-top">
                                    <div class="space-y-2">
                                        <input form="{{ $fid }}" name="trial_days" type="number" value="{{ $plan->trial_days }}" class="brand-input">
                                        <textarea form="{{ $fid }}" name="features" rows="3" class="brand-input">{{ collect($plan->feature_limits ?? [])->map(fn($v, $k) => $k.': '.$v)->implode("\n") }}</textarea>
                                    </div>
                                </td>
                                <td class="align-top">
                                    <input form="{{ $fid }}" type="hidden" name="is_active" value="0">
                                    <input form="{{ $fid }}" type="hidden" name="is_custom" value="0">
                                    <div class="space-y-2">
                                        <label class="inline-flex items-center gap-2 cursor-pointer">
                                            <input form="{{ $fid }}" type="checkbox" name="is_active" value="1" @checked($plan->is_active) class="brand-checkbox">
                                            <span class="text-sm text-night-200">Active</span>
                                        </label>
                                        <label class="inline-flex items-center gap-2 cursor-pointer">
                                            <input form="{{ $fid }}" type="checkbox" name="is_custom" value="1" @checked($plan->is_custom) class="brand-checkbox">
                                            <span class="text-sm text-night-200">Custom</span>
                                        </label>
                                    </div>
                                </td>
                                <td class="align-top">
                                    <div class="flex flex-col gap-2">
                                        <button form="{{ $fid }}" type="submit" class="brand-btn-primary !px-3 !py-2 text-xs">Save</button>
                                        <button form="{{ $aid }}" type="submit" class="brand-btn-danger !px-3 !py-2 text-xs">Archive</button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-12 text-center text-night-300">No plans yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-night-700/60 px-4 py-3">{{ $plans->links() }}</div>
        </x-ui.card>
    </div>
@endsection
