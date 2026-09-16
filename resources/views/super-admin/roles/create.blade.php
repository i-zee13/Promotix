@extends('layouts.super-admin')

@section('title', 'Create Role')

@section('content')
@php
    $old = [
        'name' => old('name', ''),
        'slug' => old('slug', ''),
        'description' => old('description', ''),
        'portal' => old('portal', 'user'),
        'color' => old('color', '#7C3AED'),
        'is_temporary' => (bool) old('is_temporary', false),
        'expires_at' => old('expires_at', ''),
        'base_role_id' => old('base_role_id', ''),
        'page_access' => old('page_access', []),
        'abilities' => old('abilities', []),
        'scope' => old('scope', [
            'mode' => 'workspace',
            'include_future_projects' => false,
            'workspace_ids' => [],
            'project_ids' => [],
            'campaign_ids' => [],
        ]),
        'confirm_review' => false,
    ];
@endphp

<style>
    .crw { color: #fff; }
    .crw-head {
        display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between;
        gap: 16px; margin-bottom: 22px;
    }
    .crw-crumb { margin: 0 0 6px; font-size: 12px; color: rgba(255,255,255,.45); }
    .crw-crumb a { color: rgba(255,255,255,.55); text-decoration: none; }
    .crw-crumb a:hover { color: #fff; }
    .crw-title { margin: 0; font-size: 28px; font-weight: 600; line-height: 1.15; color: #fff; }
    .crw-sub { margin: 6px 0 0; font-size: 13px; color: rgba(255,255,255,.55); max-width: 520px; }
    .crw-actions { display: flex; flex-wrap: wrap; gap: 8px; }
    .crw-btn {
        display: inline-flex; align-items: center; justify-content: center; gap: 6px;
        min-height: 38px; padding: 0 16px; border-radius: 8px; font-size: 13px; font-weight: 600;
        border: 1px solid rgba(255,255,255,.2); background: transparent; color: #fff; cursor: pointer;
        text-decoration: none; transition: .15s ease;
    }
    .crw-btn:hover { border-color: rgba(255,255,255,.4); }
    .crw-btn-primary { background: var(--brand-primary, #FF6600); border-color: var(--brand-primary, #FF6600); color: #fff; }
    .crw-btn-primary:hover { filter: brightness(1.05); }
    .crw-btn-primary:disabled { opacity: .45; cursor: not-allowed; filter: none; }
    .crw-layout {
        display: grid; gap: 18px;
        grid-template-columns: minmax(180px, 210px) minmax(0, 1fr) minmax(240px, 280px);
        align-items: start;
    }
    @media (max-width: 1100px) {
        .crw-layout { grid-template-columns: 1fr; }
    }
    .crw-stepper {
        border: 1px solid rgba(255,102,0,.35); border-radius: 12px; padding: 18px 14px;
        background: rgba(0,0,0,.25);
    }
    .crw-step {
        display: grid; grid-template-columns: 28px 1fr; gap: 10px; padding: 10px 6px;
        border-radius: 8px; cursor: pointer; text-align: left; width: 100%;
        background: transparent; border: 0; color: inherit;
    }
    .crw-step + .crw-step { margin-top: 2px; }
    .crw-step.is-active { background: rgba(255,102,0,.12); }
    .crw-step-num {
        width: 28px; height: 28px; border-radius: 999px; display: grid; place-items: center;
        font-size: 12px; font-weight: 700; border: 1px solid rgba(255,255,255,.25);
        color: rgba(255,255,255,.55); background: #111;
    }
    .crw-step.is-active .crw-step-num,
    .crw-step.is-done .crw-step-num {
        background: var(--brand-primary, #FF6600); border-color: var(--brand-primary, #FF6600); color: #fff;
    }
    .crw-step-label { font-size: 13px; font-weight: 600; color: rgba(255,255,255,.85); }
    .crw-step.is-active .crw-step-label { color: #fff; }
    .crw-step-desc { font-size: 11px; color: rgba(255,255,255,.45); margin-top: 2px; }
    .crw-panel {
        border: 1px solid rgba(255,102,0,.4); border-radius: 12px; background: rgba(10,10,12,.9);
        padding: 20px 18px; min-height: 420px;
    }
    .crw-panel-title { margin: 0 0 4px; font-size: 18px; font-weight: 600; }
    .crw-panel-lead { margin: 0 0 18px; font-size: 12px; color: rgba(255,255,255,.55); }
    .crw-field { margin-bottom: 14px; }
    .crw-label { display: block; margin-bottom: 6px; font-size: 12px; font-weight: 600; color: rgba(255,255,255,.8); }
    .crw-hint { margin: 6px 0 0; font-size: 11px; color: rgba(255,255,255,.4); }
    .crw-count { float: right; font-weight: 500; color: rgba(255,255,255,.4); }
    .crw-input, .crw-select, .crw-textarea {
        width: 100%; border-radius: 8px; border: 1px solid rgba(255,255,255,.18);
        background: #101010; color: #fff; padding: 10px 12px; font-size: 13px;
    }
    .crw-textarea { min-height: 88px; resize: vertical; }
    .crw-input:focus, .crw-select:focus, .crw-textarea:focus {
        outline: none; border-color: var(--brand-primary, #FF6600);
    }
    .crw-grid-2 { display: grid; gap: 14px; grid-template-columns: 1fr 1fr; }
    @media (max-width: 700px) { .crw-grid-2 { grid-template-columns: 1fr; } }
    .crw-radio {
        display: flex; gap: 10px; align-items: flex-start; padding: 12px;
        border: 1px solid rgba(255,255,255,.12); border-radius: 10px; cursor: pointer;
        background: rgba(0,0,0,.2);
    }
    .crw-radio.is-on { border-color: var(--brand-primary, #FF6600); background: rgba(255,102,0,.08); }
    .crw-radio input { margin-top: 2px; accent-color: var(--brand-primary, #FF6600); }
    .crw-radio strong { display: block; font-size: 13px; }
    .crw-radio span { display: block; font-size: 11px; color: rgba(255,255,255,.5); margin-top: 2px; }
    .crw-callout {
        display: flex; gap: 10px; align-items: flex-start; margin-top: 16px;
        border: 1px solid rgba(255,102,0,.45); border-radius: 10px; padding: 12px 14px;
        background: rgba(255,102,0,.08); font-size: 12px; color: rgba(255,255,255,.85);
    }
    .crw-callout--amber { border-color: rgba(245,158,11,.5); background: rgba(245,158,11,.1); }
    .crw-callout--green { border-color: rgba(34,197,94,.45); background: rgba(34,197,94,.1); color: #bbf7d0; }
    .crw-portal-cards { display: grid; gap: 12px; grid-template-columns: 1fr 1fr; }
    @media (max-width: 700px) { .crw-portal-cards { grid-template-columns: 1fr; } }
    .crw-portal-card {
        border: 1px solid rgba(255,255,255,.15); border-radius: 12px; padding: 16px;
        background: rgba(0,0,0,.25); cursor: pointer; text-align: left; color: inherit; width: 100%;
    }
    .crw-portal-card.is-on { border-color: var(--brand-primary, #FF6600); box-shadow: inset 0 0 0 1px var(--brand-primary, #FF6600); }
    .crw-portal-icon {
        width: 40px; height: 40px; border-radius: 10px; display: grid; place-items: center; margin-bottom: 10px;
    }
    .crw-portal-icon--user { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
    .crw-portal-icon--admin { background: linear-gradient(135deg, #ef4444, #b91c1c); }
    .crw-scope-seg {
        display: flex; flex-wrap: wrap; gap: 6px; padding: 4px; border-radius: 10px;
        background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.1); margin-bottom: 12px;
    }
    .crw-scope-seg button {
        flex: 1; min-width: 120px; border: 0; border-radius: 8px; padding: 9px 10px;
        background: transparent; color: rgba(255,255,255,.65); font-size: 12px; font-weight: 600; cursor: pointer;
    }
    .crw-scope-seg button.is-on { background: var(--brand-primary, #FF6600); color: #fff; }
    .crw-chips { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
    .crw-chip {
        display: inline-flex; align-items: center; gap: 6px; border-radius: 999px;
        background: rgba(255,102,0,.15); border: 1px solid rgba(255,102,0,.4);
        padding: 5px 10px; font-size: 11px; color: #fff;
    }
    .crw-chip button { border: 0; background: transparent; color: rgba(255,255,255,.7); cursor: pointer; font-size: 14px; line-height: 1; }
    .crw-toolbar { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 14px; }
    .crw-toolbar .crw-input, .crw-toolbar .crw-select { max-width: 240px; }
    .crw-table { width: 100%; border-collapse: collapse; font-size: 12px; }
    .crw-table th {
        text-align: left; padding: 10px 8px; color: rgba(255,255,255,.55); font-weight: 600;
        border-bottom: 1px solid rgba(255,255,255,.1);
    }
    .crw-table td { padding: 12px 8px; border-bottom: 1px solid rgba(255,255,255,.06); vertical-align: middle; }
    .crw-table tr:hover td { background: rgba(255,255,255,.03); }
    .crw-page-cell { display: flex; align-items: center; gap: 10px; font-weight: 600; }
    .crw-page-ico {
        width: 28px; height: 28px; border-radius: 8px; display: grid; place-items: center;
        background: rgba(255,102,0,.15); color: #ffb380; flex-shrink: 0;
    }
    .crw-access { display: flex; gap: 14px; flex-wrap: wrap; }
    .crw-access label { display: inline-flex; align-items: center; gap: 6px; color: rgba(255,255,255,.75); cursor: pointer; }
    .crw-access input { accent-color: var(--brand-primary, #FF6600); }
    .crw-ability-group { margin-bottom: 16px; }
    .crw-ability-group h3 { margin: 0 0 8px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: rgba(255,255,255,.5); }
    .crw-ability {
        display: flex; gap: 12px; align-items: flex-start; padding: 12px;
        border: 1px solid rgba(255,255,255,.1); border-radius: 10px; margin-bottom: 8px;
        background: rgba(0,0,0,.2);
    }
    .crw-ability.is-off { opacity: .45; }
    .crw-ability input { margin-top: 3px; accent-color: var(--brand-primary, #FF6600); }
    .crw-tag {
        display: inline-block; margin-top: 6px; padding: 2px 8px; border-radius: 999px;
        background: rgba(255,255,255,.08); color: rgba(255,255,255,.55); font-size: 10px; font-weight: 600;
    }
    .crw-summary {
        border: 1px solid rgba(255,102,0,.35); border-radius: 12px; background: rgba(0,0,0,.3);
        padding: 16px; position: sticky; top: 16px;
    }
    .crw-summary-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
    .crw-summary-head h2 { margin: 0; font-size: 14px; font-weight: 600; }
    .crw-badge {
        font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em;
        padding: 4px 8px; border-radius: 999px; background: rgba(255,102,0,.2); color: #ffb380;
    }
    .crw-identity { display: flex; gap: 10px; align-items: center; margin-bottom: 14px; }
    .crw-avatar {
        width: 42px; height: 42px; border-radius: 10px; display: grid; place-items: center; flex-shrink: 0;
    }
    .crw-identity strong { display: block; font-size: 14px; }
    .crw-identity span { font-size: 11px; color: rgba(255,255,255,.45); }
    .crw-meta { list-style: none; margin: 0; padding: 0; display: grid; gap: 10px; }
    .crw-meta li { display: flex; justify-content: space-between; gap: 10px; font-size: 12px; }
    .crw-meta li span { color: rgba(255,255,255,.45); }
    .crw-meta li strong { color: #fff; font-weight: 600; text-align: right; }
    .crw-summary .crw-btn-primary { width: 100%; margin-top: 16px; }
    .crw-foot {
        margin-top: 16px; display: flex; align-items: center; gap: 8px;
        border: 1px solid rgba(255,255,255,.1); border-radius: 10px; padding: 10px 12px;
        font-size: 11px; color: rgba(255,255,255,.5); background: rgba(0,0,0,.2);
    }
    .crw-review-grid { display: grid; gap: 12px; grid-template-columns: 1.2fr .8fr; }
    @media (max-width: 900px) { .crw-review-grid { grid-template-columns: 1fr; } }
    .crw-card {
        border: 1px solid rgba(255,255,255,.12); border-radius: 12px; padding: 14px;
        background: rgba(0,0,0,.25);
    }
    .crw-card h3 { margin: 0 0 10px; font-size: 13px; font-weight: 600; }
    .crw-checks { list-style: none; margin: 0; padding: 0; display: grid; gap: 8px; font-size: 12px; }
    .crw-checks li { display: flex; gap: 8px; align-items: flex-start; color: rgba(255,255,255,.8); }
    .crw-checks li::before { content: '✓'; color: #22c55e; font-weight: 700; }
    .crw-color-opt { display: inline-flex; align-items: center; gap: 8px; }
    .crw-dot { width: 10px; height: 10px; border-radius: 999px; display: inline-block; }
    .crw-toggle { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 12px; font-size: 12px; }
    .crw-toggle input { accent-color: var(--brand-primary, #FF6600); width: 36px; height: 18px; }
    .crw-stats {
        display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin: 14px 0;
    }
    .crw-stat {
        border: 1px solid rgba(255,255,255,.1); border-radius: 10px; padding: 12px;
        background: rgba(255,255,255,.03);
    }
    .crw-stat strong { display: block; font-size: 18px; }
    .crw-stat span { font-size: 11px; color: rgba(255,255,255,.5); }
</style>

<div
    class="crw mx-auto max-w-[1280px] px-3 pb-8 pt-4 sm:px-4"
    x-data="createRoleWizard(@js([
        'initial' => $old,
        'userPages' => $userPages,
        'userAbilities' => $userAbilities,
        'adminPages' => $adminPages,
        'abilityRequirements' => $abilityRequirements,
        'roleColors' => $roleColors ?? \App\Support\RolePortalCatalog::roleColors(),
        'baseRoles' => $baseRoles->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'portal' => $r->portal ?? 'user'])->values(),
        'storeUrl' => route('super-admin.roles.store'),
        'indexUrl' => route('super-admin.roles.index'),
    ]))"
    x-init="init()"
>
    @if (session('error'))
        <div class="mb-4 rounded-lg border border-rose-400/40 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-rose-400/40 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
            <ul class="list-disc pl-4">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <header class="crw-head">
        <div>
            <p class="crw-crumb"><a href="{{ route('super-admin.roles.index') }}">Roles &amp; Permissions</a> / Create role</p>
            <h1 class="crw-title" x-text="headerTitle"></h1>
            <p class="crw-sub" x-text="headerSub"></p>
        </div>
        <div class="crw-actions">
            <a href="{{ route('super-admin.roles.index') }}" class="crw-btn">Cancel</a>
            <button type="button" class="crw-btn" @click="saveDraft()">Save draft</button>
            <button type="button" class="crw-btn crw-btn-primary" @click="submitFromHeader()" x-text="step === 4 ? 'Create role' : 'Create role'"></button>
        </div>
    </header>

    <form method="POST" :action="storeUrl" @submit="onSubmit" id="create-role-form">
                @csrf
        <input type="hidden" name="slug" :value="form.slug">
        <input type="hidden" name="portal" :value="form.portal">
        <input type="hidden" name="color" :value="form.color">
        <input type="hidden" name="is_temporary" :value="form.is_temporary ? 1 : 0">
        <input type="hidden" name="expires_at" :value="form.is_temporary ? form.expires_at : ''">
        <input type="hidden" name="base_role_id" :value="form.base_role_id || ''">
        <input type="hidden" name="scope[mode]" :value="form.scope.mode">
        <input type="hidden" name="scope[include_future_projects]" :value="form.scope.include_future_projects ? 1 : 0">
        <template x-for="id in form.scope.project_ids" :key="'proj-' + id">
            <input type="hidden" name="scope[project_ids][]" :value="id">
        </template>
        <template x-for="id in form.scope.campaign_ids" :key="'camp-' + id">
            <input type="hidden" name="scope[campaign_ids][]" :value="id">
        </template>
        <template x-for="page in pagesForPortal" :key="'pa-' + page.key">
            <input type="hidden" :name="'page_access[' + page.key + ']'" :value="form.page_access[page.key] || 'none'">
        </template>
        <template x-for="abKey in form.abilities" :key="'hid-ab-' + abKey">
            <input type="hidden" name="abilities[]" :value="abKey">
        </template>

        <div class="crw-layout">
            {{-- Vertical stepper --}}
            <aside class="crw-stepper" aria-label="Create role steps">
                <template x-for="(s, i) in steps" :key="s.key">
                    <button type="button" class="crw-step" :class="{ 'is-active': step === i, 'is-done': i < step }" @click="goStep(i)">
                        <span class="crw-step-num" x-text="i < step ? '✓' : (i + 1)"></span>
                        <span>
                            <span class="crw-step-label" x-text="s.label"></span>
                            <span class="crw-step-desc" x-text="s.desc"></span>
                        </span>
                    </button>
                </template>
            </aside>

            {{-- Main panel --}}
            <section class="crw-panel">
                {{-- Step 1: Role details --}}
                <div x-show="step === 0" x-cloak>
                    <h2 class="crw-panel-title">Role details</h2>
                    <p class="crw-panel-lead">Define basic information for this role.</p>

                    <div class="crw-field">
                        <label class="crw-label" for="cr-name">Role name <span class="crw-count" x-text="(form.name || '').length + '/100'"></span></label>
                        <input id="cr-name" name="name" type="text" maxlength="100" x-model="form.name" @input="autoSlug" class="crw-input" placeholder="e.g. Campaign Analyst" required>
                        <p class="crw-hint">Choose a clear, descriptive name for this role.</p>
                    </div>

                    <div class="crw-field">
                        <label class="crw-label" for="cr-desc">Description <span class="crw-count" x-text="(form.description || '').length + '/250'"></span></label>
                        <textarea id="cr-desc" name="description" maxlength="250" x-model="form.description" class="crw-textarea" placeholder="Explain the purpose of this role"></textarea>
                        <p class="crw-hint">Explain the purpose of this role and what it will be used for.</p>
                    </div>

                    <div class="crw-grid-2">
                        <div class="crw-field">
                            <label class="crw-label" for="cr-base">Base role</label>
                            <select id="cr-base" class="crw-select" x-model="form.base_role_id" @change="applyBaseRole">
                                <option value="">Read-only</option>
                                <template x-for="r in baseRolesForPortal" :key="'base-' + r.id">
                                    <option :value="String(r.id)" x-text="r.name"></option>
                                </template>
                            </select>
                            <p class="crw-hint">Use a base role as a starting point for permissions.</p>
                        </div>
                        <div class="crw-field">
                            <label class="crw-label" for="cr-color">Role color</label>
                            <select id="cr-color" class="crw-select" x-model="form.color">
                                <template x-for="c in roleColors" :key="'color-' + c.key">
                                    <option :value="c.hex" x-text="c.label"></option>
                                </template>
                            </select>
                            <p class="crw-hint">
                                <span class="crw-color-opt">
                                    <span class="crw-dot" :style="'background:' + form.color"></span>
                                    Choose a color to help identify this role.
                                </span>
                            </p>
                        </div>
                    </div>

                    <div class="crw-field">
                        <p class="crw-label">Role type</p>
                        <div class="space-y-2">
                            <label class="crw-radio" :class="!form.is_temporary && 'is-on'">
                                <input type="radio" name="role_type_ui" value="standard" :checked="!form.is_temporary" @change="form.is_temporary = false">
                                <span>
                                    <strong>Standard role</strong>
                                    <span>This role will have no expiration date.</span>
                                </span>
                            </label>
                            <label class="crw-radio" :class="form.is_temporary && 'is-on'">
                                <input type="radio" name="role_type_ui" value="temporary" :checked="form.is_temporary" @change="form.is_temporary = true">
                                <span>
                                    <strong>Temporary role</strong>
                                    <span>This role will expire on a specific date.</span>
                                </span>
                            </label>
                        </div>
                        <div class="mt-3" x-show="form.is_temporary" x-cloak>
                            <label class="crw-label" for="cr-exp">Expiration date</label>
                            <input id="cr-exp" type="datetime-local" class="crw-input" x-model="form.expires_at">
                        </div>
                    </div>

                    <div class="crw-callout">
                        <span aria-hidden="true">⚠</span>
                        <span>Role permissions can be edited later by an authorized administrator.</span>
                    </div>
                </div>

                {{-- Step 2: Portal access --}}
                <div x-show="step === 1" x-cloak>
                    <h2 class="crw-panel-title">Select portal</h2>
                    <p class="crw-panel-lead">Choose which portal this role belongs to, then set its scope.</p>

                    <div class="crw-portal-cards mb-4">
                        <button type="button" class="crw-portal-card" :class="form.portal === 'user' && 'is-on'" @click="form.portal = 'user'; pruneAbilities()">
                            <div class="crw-portal-icon crw-portal-icon--user" aria-hidden="true">
                                <svg width="20" height="20" fill="none" stroke="#fff" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="1.8" d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8z"/></svg>
                            </div>
                            <strong class="block text-[14px]">User Portal</strong>
                            <span class="mt-1 block text-[11px] text-white/55">Workspace members and customers</span>
                        </button>
                        <button type="button" class="crw-portal-card" :class="form.portal === 'admin' && 'is-on'" @click="form.portal = 'admin'; pruneAbilities()">
                            <div class="crw-portal-icon crw-portal-icon--admin" aria-hidden="true">
                                <svg width="20" height="20" fill="none" stroke="#fff" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="1.8" d="M12 15.5A3.5 3.5 0 1012 8.5a3.5 3.5 0 000 7zM19.4 15a1.7 1.7 0 00.3 1.8l.1.1a2 2 0 11-2.8 2.8l-.1-.1a1.7 1.7 0 00-1.8-.3 1.7 1.7 0 00-1 1.5V21a2 2 0 11-4 0v-.1a1.7 1.7 0 00-1.1-1.5 1.7 1.7 0 00-1.8.3l-.1.1a2 2 0 11-2.8-2.8l.1-.1a1.7 1.7 0 00.3-1.8 1.7 1.7 0 00-1.5-1H3a2 2 0 110-4h.1a1.7 1.7 0 001.5-1 1.7 1.7 0 00-.3-1.8l-.1-.1a2 2 0 112.8-2.8l.1.1a1.7 1.7 0 001.8.3H9a1.7 1.7 0 001-1.5V3a2 2 0 114 0v.1a1.7 1.7 0 001 1.5 1.7 1.7 0 001.8-.3l.1-.1a2 2 0 112.8 2.8l-.1.1a1.7 1.7 0 00-.3 1.8V9c.3.6.9 1 1.6 1H21a2 2 0 110 4h-.1a1.7 1.7 0 00-1.5 1z"/></svg>
                            </div>
                            <strong class="block text-[14px]">Admin Portal</strong>
                            <span class="mt-1 block text-[11px] text-white/55">Clickronix platform administrators</span>
                        </button>
                    </div>

                    <div class="crw-callout mb-4">
                        <span aria-hidden="true">⚠</span>
                        <span>Portal roles are separate. User Portal roles cannot access Admin Portal pages. Admin Portal roles require platform administrator access.</span>
                    </div>

                    <p class="crw-label">Role scope</p>
                    <div class="crw-scope-seg" role="tablist">
                        <button type="button" :class="form.scope.mode === 'workspace' && 'is-on'" @click="form.scope.mode = 'workspace'">Entire workspace</button>
                        <button type="button" :class="form.scope.mode === 'project' && 'is-on'" @click="form.scope.mode = 'project'">Selected projects</button>
                        <button type="button" :class="form.scope.mode === 'campaign' && 'is-on'" @click="form.scope.mode = 'campaign'">Selected campaigns</button>
                    </div>

                    <div x-show="form.scope.mode === 'project'" x-cloak class="crw-field">
                        <label class="crw-label">Select projects</label>
                        <div class="flex gap-2">
                            <input type="text" class="crw-input" placeholder="Add project name" x-model="chipDraft" @keydown.enter.prevent="addChip('project')">
                            <button type="button" class="crw-btn" @click="addChip('project')">Add</button>
                        </div>
                        <div class="crw-chips">
                            <template x-for="(p, idx) in form.scope.project_ids" :key="'pchip-' + p + idx">
                                <span class="crw-chip"><span x-text="p"></span><button type="button" @click="removeChip('project', idx)" aria-label="Remove">×</button></span>
                            </template>
                        </div>
                    </div>

                    <div x-show="form.scope.mode === 'campaign'" x-cloak class="crw-field">
                        <label class="crw-label">Select campaigns</label>
                        <div class="flex gap-2">
                            <input type="text" class="crw-input" placeholder="Add campaign name" x-model="chipDraft" @keydown.enter.prevent="addChip('campaign')">
                            <button type="button" class="crw-btn" @click="addChip('campaign')">Add</button>
                        </div>
                        <div class="crw-chips">
                            <template x-for="(c, idx) in form.scope.campaign_ids" :key="'cchip-' + c + idx">
                                <span class="crw-chip"><span x-text="c"></span><button type="button" @click="removeChip('campaign', idx)" aria-label="Remove">×</button></span>
                            </template>
                        </div>
                    </div>

                    <label class="crw-toggle" x-show="form.scope.mode !== 'workspace'" x-cloak>
                        <span>Allow access to future projects</span>
                        <input type="checkbox" x-model="form.scope.include_future_projects">
                    </label>
                </div>

                {{-- Step 3: Page access --}}
                <div x-show="step === 2" x-cloak>
                    <h2 class="crw-panel-title">User Portal page access</h2>
                    <p class="crw-panel-lead" x-show="form.portal === 'user'">Select the pages this role can access in the User Portal.</p>
                    <p class="crw-panel-lead" x-show="form.portal === 'admin'" x-cloak>Select Admin Portal pages this role can access.</p>

                    <div class="crw-toolbar">
                        <input type="search" class="crw-input" placeholder="Search pages" x-model="pageSearch">
                        <select class="crw-select" x-model="pageGroup" x-show="form.portal === 'user'">
                            <option value="">All page groups</option>
                            <template x-for="g in pageGroups" :key="'grp-' + g">
                                <option :value="g" x-text="g"></option>
                            </template>
                        </select>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="crw-table">
                            <thead>
                                <tr>
                                    <th>Page</th>
                                    <th>No access</th>
                                    <th>View</th>
                                    <th>Full access</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="page in filteredPages" :key="'pg-' + page.key">
                                    <tr>
                                        <td>
                                            <div class="crw-page-cell">
                                                <span class="crw-page-ico" aria-hidden="true">
                                                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="1.8" d="M4 6h16M4 12h16M4 18h10"/></svg>
                                                </span>
                                                <span>
                                                    <span x-text="page.label"></span>
                                                    <span class="block text-[10px] font-normal text-white/40" x-text="page.group || ''"></span>
                                                </span>
                                            </div>
                                        </td>
                                        <td><label class="crw-access"><input type="radio" :name="'ui_page_' + page.key" value="none" :checked="(form.page_access[page.key] || 'none') === 'none'" @change="setPageAccess(page.key, 'none')"></label></td>
                                        <td><label class="crw-access"><input type="radio" :name="'ui_page_' + page.key" value="view" :checked="form.page_access[page.key] === 'view'" @change="setPageAccess(page.key, 'view')"></label></td>
                                        <td><label class="crw-access"><input type="radio" :name="'ui_page_' + page.key" value="full" :checked="form.page_access[page.key] === 'full'" @change="setPageAccess(page.key, 'full')"></label></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div class="crw-callout mt-4">
                        <span aria-hidden="true">⚠</span>
                        <span>Page access controls which navigation items appear. Abilities control what the member can do inside those pages.</span>
                    </div>
                </div>

                {{-- Step 4: Abilities --}}
                <div x-show="step === 3" x-cloak>
                    <h2 class="crw-panel-title">Role abilities</h2>
                    <p class="crw-panel-lead">Choose what this role can do inside its permitted pages.</p>

                    <div class="crw-toolbar" x-show="form.portal === 'user'">
                        <input type="search" class="crw-input" placeholder="Search abilities" x-model="abilitySearch">
                    </div>

                    <div x-show="form.portal === 'user'">
                        <template x-for="group in abilityGroups" :key="'abg-' + group">
                            <div class="crw-ability-group" x-show="abilitiesInGroup(group).length">
                                <h3 x-text="group"></h3>
                                <template x-for="ab in abilitiesInGroup(group)" :key="'ab-' + ab.key">
                                    <label class="crw-ability" :class="!abilityEnabled(ab.key) && 'is-off'">
                                        <input type="checkbox"
                                               :disabled="!abilityEnabled(ab.key)"
                                               :checked="form.abilities.includes(ab.key)"
                                               @change="toggleAbility(ab.key, $event.target.checked)">
                                        <span class="min-w-0 flex-1">
                                            <strong class="block text-[13px]" x-text="ab.label"></strong>
                                            <span class="block text-[11px] text-white/55" x-text="ab.definition"></span>
                                            <span class="crw-tag" x-text="ab.page_tag || ab.dependency"></span>
                                        </span>
                                    </label>
                                </template>
                            </div>
                        </template>
                        <div class="crw-callout">
                            <span aria-hidden="true">⚠</span>
                            <span>This User Portal role cannot manage billing, plans, Admin Portal users or platform settings.</span>
                        </div>
                    </div>

                    <div x-show="form.portal === 'admin'" x-cloak>
                        <p class="text-[12px] text-white/60">Admin Portal grants are controlled by page access on the previous step (View / Full).</p>
                    </div>
                </div>

                {{-- Step 5: Review --}}
                <div x-show="step === 4" x-cloak>
                    <h2 class="crw-panel-title">Review role</h2>
                    <p class="crw-panel-lead">Confirm portal, pages, abilities and scope before creating.</p>

                    <div class="crw-identity mb-4">
                        <div class="crw-avatar" :style="'background:' + form.color">
                            <svg width="22" height="22" fill="none" stroke="#fff" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="1.8" d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zM23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                        </div>
                        <div>
                            <strong x-text="form.name || 'Untitled role'"></strong>
                            <span class="ml-2 inline-block rounded-full bg-white/10 px-2 py-0.5 text-[10px]" x-text="form.portal === 'admin' ? 'Admin Portal' : 'User Portal'"></span>
                            <span class="mt-1 block text-[12px] text-white/55" x-text="form.description || 'No description'"></span>
                            <span class="mt-1 block text-[11px] text-white/40">
                                Base role: <span x-text="baseRoleLabel()"></span>
                                · Role color: <span class="crw-dot align-middle" :style="'background:' + form.color"></span>
                                <span x-text="colorLabel()"></span>
                            </span>
                                    </div>
                                </div>

                    <div class="crw-stats">
                        <div class="crw-stat"><strong x-text="grantedPageCount()"></strong><span>User Portal pages</span></div>
                        <div class="crw-stat"><strong x-text="form.portal === 'user' ? form.abilities.length : '—'"></strong><span>Abilities</span></div>
                        <div class="crw-stat"><strong x-text="scopeLabel()"></strong><span>Scope</span></div>
                        <div class="crw-stat"><strong x-text="scopeTargetsLabel()"></strong><span>Selected targets</span></div>
                    </div>

                    <div class="crw-review-grid">
                        <div class="space-y-3">
                            <div class="crw-card">
                                <h3>Page access</h3>
                                <ul class="space-y-1 text-[12px] text-white/75">
                                    <template x-for="page in pagesForPortal" :key="'rvp-' + page.key">
                                        <li x-show="(form.page_access[page.key] || 'none') !== 'none'">
                                            <span x-text="page.label"></span>
                                            — <span class="capitalize" x-text="form.page_access[page.key] === 'full' ? 'Full' : 'View'"></span>
                                        </li>
                                    </template>
                                </ul>
                                <p class="text-[11px] text-white/40" x-show="!hasAnyPageAccess()">No pages granted</p>
                            </div>
                            <div class="crw-card" x-show="form.portal === 'user'">
                                <h3>Abilities</h3>
                                <ul class="space-y-2 text-[12px] text-white/75">
                                    <template x-for="ab in selectedAbilityRows()" :key="'rva-' + ab.key">
                                        <li>
                                            <strong class="text-white" x-text="ab.label"></strong>
                                            <span class="block text-[11px] text-white/45" x-text="ab.definition"></span>
                                        </li>
                                    </template>
                                </ul>
                                <p class="text-[11px] text-white/40" x-show="!form.abilities.length">None selected</p>
                            </div>
                                </div>

                        <div class="crw-card">
                            <h3>Security review</h3>
                            <ul class="crw-checks">
                                <li x-text="form.portal === 'user' ? 'No Admin Portal access' : 'Admin Portal role — elevated access'"></li>
                                <li x-text="'Scope: ' + scopeLabel()"></li>
                                <li>Future projects require explicit opt-in</li>
                                <li>Creating this role will be logged in the audit history</li>
                            </ul>
                            <div class="crw-callout crw-callout--amber mt-3">
                                <span aria-hidden="true">⚠</span>
                                <span>Creating this role will be logged in the audit history.</span>
                            </div>
                            <label class="mt-3 flex items-start gap-2 text-[12px] text-white">
                                <input type="checkbox" name="confirm_review" value="1" class="mt-0.5" x-model="form.confirm_review" required>
                                <span>I confirm these permissions are correct.</span>
                            </label>
                            <button type="submit" class="crw-btn crw-btn-primary mt-3 w-full" :disabled="!form.confirm_review">Create role &gt;</button>
                        </div>
                    </div>

                    <div class="crw-callout crw-callout--green mt-4">
                        <span aria-hidden="true">✓</span>
                        <span>After creation, this role can be assigned from User Portal Settings &gt; Members.</span>
                    </div>
                </div>
            </section>

            {{-- Role summary --}}
            <aside class="crw-summary">
                <div class="crw-summary-head">
                    <h2>Role summary</h2>
                    <span class="crw-badge">Draft</span>
                </div>
                <div class="crw-identity">
                    <div class="crw-avatar" :style="'background:' + form.color">
                        <svg width="20" height="20" fill="none" stroke="#fff" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="1.8" d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8z"/></svg>
                    </div>
                    <div>
                        <strong x-text="form.name || 'Untitled role'"></strong>
                        <span x-text="form.portal === 'admin' ? 'Admin Portal' : 'User Portal'"></span>
                    </div>
                </div>
                <ul class="crw-meta">
                    <li><span>Portal</span><strong x-text="form.portal ? (form.portal === 'admin' ? 'Admin Portal' : 'User Portal') : 'Not selected'"></strong></li>
                    <li><span>Pages</span><strong x-text="grantedPageCount() ? (grantedPageCount() + ' pages') : 'Not selected'"></strong></li>
                    <li><span>Abilities</span><strong x-text="form.portal === 'user' ? (form.abilities.length ? (form.abilities.length + ' abilities') : 'Not selected') : 'Via pages'"></strong></li>
                    <li><span>Scope</span><strong x-text="scopeLabel()"></strong></li>
                </ul>
                <div class="crw-callout mt-3" x-show="step === 3 && form.portal === 'user'" x-cloak>
                    <span aria-hidden="true">⚠</span>
                    <span>Abilities require matching page access.</span>
                </div>
                <button type="button" class="crw-btn crw-btn-primary" x-show="step < 4" @click="next" x-text="continueLabel"></button>
                <button type="button" class="crw-btn crw-btn-primary" x-show="step === 4" x-cloak @click="submitFromHeader()" :disabled="!form.confirm_review">Create role &gt;</button>
            </aside>
                </div>
            </form>

    <div class="crw-foot">
        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-width="1.8" d="M12 11c1.7 0 3-1.3 3-3S13.7 5 12 5 9 6.3 9 8s1.3 3 3 3zm0 2c-2.7 0-8 1.3-8 4v2h16v-2c0-2.7-5.3-4-8-4z"/><rect x="5" y="11" width="14" height="10" rx="2" stroke-width="1.8"/></svg>
        <span x-text="footerTip"></span>
    </div>
</div>

<script>
function createRoleWizard(cfg) {
    const initialAccess = {};
    (cfg.userPages || []).forEach((p) => { initialAccess[p.key] = 'none'; });
    (cfg.adminPages || []).forEach((p) => { initialAccess[p.key] = 'none'; });
    Object.assign(initialAccess, cfg.initial?.page_access || {});

    return {
        step: 0,
        storeUrl: cfg.storeUrl,
        indexUrl: cfg.indexUrl,
        chipDraft: '',
        pageSearch: '',
        pageGroup: '',
        abilitySearch: '',
        _slugTouched: Boolean(cfg.initial?.slug),
        steps: [
            { key: 'details', label: 'Role details', desc: 'Define basic information.' },
            { key: 'portal', label: 'Portal access', desc: 'Choose portal.' },
            { key: 'pages', label: 'Page access', desc: 'Select pages.' },
            { key: 'abilities', label: 'Abilities', desc: 'Configure abilities.' },
            { key: 'review', label: 'Review', desc: 'Confirm and create.' },
        ],
        userPages: cfg.userPages || [],
        userAbilities: cfg.userAbilities || [],
        adminPages: cfg.adminPages || [],
        abilityRequirements: cfg.abilityRequirements || {},
        roleColors: cfg.roleColors || [],
        baseRoles: cfg.baseRoles || [],
        form: {
            name: cfg.initial?.name || '',
            slug: cfg.initial?.slug || '',
            description: cfg.initial?.description || '',
            portal: cfg.initial?.portal || 'user',
            color: cfg.initial?.color || '#7C3AED',
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
        get pageGroups() {
            return [...new Set(this.userPages.map((p) => p.group).filter(Boolean))];
        },
        get filteredPages() {
            const q = String(this.pageSearch || '').toLowerCase().trim();
            return this.pagesForPortal.filter((p) => {
                if (this.form.portal === 'user' && this.pageGroup && p.group !== this.pageGroup) return false;
                if (!q) return true;
                return String(p.label || '').toLowerCase().includes(q)
                    || String(p.group || '').toLowerCase().includes(q)
                    || String(p.view_means || p.definition || '').toLowerCase().includes(q);
            });
        },
        get abilityGroups() {
            return [...new Set(this.userAbilities.map((a) => a.group || 'Other'))];
        },
        get baseRolesForPortal() {
            return this.baseRoles.filter((r) => (r.portal || 'user') === this.form.portal);
        },
        get headerTitle() {
            return this.step === 4 ? 'Review role' : 'Create role';
        },
        get headerSub() {
            const map = [
                'Define the portal, pages, abilities and scope for this role.',
                'Choose which portal this role belongs to.',
                'Select the pages this role can access in the User Portal.',
                'Choose what this role can do inside its permitted pages.',
                'Confirm portal, pages, abilities and scope before creating.',
            ];
            return map[this.step] || map[0];
        },
        get continueLabel() {
            const next = this.steps[this.step + 1];
            return next ? ('Continue to ' + next.label.toLowerCase() + ' >') : 'Continue >';
        },
        get footerTip() {
            const tips = [
                'You can only create roles permitted by your current access.',
                'Portal selection determines which pages and abilities can be added next.',
                'Page access controls which navigation items appear.',
                'Abilities without matching page access stay disabled.',
                'Final confirmation is required before the role is created.',
            ];
            return tips[this.step] || tips[0];
        },
        autoSlug() {
            if (!this._slugTouched) {
                this.form.slug = String(this.form.name || '')
                    .toLowerCase()
                    .trim()
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/^-|-$/g, '');
            }
        },
        applyBaseRole() {},
        colorLabel() {
            const hit = this.roleColors.find((c) => c.hex === this.form.color);
            return hit ? hit.label : this.form.color;
        },
        baseRoleLabel() {
            if (!this.form.base_role_id) return 'Read-only';
            const hit = this.baseRoles.find((r) => String(r.id) === String(this.form.base_role_id));
            return hit ? hit.name : 'Custom';
        },
        scopeLabel() {
            if (this.form.scope.mode === 'project') return 'Selected projects';
            if (this.form.scope.mode === 'campaign') return 'Selected campaigns';
            return 'Entire workspace';
        },
        scopeTargetsLabel() {
            if (this.form.scope.mode === 'project') {
                return this.form.scope.project_ids.length
                    ? this.form.scope.project_ids.join(', ')
                    : 'None';
            }
            if (this.form.scope.mode === 'campaign') {
                return this.form.scope.campaign_ids.length
                    ? this.form.scope.campaign_ids.join(', ')
                    : 'None';
            }
            return 'All';
        },
        grantedPageCount() {
            return Object.values(this.form.page_access || {}).filter((l) => l === 'view' || l === 'full').length;
        },
        setPageAccess(key, level) {
            this.form.page_access[key] = level;
            this.pruneAbilities();
        },
        addChip(kind) {
            const value = String(this.chipDraft || '').trim();
            if (!value) return;
            const list = kind === 'campaign' ? this.form.scope.campaign_ids : this.form.scope.project_ids;
            if (!list.includes(value)) list.push(value);
            this.chipDraft = '';
        },
        removeChip(kind, idx) {
            if (kind === 'campaign') this.form.scope.campaign_ids.splice(idx, 1);
            else this.form.scope.project_ids.splice(idx, 1);
        },
        abilitiesInGroup(group) {
            const q = String(this.abilitySearch || '').toLowerCase().trim();
            return this.userAbilities.filter((a) => {
                if ((a.group || 'Other') !== group) return false;
                if (!q) return true;
                return String(a.label || '').toLowerCase().includes(q)
                    || String(a.definition || '').toLowerCase().includes(q)
                    || String(a.page_tag || '').toLowerCase().includes(q);
            });
        },
        selectedAbilityRows() {
            return this.userAbilities.filter((a) => this.form.abilities.includes(a.key));
        },
        goStep(i) {
            if (i >= 0 && i < this.steps.length) this.step = i;
        },
        next() {
            if (this.step === 0 && !String(this.form.name || '').trim()) {
                alert('Role name is required.');
                return;
            }
            if (this.step < 4) this.step += 1;
            if (this.step === 3) this.pruneAbilities();
        },
        prev() {
            if (this.step > 0) this.step -= 1;
        },
        hasAnyPageAccess() {
            return Object.values(this.form.page_access || {}).some((l) => l === 'view' || l === 'full');
        },
        abilityEnabled(key) {
            if (this.form.portal !== 'user') return false;
            if (!this.hasAnyPageAccess()) return false;
            const reqs = this.abilityRequirements[key] || [];
            const needsFullAbility = ['create_data', 'edit_data', 'delete_data', 'manage_domains', 'edit_domain_settings', 'configure_campaigns', 'manage_exclusions'].includes(key);
            if (needsFullAbility && !Object.values(this.form.page_access || {}).some((l) => l === 'full')) {
                return false;
            }
            if (!reqs.length) return true;
            const viewOk = ['view_data', 'export_data', 'view_campaigns', 'view_domains'].includes(key);
            return reqs.some((pageKey) => {
                const level = this.form.page_access[pageKey] || 'none';
                if (viewOk) return level === 'view' || level === 'full';
                return level === 'full';
            });
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
        saveDraft() {
            try {
                localStorage.setItem('promotix-create-role-draft', JSON.stringify(this.form));
                alert('Draft saved on this device.');
            } catch (e) {
                alert('Could not save draft in this browser.');
            }
        },
        submitFromHeader() {
            if (this.step !== 4) {
                this.step = 4;
                return;
            }
            if (!this.form.confirm_review) {
                alert('Confirm the review checkbox before creating the role.');
                return;
            }
            this.pruneAbilities();
            document.getElementById('create-role-form')?.requestSubmit();
        },
        onSubmit(e) {
            if (this.step !== 4 || !this.form.confirm_review) {
                e.preventDefault();
                this.step = 4;
                return;
            }
            this.pruneAbilities();
        },
        init() {
            try {
                const raw = localStorage.getItem('promotix-create-role-draft');
                if (raw && !this.form.name) {
                    const draft = JSON.parse(raw);
                    if (draft && typeof draft === 'object') {
                        Object.assign(this.form, draft, {
                            page_access: Object.assign({}, this.form.page_access, draft.page_access || {}),
                            scope: Object.assign({}, this.form.scope, draft.scope || {}),
                            confirm_review: false,
                        });
                    }
                }
            } catch (e) {}
        },
    };
}
</script>
@endsection
