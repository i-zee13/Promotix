@extends('layouts.super-admin')

@section('title', 'Edit Role')

@section('content')
@php
    $old = [
        'name' => old('name', $role->name),
        'slug' => old('slug', $role->slug),
        'description' => old('description', $role->description),
        'portal' => old('portal', $role->portal ?? 'user'),
        'color' => old('color', $role->color ?? '#FF6600'),
        'is_temporary' => (bool) old('is_temporary', $role->is_temporary),
        'expires_at' => old('expires_at', optional($role->expires_at)->format('Y-m-d\\TH:i')),
        'base_role_id' => old('base_role_id', $role->base_role_id),
        'page_access' => old('page_access', $role->page_access ?? []),
        'abilities' => old('abilities', $role->abilities ?? []),
        'scope' => old('scope', $role->scope ?? [
            'mode' => 'workspace',
            'include_future_projects' => false,
            'workspace_ids' => [],
            'project_ids' => [],
            'campaign_ids' => [],
        ]),
        'confirm_review' => false,
    ];
@endphp
<x-super-admin.page :title="'Edit '.$role->name" subtitle="Update portal pages, abilities and scope — packages are not assigned here.">
    <div class="mx-auto max-w-4xl space-y-4">
        <a href="{{ route('super-admin.roles.index') }}" class="figma-sa-btn figma-sa-btn-outline !px-3 !py-2 text-sm">← Back to roles</a>

        @if (session('error'))
            <div class="rounded-lg border border-rose-400/40 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg border border-rose-400/40 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
                <ul class="list-disc pl-4">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <div
            class="rounded-xl border border-white/15 bg-[#141018] p-4 sm:p-6"
            x-data="createRoleWizard(@js([
                'initial' => $old,
                'userPages' => $userPages,
                'userAbilities' => $userAbilities,
                'adminPages' => $adminPages,
                'abilityRequirements' => $abilityRequirements,
                'baseRoles' => $baseRoles->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'portal' => $r->portal ?? 'user'])->values(),
                'storeUrl' => route('super-admin.roles.update', $role),
                'method' => 'PUT',
                'lockedSlug' => $role->slug === 'super-admin',
            ]))"
        >
            <nav class="mb-6 flex flex-wrap gap-2" aria-label="Edit Role steps">
                <template x-for="(s, i) in steps" :key="s.key">
                    <button type="button"
                            class="rounded-full px-3 py-1.5 text-[11px] font-semibold uppercase tracking-wide transition"
                            :class="step === i ? 'bg-[var(--brand-primary,#FF6600)] text-white' : (i < step ? 'bg-white/15 text-white' : (canVisitStep(i) ? 'bg-white/5 text-white/45' : 'bg-white/5 text-white/25 opacity-50 cursor-not-allowed'))"
                            :disabled="!canVisitStep(i)"
                            @click="goStep(i)"
                            x-text="(i + 1) + '. ' + s.label"></button>
                </template>
            </nav>

            <div class="mb-4 rounded-lg border border-[#FF6600]/50 bg-[#FF6600]/10 px-3 py-2 text-[12px] text-white/85">
                A creator cannot grant a page, ability or scope they do not already possess. User Portal roles cannot access Admin Portal pages.
            </div>

            <form method="POST" :action="storeUrl" @submit="onSubmit">
                @csrf
                @method('PUT')

                <div x-show="step === 0" x-cloak class="space-y-4">
                    <h2 class="text-[16px] font-semibold text-white">Role Details</h2>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="figma-sa-label">Portal</label>
                            <div class="mt-1 flex flex-wrap gap-3">
                                <label class="flex cursor-pointer items-center gap-2 text-sm text-white/85">
                                    <input type="radio" name="portal" value="user" x-model="form.portal" class="figma-sa-checkbox"> User Portal
                                </label>
                                <label class="flex cursor-pointer items-center gap-2 text-sm text-white/85">
                                    <input type="radio" name="portal" value="admin" x-model="form.portal" class="figma-sa-checkbox"> Admin Portal
                                </label>
                            </div>
                        </div>
                        <div>
                            <label class="figma-sa-label" for="cr-name">Role name</label>
                            <input id="cr-name" name="name" type="text" x-model="form.name" required class="figma-input mt-1">
                        </div>
                        <div>
                            <label class="figma-sa-label" for="cr-slug">Slug</label>
                            <input id="cr-slug" name="slug" type="text" x-model="form.slug" class="figma-input mt-1" :readonly="lockedSlug">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="figma-sa-label" for="cr-desc">Description</label>
                            <textarea id="cr-desc" name="description" rows="2" x-model="form.description" class="figma-input mt-1"></textarea>
                        </div>
                        <div>
                            <label class="figma-sa-label" for="cr-base">Base role (optional)</label>
                            <select id="cr-base" name="base_role_id" x-model="form.base_role_id" class="figma-input mt-1">
                                <option value="">None</option>
                                <template x-for="r in baseRolesForPortal" :key="'base-' + r.id">
                                    <option :value="String(r.id)" x-text="r.name"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label class="figma-sa-label" for="cr-color">Role color</label>
                            <input id="cr-color" name="color" type="color" x-model="form.color" class="mt-1 h-10 w-full cursor-pointer rounded border border-white/20 bg-transparent p-1">
                        </div>
                        <div class="sm:col-span-2 flex flex-wrap items-center gap-4">
                            <label class="flex items-center gap-2 text-sm text-white/85">
                                <input type="checkbox" name="is_temporary" value="1" x-model="form.is_temporary" class="figma-sa-checkbox"> Temporary role
                            </label>
                            <div x-show="form.is_temporary" x-cloak>
                                <label class="figma-sa-label" for="cr-exp">Expires at</label>
                                <input id="cr-exp" name="expires_at" type="datetime-local" x-model="form.expires_at" class="figma-input mt-1">
                            </div>
                        </div>
                    </div>
                </div>

                <div x-show="step === 1" x-cloak class="space-y-4">
                    <h2 class="text-[16px] font-semibold text-white">Page Access</h2>
                    <div class="overflow-x-auto rounded-lg border border-white/10">
                        <table class="w-full text-left text-[12px]">
                            <thead class="bg-[#2a2118] text-white">
                                <tr>
                                    <th class="px-3 py-2">Page</th>
                                    <th class="px-3 py-2">Meaning</th>
                                    <th class="px-3 py-2">Access</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(page, idx) in pagesForPortal" :key="'pg-' + page.key">
                                    <tr :class="idx % 2 ? 'bg-[#1a1410]' : 'bg-[#120f0c]'">
                                        <td class="px-3 py-2 font-medium text-white" x-text="page.label"></td>
                                        <td class="px-3 py-2 text-white/65" x-text="page.view_means || page.definition"></td>
                                        <td class="px-3 py-2">
                                            <select class="figma-input !py-1 text-[11px]"
                                                    :name="'page_access[' + page.key + ']'"
                                                    x-model="form.page_access[page.key]"
                                                    @change="pruneAbilities()">
                                                <option value="none">No access</option>
                                                <option value="view">View</option>
                                                <option value="full">Full access</option>
                                            </select>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div x-show="step === 2" x-cloak class="space-y-4">
                    <h2 class="text-[16px] font-semibold text-white">Abilities</h2>
                    <div class="space-y-2" x-show="form.portal === 'user'">
                        <template x-for="ab in userAbilities" :key="'ab-' + ab.key">
                            <label class="flex items-start gap-3 rounded-lg border border-white/10 px-3 py-2"
                                   :class="abilityEnabled(ab.key) ? 'bg-black/20' : 'opacity-45'">
                                <input type="checkbox" class="figma-sa-checkbox mt-1" :value="ab.key"
                                       :disabled="!abilityEnabled(ab.key)"
                                       :checked="form.abilities.includes(ab.key)"
                                       @change="toggleAbility(ab.key, $event.target.checked)">
                                <span class="min-w-0 flex-1">
                                    <span class="block text-[13px] font-medium text-white" x-text="ab.label"></span>
                                    <span class="block text-[11px] text-white/60" x-text="ab.definition"></span>
                                </span>
                            </label>
                        </template>
                        <template x-for="abKey in form.abilities" :key="'hid-ab-' + abKey">
                            <input type="hidden" name="abilities[]" :value="abKey">
                        </template>
                    </div>
                    <p class="text-[12px] text-white/55" x-show="form.portal === 'admin'">Admin grants follow page access from the previous step.</p>
                </div>

                <div x-show="step === 3" x-cloak class="space-y-4">
                    <h2 class="text-[16px] font-semibold text-white">Scope</h2>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-white/15 px-3 py-3 text-sm text-white/85">
                            <input type="radio" name="scope[mode]" value="workspace" x-model="form.scope.mode" class="figma-sa-checkbox"> Workspace
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-white/15 px-3 py-3 text-sm text-white/85">
                            <input type="radio" name="scope[mode]" value="project" x-model="form.scope.mode" class="figma-sa-checkbox"> Project
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-white/15 px-3 py-3 text-sm text-white/85">
                            <input type="radio" name="scope[mode]" value="campaign" x-model="form.scope.mode" class="figma-sa-checkbox"> Campaign
                        </label>
                    </div>
                    <label class="flex items-start gap-2 text-sm text-white/85">
                        <input type="checkbox" name="scope[include_future_projects]" value="1" x-model="form.scope.include_future_projects" class="figma-sa-checkbox mt-0.5">
                        <span>Include future projects (explicit)</span>
                    </label>
                </div>

                <div x-show="step === 4" x-cloak class="space-y-4">
                    <h2 class="text-[16px] font-semibold text-white">Review Role</h2>
                    <div class="space-y-3 rounded-lg border border-white/10 bg-black/25 p-4 text-[12px] text-white/80">
                        <p><span class="text-white/45">Portal:</span> <span class="font-semibold text-white" x-text="form.portal === 'admin' ? 'Admin Portal' : 'User Portal'"></span></p>
                        <p><span class="text-white/45">Name:</span> <span class="font-semibold text-white" x-text="form.name || '—'"></span></p>
                        <p><span class="text-white/45">Scope:</span> <span class="capitalize" x-text="form.scope.mode"></span>
                            <span x-text="form.scope.include_future_projects ? ' · future projects included' : ' · future projects excluded'"></span>
                        </p>
                        <ul class="list-disc pl-4">
                            <template x-for="page in pagesForPortal" :key="'rv-' + page.key">
                                <li x-show="(form.page_access[page.key] || 'none') !== 'none'">
                                    <span x-text="page.label"></span> — <span class="capitalize" x-text="form.page_access[page.key]"></span>
                                </li>
                            </template>
                        </ul>
                    </div>
                    <label class="flex items-start gap-2 text-sm text-white">
                        <input type="checkbox" name="confirm_review" value="1" x-model="form.confirm_review" class="figma-sa-checkbox mt-0.5" required>
                        <span>I confirm this role’s portal, pages, abilities and scope are correct.</span>
                    </label>
                </div>

                <div class="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-white/10 pt-4">
                    <button type="button" class="figma-sa-btn figma-sa-btn-outline" @click="prev" :disabled="step === 0">Back</button>
                    <div class="flex gap-2">
                        <a href="{{ route('super-admin.roles.index') }}" class="figma-sa-btn figma-sa-btn-outline">Cancel</a>
                        <button type="button" class="figma-sa-btn figma-sa-btn-primary" x-show="step < 4" @click="next">Continue</button>
                        <button type="submit" class="figma-sa-btn figma-sa-btn-primary" x-show="step === 4" x-cloak :disabled="!form.confirm_review">Save role</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-super-admin.page>

