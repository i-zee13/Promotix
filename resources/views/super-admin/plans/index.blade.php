@extends('layouts.super-admin')

@section('title', 'Plans & Pricing')

@section('content')
@php
    $defaultProduct = $products->first();
    $plansPayload = $plans->map(function ($p) {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'tier' => $p->tier,
            'product_id' => $p->saas_product_id,
            'product_name' => $p->product?->name,
            'billing_interval' => $p->billing_interval,
            'currency' => $p->currency ?: 'USD',
            'price' => round($p->price_cents / 100, 2),
            'price_yearly' => $p->price_yearly_cents !== null ? round($p->price_yearly_cents / 100, 2) : '',
            'trial_days' => (int) ($p->trial_days ?? 0),
            'sort_order' => (int) ($p->sort_order ?? 0),
            'cta_label' => $p->cta_label,
            'is_active' => (bool) $p->is_active,
            'is_custom' => (bool) $p->is_custom,
            'is_highlighted' => (bool) $p->is_highlighted,
            'short_description' => $p->short_description,
            'features' => collect($p->feature_limits ?? [])->map(fn ($v, $k) => $k.': '.$v)->implode("\n"),
            'feature_flags' => collect($p->feature_flags ?? [])->map(fn ($v, $k) => $k.': '.($v ? '1' : '0'))->implode("\n"),
            'price_label' => $p->is_custom
                ? ($p->tier === 'custom' ? 'features' : 'Contact us')
                : '$'.number_format($p->price_cents / 100, 0).' / '.($p->billing_interval === 'yearly' ? 'yr' : 'mo').'.',
            'status_label' => $p->is_custom && $p->trial_days
                ? 'Custom Trial'
                : ($p->is_active ? 'Active' : 'Inactive'),
            'description_lines' => array_values(array_filter(preg_split('/\r\n|\r|\n/', (string) ($p->short_description ?? '')))),
            'feature_lines' => collect($p->feature_limits ?? [])->take(8)->map(fn ($v, $k) => is_string($k) ? ucwords(str_replace('_', ' ', $k)).': '.$v : $v)->values()->all(),
            'update_url' => route('super-admin.plans.update', $p),
            'archive_url' => route('super-admin.plans.destroy', $p),
        ];
    })->values();
@endphp

