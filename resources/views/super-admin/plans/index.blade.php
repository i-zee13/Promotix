@extends('layouts.super-admin')

@section('title', 'Plans & Pricing')

@section('content')
@php
    $productNames = $products->pluck('name', 'id');
    $defaultProduct = $products->first();
@endphp

<x-super-admin.page title="Plans & Pricing">
    <div
        class="figma-sa-plans"
        x-data="plansPricingPage(@js($plans->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'tier' => $p->tier,
            'product_id' => $p->saas_product_id,
            'product_name' => $p->product?->name,
            'billing_interval' => $p->billing_interval,
            'is_active' => (bool) $p->is_active,
            'is_custom' => (bool) $p->is_custom,
            'is_highlighted' => (bool) $p->is_highlighted,
            'price_label' => $p->is_custom
                ? ($p->tier === 'custom' ? 'features' : 'Contact us')
                : '$'.number_format($p->price_cents / 100, 0).' / '.($p->billing_interval === 'yearly' ? 'yr' : 'mo').'.',
            'status_label' => $p->is_custom && $p->trial_days
                ? 'Custom Trial'
                : ($p->is_active ? 'Active' : 'Inactive'),
            'short_description' => $p->short_description,
            'description_lines' => array_values(array_filter(preg_split('/\r\n|\r|\n/', (string) ($p->short_description ?? '')))),
            'feature_lines' => collect($p->feature_limits ?? [])->take(8)->map(fn ($v, $k) => is_string($k) ? ucwords(str_replace('_', ' ', $k)).': '.$v : $v)->values()->all(),
        ])))"
    >
        <div class="figma-sa-plans-toolbar">
            <label class="figma-sa-plans-search">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="search" x-model="search" placeholder="Select plans" autocomplete="off" aria-label="Search plans">
            </label>

            <x-super-admin.dashboard-dropdown align="left">
                <x-slot:trigger>
                    <button type="button" class="figma-sa-plans-filter-chip">
                        <span x-text="planTypeLabel"></span>
                        <span class="figma-sa-plans-chip-chevron">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </span>
                    </button>
                </x-slot:trigger>
                <button type="button" class="figma-sa-users-filter-option" @click="planType = ''">All Plan Types</button>
                @foreach (['basic','pro','premium','enterprise','custom'] as $tier)
                    <button type="button" class="figma-sa-users-filter-option" @click="planType = '{{ $tier }}'">{{ ucfirst($tier) }}</button>
                @endforeach
            </x-super-admin.dashboard-dropdown>

            <x-super-admin.dashboard-dropdown align="left">
                <x-slot:trigger>
                    <button type="button" class="figma-sa-plans-filter-chip">
                        <span x-text="billingCycleLabel"></span>
                        <span class="figma-sa-plans-chip-chevron">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </span>
                    </button>
                </x-slot:trigger>
                <button type="button" class="figma-sa-users-filter-option" @click="billingCycle = ''">Billing Cycle</button>
                <button type="button" class="figma-sa-users-filter-option" @click="billingCycle = 'monthly'">Monthly</button>
                <button type="button" class="figma-sa-users-filter-option" @click="billingCycle = 'yearly'">Yearly</button>
                <button type="button" class="figma-sa-users-filter-option" @click="billingCycle = 'custom'">Custom</button>
            </x-super-admin.dashboard-dropdown>

            <button type="button" class="figma-sa-plans-save-btn" @click="openAdmin()">Save</button>

            <button type="button" class="figma-sa-plans-new-btn" @click="openAdmin(true)">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="12" cy="12" r="9" stroke-width="1.75"/>
                    <path stroke-linecap="round" stroke-width="1.75" d="M12 8v8M8 12h8"/>
                </svg>
                New Plan
            </button>
        </div>

        <h2 class="figma-sa-plans-product-title" x-text="productTitle">{{ $defaultProduct?->name ?? 'Plans' }}</h2>

        <div class="figma-sa-plans-cards-wrap">
            <div class="figma-sa-plans-cards">
                <template x-for="plan in filteredPlans" :key="plan.id">
                    <article class="figma-sa-plans-card" :class="{ 'is-inactive': !plan.is_active, 'is-highlighted': plan.is_highlighted }">
                        <div class="figma-sa-plans-card-head">
                            <div class="figma-sa-plans-card-head-main">
                                <span class="figma-sa-plans-card-tier" x-text="plan.tier.toUpperCase()"></span>
                                <span class="figma-sa-plans-card-price" x-text="plan.price_label"></span>
                            </div>
                            <div class="relative shrink-0">
                                <button type="button" class="figma-sa-plans-card-menu-btn" @click.stop="toggleMenu(plan.id)" aria-label="Plan actions">
                                    <svg fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a1.5 1.5 0 110-3 1.5 1.5 0 010 3zM10 11.5a1.5 1.5 0 110-3 1.5 1.5 0 010 3zM10 17a1.5 1.5 0 110-3 1.5 1.5 0 010 3z"/></svg>
                                </button>
                                <div
                                    x-show="openMenuId === plan.id"
                                    x-cloak
                                    @click.outside="openMenuId = null"
                                    class="figma-sa-plans-card-menu"
                                >
                                    <button type="button" @click="editPlan(plan.id)">Edit Plan</button>
                                    <button type="button" @click="editPlan(plan.id)">Edit Pricing</button>
                                    <button type="button" @click="editPlan(plan.id)" x-text="plan.is_active ? 'Deactivate Plan' : 'Activate Plan'"></button>
                                </div>
                            </div>
                        </div>
                        <div class="figma-sa-plans-card-body">
                            <div class="figma-sa-plans-card-copy">
                                <template x-if="plan.description_lines.length">
                                    <div>
                                        <p class="figma-sa-plans-card-tagline" x-text="plan.description_lines[0]"></p>
                                        <ul x-show="plan.description_lines.length > 1">
                                            <template x-for="line in plan.description_lines.slice(1)" :key="line">
                                                <li x-text="line.replace(/^[•\-]\s*/, '')"></li>
                                            </template>
                                        </ul>
                                    </div>
                                </template>
                                <template x-if="!plan.description_lines.length && plan.feature_lines.length">
                                    <ul>
                                        <template x-for="line in plan.feature_lines" :key="line">
                                            <li x-text="line"></li>
                                        </template>
                                    </ul>
                                </template>
                            </div>
                        </div>
                        <div class="figma-sa-plans-card-foot">
                            <span x-text="plan.status_label"></span>
                        </div>
                    </article>
                </template>
            </div>
            <p x-show="filteredPlans.length === 0" x-cloak class="figma-sa-plans-empty">No plans match your filters.</p>
        </div>

        {{-- Server-rendered cards for no-JS fallback --}}
        <noscript>
            <div class="figma-sa-plans-cards">
                @foreach ($plans as $plan)
                    @php
                        $priceLabel = $plan->is_custom
                            ? ($plan->tier === 'custom' ? 'features' : 'Contact us')
                            : '$'.number_format($plan->price_cents / 100, 0).' / '.($plan->billing_interval === 'yearly' ? 'yr' : 'mo').'.';
                        $statusLabel = $plan->is_custom && $plan->trial_days ? 'Custom Trial' : ($plan->is_active ? 'Active' : 'Inactive');
                    @endphp
                    <article @class(['figma-sa-plans-card', 'is-inactive' => ! $plan->is_active, 'is-highlighted' => $plan->is_highlighted])>
                        <div class="figma-sa-plans-card-head">
                            <div class="figma-sa-plans-card-head-main">
                                <span class="figma-sa-plans-card-tier">{{ strtoupper($plan->tier) }}</span>
                                <span class="figma-sa-plans-card-price">{{ $priceLabel }}</span>
                            </div>
                        </div>
                        <div class="figma-sa-plans-card-body">
                            @if ($plan->short_description)
                                @php $lines = array_values(array_filter(preg_split('/\r\n|\r|\n/', $plan->short_description))); @endphp
                                <div class="figma-sa-plans-card-copy">
                                    @if (! empty($lines[0]))
                                        <p class="figma-sa-plans-card-tagline">{{ $lines[0] }}</p>
                                    @endif
                                    @if (count($lines) > 1)
                                        <ul>
                                            @foreach (array_slice($lines, 1) as $line)
                                                <li>{{ ltrim($line, "•- \t") }}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                            @endif
                        </div>
                        <div class="figma-sa-plans-card-foot"><span>{{ $statusLabel }}</span></div>
                    </article>
                @endforeach
            </div>
        </noscript>

        <div id="plans-admin-panel" x-show="showAdmin" x-cloak class="figma-sa-plans-admin mt-[28px] space-y-6">
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
                        <x-figma-toggle name="is_active" value="1" checked :show-labels="false" />
                        <span class="text-sm text-[#d9d9d9]">Active</span>
                    </label>
                    <label class="inline-flex items-center gap-2 self-end pb-1 cursor-pointer">
                        <input type="hidden" name="is_highlighted" value="0">
                        <x-figma-toggle name="is_highlighted" value="1" :show-labels="false" />
                        <span class="text-sm text-[#d9d9d9]">Highlight</span>
                    </label>
                    <div class="lg:col-span-4">
                        <label class="figma-sa-label">Card description (shown on pricing cards)</label>
                        <textarea name="short_description" rows="5" class="figma-input mt-1" placeholder="For small businesses &amp; starters&#10;• Real-time fake click detection&#10;• Bot &amp; basic VPN traffic blocking"></textarea>
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
                                <tr id="plan-row-{{ $plan->id }}">
                                    <td class="align-top">
                                        <div class="space-y-2">
                                            <input form="{{ $fid }}" name="name" value="{{ $plan->name }}" class="figma-input">
                                            <select form="{{ $fid }}" name="tier" class="figma-select">
                                                @foreach (['basic','pro','premium','enterprise','custom'] as $tier)
                                                    <option value="{{ $tier }}" @selected($plan->tier === $tier)>{{ ucfirst($tier) }}</option>
                                                @endforeach
                                            </select>
                                            <textarea form="{{ $fid }}" name="short_description" rows="4" class="figma-input text-xs" placeholder="Card description">{{ $plan->short_description }}</textarea>
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
                                                <x-figma-toggle form="{{ $fid }}" name="is_highlighted" value="1" :checked="$plan->is_highlighted" :show-labels="false" />
                                                <span class="text-xs text-[#d9d9d9]">Highlight card</span>
                                            </label>
                                        </div>
                                    </td>
                                    <td class="align-top">
                                        <div class="space-y-2">
                                            <input form="{{ $fid }}" name="trial_days" type="number" value="{{ $plan->trial_days }}" class="figma-input">
                                            <textarea form="{{ $fid }}" name="features" rows="3" class="figma-input">{{ collect($plan->feature_limits ?? [])->map(fn($v, $k) => $k.': '.$v)->implode("\n") }}</textarea>
                                            <textarea form="{{ $fid }}" name="feature_flags" rows="3" class="figma-input text-xs">{{ collect($plan->feature_flags ?? [])->map(fn($v, $k) => $k.': '.($v ? '1' : '0'))->implode("\n") }}</textarea>
                                        </div>
                                    </td>
                                    <td class="align-top">
                                        <input form="{{ $fid }}" type="hidden" name="is_active" value="0">
                                        <input form="{{ $fid }}" type="hidden" name="is_custom" value="0">
                                        <div class="space-y-2">
                                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                                <x-figma-toggle form="{{ $fid }}" name="is_active" value="1" :checked="$plan->is_active" :show-labels="false" />
                                                <span class="text-sm text-[#d9d9d9]">Active</span>
                                            </label>
                                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                                <x-figma-toggle form="{{ $fid }}" name="is_custom" value="1" :checked="$plan->is_custom" :show-labels="false" />
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
            </x-super-admin.card>
        </div>
    </div>