<script>
function createRoleWizard(cfg) {
    const initialAccess = {};
    (cfg.userPages || []).forEach((p) => { initialAccess[p.key] = 'none'; });
    (cfg.adminPages || []).forEach((p) => { initialAccess[p.key] = 'none'; });
    Object.assign(initialAccess, cfg.initial?.page_access || {});

    return {
        step: 0,
        maxReached: 0,
        stepError: '',
        storeUrl: cfg.storeUrl,
        lockedSlug: Boolean(cfg.lockedSlug),
        steps: [
            { key: 'details', label: 'Details' },
            { key: 'pages', label: 'Pages' },
            { key: 'abilities', label: 'Abilities' },
            { key: 'scope', label: 'Scope' },
            { key: 'review', label: 'Review' },
        ],
        userPages: cfg.userPages || [],
        userAbilities: cfg.userAbilities || [],
        adminPages: cfg.adminPages || [],
        abilityRequirements: cfg.abilityRequirements || {},
        baseRoles: cfg.baseRoles || [],
        form: {
            name: cfg.initial?.name || '',
            slug: cfg.initial?.slug || '',
            description: cfg.initial?.description || '',
            portal: cfg.initial?.portal || 'user',
            color: cfg.initial?.color || '#FF6600',
            is_temporary: Boolean(cfg.initial?.is_temporary),
            expires_at: cfg.initial?.expires_at || '',
            base_role_id: cfg.initial?.base_role_id ? String(cfg.initial.base_role_id) : '',
            page_access: initialAccess,
            abilities: Array.isArray(cfg.initial?.abilities) ? cfg.initial.abilities.slice() : [],
            scope: Object.assign({
                mode: 'workspace',
                include_future_projects: false,
                workspace_ids: [],
                project_ids: [],
                campaign_ids: [],
            }, cfg.initial?.scope || {}),
            confirm_review: false,
        },
        get pagesForPortal() {
            return this.form.portal === 'admin' ? this.adminPages : this.userPages;
        },
        get baseRolesForPortal() {
            return this.baseRoles.filter((r) => (r.portal || 'user') === this.form.portal);
        },
        goStep(i) {
            const target = Number(i);
            if (Number.isNaN(target) || target < 0 || target >= this.steps.length) return;
            if (target <= this.maxReached) {
                this.stepError = '';
                this.step = target;
                return;
            }
            for (let s = 0; s < target; s++) {
                const err = this.stepValidationError(s);
                if (err) {
                    this.step = s;
                    this.stepError = err;
                    alert(err);
                    return;
                }
            }
            this.stepError = '';
            this.step = target;
            this.maxReached = Math.max(this.maxReached, target);
        },
        canVisitStep(i) {
            return Number(i) <= this.maxReached;
        },
        stepValidationError(i) {
            if (i === 0) {
                if (!String(this.form.name || '').trim()) return 'Role name is required before you can continue.';
                if (this.form.is_temporary && !String(this.form.expires_at || '').trim()) {
                    return 'Expiration date is required for a temporary role.';
                }
            }
            return '';
        },
        next() {
            const err = this.stepValidationError(this.step);
            if (err) {
                this.stepError = err;
                alert(err);
                return;
            }
            this.stepError = '';
            if (this.step < 4) {
                this.step += 1;
                this.maxReached = Math.max(this.maxReached, this.step);
            }
            if (this.step === 2) this.pruneAbilities();
        },
        prev() { if (this.step > 0) { this.stepError = ''; this.step -= 1; } },
        hasAnyPageAccess() {
            return Object.values(this.form.page_access || {}).some((l) => l === 'view' || l === 'full');
        },
        abilityEnabled(key) {
            if (this.form.portal !== 'user') return false;
            if (!this.hasAnyPageAccess()) return false;
            const reqs = this.abilityRequirements[key] || [];
            if (['create_data', 'edit_data', 'delete_data'].includes(key)) {
                if (!Object.values(this.form.page_access || {}).some((l) => l === 'full')) return false;
            }
            if (!reqs.length) return true;
            return reqs.some((pageKey) => (this.form.page_access[pageKey] || 'none') === 'full');
        },
        toggleAbility(key, on) {
            if (!this.abilityEnabled(key)) return;
            const set = new Set(this.form.abilities);
            if (on) set.add(key); else set.delete(key);
            this.form.abilities = Array.from(set);
        },
        pruneAbilities() {
            this.form.abilities = this.form.abilities.filter((k) => this.abilityEnabled(k));
        },
        onSubmit(e) {
            if (this.step !== 4 || !this.form.confirm_review) {
                e.preventDefault();
                this.step = 4;
                return;
            }
            this.pruneAbilities();
        },
    };
}
</script>
@endsection
