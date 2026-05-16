@extends('layouts.super-admin')

@section('title', 'Plans & Pricing')
@section('content')
<x-super-admin.page title="Plans & Pricing" subtitle="Inline plan editing, limits, feature flags, and trial eligibility">
    <div class="space-y-6">
        <x-super-admin.card>
            <h2 class="text-base font-semibold text-white">Create Plan</h2>
            <form method="POST" action="{{ route('super-admin.plans.store') }}" class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-4">
                @csrf
                <div>
                    <label class="figma-sa-label">Product</label>
                    <select name="saas_product_id" class="figma-select mt-1">
                        <option value="">No product</option>
                        @foreach ($products as $product)<option value="{{ $product->id }}">{{ $product->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="figma-sa-label">Plan name</label>
                    <input name="name" required placeholder="Basic / Pro / Premium" class="figma-input mt-1">
                </div>
                <div>
                    <label class="figma-sa-label">Tier</label>
                    <select name="tier" class="figma-select mt-1">
                        @foreach (['basic','pro','premium','enterprise','custom'] as $tier)<option value="{{ $tier }}">{{ ucfirst($tier) }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="figma-sa-label">Price</label>
                    <input name="price" type="number" step="0.01" min="0" placeholder="9.99" class="figma-input mt-1">
                </div>
                <div>
                    <label class="figma-sa-label">Currency</label>
                    <input name="currency" maxlength="3" value="USD" class="figma-input mt-1">
                </div>
                <div>
                    <label class="figma-sa-label">Billing</label>
                    <select name="billing_interval" class="figma-select mt-1">
                        <option value="monthly">Monthly</option>
                        <option value="yearly">Yearly</option>
                        <option value="custom">Custom</option>
                    </select>
                </div>
                <div>
                    <label class="figma-sa-label">Trial days</label>
                    <input name="trial_days" type="number" min="0" placeholder="0" class="figma-input mt-1">
                </div>
                <div>
                    <label class="figma-sa-label">Sort order</label>
                    <input name="sort_order" type="number" min="0" value="0" class="figma-input mt-1">
                </div>
                <div>
                    <label class="figma-sa-label">Yearly price (total)</label>
                    <input name="price_yearly" type="number" step="0.01" min="0" placeholder="optional" class="figma-input mt-1">
                </div>
                <div>
                    <label class="figma-sa-label">CTA label</label>
                    <input name="cta_label" maxlength="80" placeholder="Start free trial" class="figma-input mt-1">
                </div>
                <label class="inline-flex items-center gap-2 self-end pb-1 cursor-pointer">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" checked class="figma-sa-checkbox rounded">
                    <span class="text-sm text-[#d9d9d9]">Active</span>
                </label>
                <label class="inline-flex items-center gap-2 self-end pb-1 cursor-pointer">
                    <input type="hidden" name="is_highlighted" value="0">
                    <input type="checkbox" name="is_highlighted" value="1" class="figma-sa-checkbox rounded">
                    <span class="text-sm text-[#d9d9d9]">Highlight</span>
                </label>
                <div class="lg:col-span-4">
                    <label class="figma-sa-label">Short description</label>
                    <textarea name="short_description" rows="2" class="figma-input mt-1" placeholder="Shown on pricing & onboarding cards"></textarea>
                </div>
                <div class="lg:col-span-4">
                    <label class="figma-sa-label">Feature limits</label>
                    <textarea name="features" rows="3" class="figma-input mt-1" placeholder="One per line. Example: domains: 10"></textarea>
                </div>
                <div class="lg:col-span-4">
                    <label class="figma-sa-label">Feature flags</label>
                    <textarea name="feature_flags" rows="3" class="figma-input mt-1" placeholder="One per line. Example: ad_protection: 1"></textarea>
                </div>
                <button class="figma-sa-btn figma-sa-btn-primary lg:col-span-4">Create Plan</button>
            </form>
        </x-super-admin.card>

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

        <x-super-admin.card class="!p-0 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="figma-sa-table min-w-[1400px]">
                    <thead>
                        <tr>
                            <th>Plan</th>
                            <th>Product</th>
                            <th>Pricing</th>
                            <th>Display</th>
                            <th>Limits &amp; flags</th>
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
                                        <input form="{{ $fid }}" name="name" value="{{ $plan->name }}" class="figma-input">
                                        <select form="{{ $fid }}" name="tier" class="figma-select">
                                            @foreach (['basic','pro','premium','enterprise','custom'] as $tier)
                                                <option value="{{ $tier }}" @selected($plan->tier === $tier)>{{ ucfirst($tier) }}</option>
                                            @endforeach
                                        </select>
                                        <textarea form="{{ $fid }}" name="short_description" rows="2" class="figma-input text-xs" placeholder="Card description">{{ $plan->short_description }}</textarea>
                                    </div>
                                </td>
                                <td class="align-top">
                                    <select form="{{ $fid }}" name="saas_product_id" class="figma-select">
                                        <option value="">No product</option>
                                        @foreach ($products as $product)
                                            <option value="{{ $product->id }}" @selected($plan->saas_product_id === $product->id)>{{ $product->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="align-top">
                                    <div class="space-y-2">
                                        <input form="{{ $fid }}" name="price" type="number" step="0.01" value="{{ $plan->price_cents / 100 }}" class="figma-input w-28" title="Monthly">
                                        <input form="{{ $fid }}" name="price_yearly" type="number" step="0.01" value="{{ $plan->price_yearly_cents ? $plan->price_yearly_cents / 100 : '' }}" class="figma-input w-28" placeholder="Year total">
                                        <input form="{{ $fid }}" name="currency" value="{{ $plan->currency }}" class="figma-input w-20">
                                        <select form="{{ $fid }}" name="billing_interval" class="figma-select">
                                            @foreach (['monthly','yearly','custom'] as $interval)
                                                <option value="{{ $interval }}" @selected($plan->billing_interval === $interval)>{{ ucfirst($interval) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </td>
                                <td class="align-top">
                                    <div class="space-y-2">
                                        <input form="{{ $fid }}" name="sort_order" type="number" min="0" value="{{ (int) ($plan->sort_order ?? 0) }}" class="figma-input w-24">
                                        <input form="{{ $fid }}" name="cta_label" value="{{ $plan->cta_label }}" class="figma-input text-xs" placeholder="CTA">
                                        <input form="{{ $fid }}" type="hidden" name="is_highlighted" value="0">
                                        <label class="inline-flex items-center gap-2 cursor-pointer">
                                            <input form="{{ $fid }}" type="checkbox" name="is_highlighted" value="1" @checked($plan->is_highlighted) class="figma-sa-checkbox rounded">
                                            <span class="text-xs text-[#d9d9d9]">Highlight card</span>
                                        </label>
                                    </div>
                                </td>
                                <td class="align-top">
                                    <div class="space-y-2">
                                        <input form="{{ $fid }}" name="trial_days" type="number" value="{{ $plan->trial_days }}" class="figma-input">
                                        <textarea form="{{ $fid }}" name="features" rows="3" class="figma-input">{{ collect($plan->feature_limits ?? [])->map(fn($v, $k) => $k.': '.$v)->implode("\n") }}</textarea>
                                        <textarea form="{{ $fid }}" name="feature_flags" rows="3" class="figma-input text-xs" placeholder="ad_protection: 1">{{ collect($plan->feature_flags ?? [])->map(fn($v, $k) => $k.': '.($v ? '1' : '0'))->implode("\n") }}</textarea>
                                    </div>
                                </td>
                                <td class="align-top">
                                    <input form="{{ $fid }}" type="hidden" name="is_active" value="0">
                                    <input form="{{ $fid }}" type="hidden" name="is_custom" value="0">
                                    <div class="space-y-2">
                                        <label class="inline-flex items-center gap-2 cursor-pointer">
                                            <input form="{{ $fid }}" type="checkbox" name="is_active" value="1" @checked($plan->is_active) class="figma-sa-checkbox rounded">
                                            <span class="text-sm text-[#d9d9d9]">Active</span>
                                        </label>
                                        <label class="inline-flex items-center gap-2 cursor-pointer">
                                            <input form="{{ $fid }}" type="checkbox" name="is_custom" value="1" @checked($plan->is_custom) class="figma-sa-checkbox rounded">
                                            <span class="text-sm text-[#d9d9d9]">Custom</span>
                                        </label>
                                    </div>
                                </td>
                                <td class="align-top">
                                    <div class="flex flex-col gap-2">
                                        <button form="{{ $fid }}" type="submit" class="figma-sa-btn figma-sa-btn-primary !px-3 !py-2 text-xs">Save</button>
                                        <button form="{{ $aid }}" type="submit" class="figma-sa-btn figma-sa-btn-danger !px-3 !py-2 text-xs">Archive</button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-12 text-center text-[#a9a9a9]">No plans yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="figma-sa-pagination px-4 py-3">{{ $plans->links() }}</div>
        </x-super-admin.card>
    </div>
</x-super-admin.page>
@endsection