<x-super-admin.page title="Plans & Pricing">
    <div
        class="figma-sa-plans"
        x-data="plansPricingPage(@js($plansPayload), @js($products->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values()), @js(route('super-admin.plans.store')), @js($defaultProduct?->name ?? 'Plans'))"
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

            <button type="button" class="figma-sa-plans-save-btn" @click="submitModal()" x-show="modalOpen" x-cloak>Save</button>

            <button type="button" class="figma-sa-plans-new-btn" @click="openCreate()">
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
                                    <button type="button" @click="openEdit(plan.id)">Edit Plan</button>
                                    <button type="button" @click="openEdit(plan.id)">Edit Pricing</button>
                                    <button type="button" @click="quickToggleActive(plan.id)" x-text="plan.is_active ? 'Deactivate Plan' : 'Activate Plan'"></button>
                                    <button type="button" class="is-danger" @click="confirmArchive(plan.id)">Delete Plan</button>
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

        {{-- Edit / Create modal --}}
        <div
            class="figma-sa-plans-modal-backdrop"
            x-show="modalOpen"
            x-cloak
            @keydown.escape.window="closeModal()"
        >
            <div class="figma-sa-plans-modal" @click.outside="closeModal()" role="dialog" aria-modal="true" :aria-labelledby="'plan-modal-title'">
                <button type="button" class="figma-sa-plans-modal-close" @click="closeModal()" aria-label="Close">&times;</button>
                <h2 id="plan-modal-title" class="figma-sa-plans-modal-title" x-text="formMode === 'create' ? 'New Plan' : 'Edit Plan'"></h2>
                <p class="figma-sa-plans-modal-sub" x-text="formMode === 'create' ? 'Create a pricing card and set limits.' : ('Editing ' + (form.name || 'plan'))"></p>

                <form method="POST" :action="formAction" class="figma-sa-plans-modal-form" id="plan-modal-form">
                    @csrf
                    <input type="hidden" name="_method" value="PUT" :disabled="formMode !== 'edit'">

                    <div class="figma-sa-plans-modal-grid">
                        <div>
                            <label class="figma-sa-label">Product</label>
                            <select name="saas_product_id" class="figma-select mt-1" x-model="form.product_id">
                                <option value="">No product</option>
                                <template x-for="product in products" :key="product.id">
                                    <option :value="product.id" x-text="product.name"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label class="figma-sa-label">Plan name</label>
                            <input name="name" required class="figma-input mt-1" placeholder="Basic / Pro / Premium" x-model="form.name">
                        </div>
                        <div>
                            <label class="figma-sa-label">Tier</label>
                            <select name="tier" class="figma-select mt-1" x-model="form.tier">
                                <option value="basic">Basic</option>
                                <option value="pro">Pro</option>
                                <option value="premium">Premium</option>
                                <option value="enterprise">Enterprise</option>
                                <option value="custom">Custom</option>
                            </select>
                        </div>
                        <div>
                            <label class="figma-sa-label">Price</label>
                            <input name="price" type="number" step="0.01" min="0" class="figma-input mt-1" placeholder="9.99" x-model="form.price">
                        </div>
                        <div>
                            <label class="figma-sa-label">Yearly price (total)</label>
                            <input name="price_yearly" type="number" step="0.01" min="0" class="figma-input mt-1" placeholder="optional" x-model="form.price_yearly">
                        </div>
                        <div>
                            <label class="figma-sa-label">Currency</label>
                            <input name="currency" maxlength="3" class="figma-input mt-1" x-model="form.currency">
                        </div>
                        <div>
                            <label class="figma-sa-label">Billing</label>
                            <select name="billing_interval" class="figma-select mt-1" x-model="form.billing_interval">
                                <option value="monthly">Monthly</option>
                                <option value="yearly">Yearly</option>
                                <option value="custom">Custom</option>
                            </select>
                        </div>
                        <div>
                            <label class="figma-sa-label">Trial days</label>
                            <input name="trial_days" type="number" min="0" class="figma-input mt-1" x-model="form.trial_days">
                        </div>
                        <div>
                            <label class="figma-sa-label">Sort order</label>
                            <input name="sort_order" type="number" min="0" class="figma-input mt-1" x-model="form.sort_order">
                        </div>
                        <div>
                            <label class="figma-sa-label">CTA label</label>
                            <input name="cta_label" maxlength="80" class="figma-input mt-1" placeholder="Start free trial" x-model="form.cta_label">
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="figma-sa-label">Card description</label>
                        <textarea name="short_description" rows="5" class="figma-input mt-1" placeholder="For small businesses & starters&#10;Real-time fake click detection&#10;Bot & basic VPN traffic blocking" x-model="form.short_description"></textarea>
                    </div>

                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="figma-sa-label">Feature limits</label>
                            <textarea name="features" rows="4" class="figma-input mt-1" placeholder="domains: 10" x-model="form.features"></textarea>
                        </div>
                        <div>
                            <label class="figma-sa-label">Feature flags</label>
                            <textarea name="feature_flags" rows="4" class="figma-input mt-1" placeholder="ad_protection: 1&#10;detection_vpn: 1" x-model="form.feature_flags"></textarea>
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="figma-sa-label">Detection panel modules (plan gate)</label>
                        <p class="mt-1 text-[11px] text-[#a9a9a9]">Off = customer ko yeh detection panel mein nahi milegi / enforce nahi hogi.</p>
                        <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach (\App\Support\DetectionPlanFeatures::catalog() as $det)
                                <label class="inline-flex items-start gap-2 rounded-[8px] border border-white/10 bg-black/20 px-3 py-2 text-[11px] text-[#d9d9d9] cursor-pointer">
                                    <input
                                        type="checkbox"
                                        class="mt-[2px] accent-[#6400B2]"
                                        :checked="detectionFlagOn(@js($det['key']))"
                                        @change="setDetectionFlag(@js($det['key']), $event.target.checked)"
                                    >
                                    <span>
                                        <span class="block font-semibold text-white">{{ $det['label'] }}</span>
                                        <span class="block text-[#a9a9a9]">{{ $det['description'] }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-5">
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" class="figma-sa-plans-check" x-model="form.is_active">
                            <span class="text-sm text-[#d9d9d9]">Active</span>
                        </label>
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="is_custom" value="0">
                            <input type="checkbox" name="is_custom" value="1" class="figma-sa-plans-check" x-model="form.is_custom">
                            <span class="text-sm text-[#d9d9d9]">Custom</span>
                        </label>
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="is_highlighted" value="0">
                            <input type="checkbox" name="is_highlighted" value="1" class="figma-sa-plans-check" x-model="form.is_highlighted">
                            <span class="text-sm text-[#d9d9d9]">Highlight card</span>
                        </label>
                    </div>

                    <div class="figma-sa-plans-modal-actions">
                        <button type="button" class="figma-sa-btn figma-sa-btn-outline" @click="closeModal()">Cancel</button>
                        <template x-if="formMode === 'edit'">
                            <button type="button" class="figma-sa-btn figma-sa-btn-danger" @click="confirmArchive(form.id)">Delete</button>
                        </template>
                        <button type="submit" class="figma-sa-btn figma-sa-btn-primary" x-text="formMode === 'create' ? 'Create Plan' : 'Save Changes'"></button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Hidden archive forms --}}
        <template x-for="plan in plans" :key="'archive-' + plan.id">
            <form :id="'plan-archive-' + plan.id" method="POST" :action="plan.archive_url" class="hidden" onsubmit="return confirm('Archive this plan?')">
                @csrf
                @method('DELETE')
            </form>
        </template>

        {{-- Quick activate/deactivate forms --}}
        <template x-for="plan in plans" :key="'toggle-' + plan.id">
            <form :id="'plan-toggle-' + plan.id" method="POST" :action="plan.update_url" class="hidden">
                @csrf
                @method('PUT')
                <input type="hidden" name="name" :value="plan.name">
                <input type="hidden" name="tier" :value="plan.tier">
                <input type="hidden" name="saas_product_id" :value="plan.product_id || ''">
                <input type="hidden" name="price" :value="plan.price">
                <input type="hidden" name="price_yearly" :value="plan.price_yearly">
                <input type="hidden" name="currency" :value="plan.currency">
                <input type="hidden" name="billing_interval" :value="plan.billing_interval">
                <input type="hidden" name="trial_days" :value="plan.trial_days">
                <input type="hidden" name="sort_order" :value="plan.sort_order">
                <input type="hidden" name="cta_label" :value="plan.cta_label || ''">
                <input type="hidden" name="short_description" :value="plan.short_description || ''">
                <input type="hidden" name="features" :value="plan.features || ''">
                <input type="hidden" name="feature_flags" :value="plan.feature_flags || ''">
                <input type="hidden" name="is_custom" :value="plan.is_custom ? 1 : 0">
                <input type="hidden" name="is_highlighted" :value="plan.is_highlighted ? 1 : 0">
                <input type="hidden" name="is_active" :value="plan.is_active ? 0 : 1">
            </form>
        </template>
    </div>