</x-super-admin.page>

<script>
function plansPricingPage(plans) {
    return {
        plans,
        search: '',
        planType: '',
        billingCycle: '',
        showAdmin: false,
        openMenuId: null,
        get filteredPlans() {
            return this.plans.filter((plan) => {
                const q = this.search.trim().toLowerCase();
                if (q && !(`${plan.name} ${plan.tier} ${plan.product_name || ''}`.toLowerCase().includes(q))) {
                    return false;
                }
                if (this.planType && plan.tier !== this.planType) return false;
                if (this.billingCycle && plan.billing_interval !== this.billingCycle) return false;
                return true;
            });
        },
        get planTypeLabel() {
            return this.planType ? this.planType.charAt(0).toUpperCase() + this.planType.slice(1) : 'All Plan Types';
        },
        get billingCycleLabel() {
            if (this.billingCycle === 'monthly') return 'Monthly';
            if (this.billingCycle === 'yearly') return 'Yearly';
            if (this.billingCycle === 'custom') return 'Custom';
            return 'Billing Cycle';
        },
        get productTitle() {
            const first = this.filteredPlans[0];
            return first?.product_name || @js($defaultProduct?->name ?? 'Plans');
        },
        toggleMenu(id) {
            this.openMenuId = this.openMenuId === id ? null : id;
        },
        openAdmin(scrollCreate = false) {
            this.showAdmin = true;
            this.$nextTick(() => {
                const panel = document.getElementById('plans-admin-panel');
                panel?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                if (scrollCreate) {
                    panel?.querySelector('form[action*="plans"]')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        },
        editPlan(id) {
            this.openMenuId = null;
            this.showAdmin = true;
            this.$nextTick(() => {
                document.getElementById('plan-row-' + id)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        },
    };
}
</script>
@endsection