</x-super-admin.page>

<script>
function plansPricingPage(plans, products, storeUrl, defaultProductTitle) {
    const blankForm = () => ({
        id: null,
        name: '',
        tier: 'basic',
        product_id: '',
        billing_interval: 'monthly',
        currency: 'USD',
        price: '',
        price_yearly: '',
        trial_days: 0,
        sort_order: 0,
        cta_label: '',
        short_description: '',
        features: '',
        feature_flags: '',
        is_active: true,
        is_custom: false,
        is_highlighted: false,
    });

    return {
        plans,
        products,
        storeUrl,
        defaultProductTitle,
        search: '',
        planType: '',
        billingCycle: '',
        openMenuId: null,
        modalOpen: false,
        formMode: 'create',
        form: blankForm(),
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
            return first?.product_name || this.defaultProductTitle;
        },
        get formAction() {
            if (this.formMode === 'edit' && this.form.id) {
                const plan = this.plans.find((p) => p.id === this.form.id);
                return plan?.update_url || this.storeUrl;
            }
            return this.storeUrl;
        },
        parseFlagMap(raw) {
            const map = {};
            String(raw || '').split('\n').forEach((line) => {
                const trimmed = line.trim();
                if (!trimmed) return;
                const parts = trimmed.split(':');
                const key = (parts[0] || '').trim().toLowerCase().replace(/[\s-]+/g, '_');
                if (!key) return;
                const value = (parts.slice(1).join(':') || '1').trim().toLowerCase();
                map[key] = ['1', 'true', 'yes', 'on'].includes(value);
            });
            return map;
        },
        serializeFlagMap(map) {
            return Object.keys(map).sort().map((k) => `${k}: ${map[k] ? '1' : '0'}`).join('\n');
        },
        detectionFlagOn(key) {
            const map = this.parseFlagMap(this.form.feature_flags);
            return map[key] !== false;
        },
        setDetectionFlag(key, on) {
            const map = this.parseFlagMap(this.form.feature_flags);
            map[key] = !!on;
            this.form.feature_flags = this.serializeFlagMap(map);
        },
        toggleMenu(id) {
            this.openMenuId = this.openMenuId === id ? null : id;
        },
        openCreate() {
            this.openMenuId = null;
            this.formMode = 'create';
            this.form = blankForm();
            this.modalOpen = true;
        },
        openEdit(id) {
            const plan = this.plans.find((p) => p.id === id);
            if (!plan) return;
            this.openMenuId = null;
            this.formMode = 'edit';
            this.form = {
                id: plan.id,
                name: plan.name || '',
                tier: plan.tier || 'basic',
                product_id: plan.product_id ? String(plan.product_id) : '',
                billing_interval: plan.billing_interval || 'monthly',
                currency: plan.currency || 'USD',
                price: plan.price ?? '',
                price_yearly: plan.price_yearly ?? '',
                trial_days: plan.trial_days ?? 0,
                sort_order: plan.sort_order ?? 0,
                cta_label: plan.cta_label || '',
                short_description: plan.short_description || '',
                features: plan.features || '',
                feature_flags: plan.feature_flags || '',
                is_active: !!plan.is_active,
                is_custom: !!plan.is_custom,
                is_highlighted: !!plan.is_highlighted,
            };
            this.modalOpen = true;
        },
        closeModal() {
            this.modalOpen = false;
        },
        submitModal() {
            document.getElementById('plan-modal-form')?.requestSubmit();
        },
        confirmArchive(id) {
            this.openMenuId = null;
            this.modalOpen = false;
            const form = document.getElementById('plan-archive-' + id);
            if (form) form.requestSubmit();
        },
        quickToggleActive(id) {
            this.openMenuId = null;
            const form = document.getElementById('plan-toggle-' + id);
            if (form) form.submit();
        },
    };
}
</script>
@endsection
