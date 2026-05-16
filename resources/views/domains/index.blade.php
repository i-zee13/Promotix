@extends('layouts.admin')

@section('title', 'Site Management')

@section('content')
<div class="min-h-[calc(100vh-49px)] bg-[#0d0d0d]" x-data="siteManagementFigma()" @keydown.escape.window="closeAll()">
    <section class="mx-auto w-full px-[12px] pb-[32px] pt-[28px] sm:px-[18px] xl:px-[19px] xl:pt-[68px]">
        <div class="mb-[20px] flex flex-col gap-[14px] lg:flex-row lg:items-center lg:justify-between">
            <h1 class="text-[28px] font-semibold leading-none text-[#a9a9a9] sm:text-[36px]">Site Management</h1>

            <div class="figma-filter-bar flex h-[54px] w-full max-w-[370px] overflow-hidden rounded-[10px] border border-white/25 bg-[#d9d9d9] text-[10px] text-black">
                <label class="flex flex-1 flex-col justify-center border-r border-black/20 px-[12px]">
                    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Campaigns</span>
                    <select class="figma-filter-control h-[23px] rounded-[3px] border-0 bg-[#101010] px-[8px] py-0 text-[11px] text-[#8c8787] focus:ring-0">
                        <option>All campaigns</option>
                    </select>
                </label>
                <label class="flex w-[178px] flex-col justify-center px-[12px]">
                    <span class="mb-[3px] text-[8px] font-semibold uppercase text-black/55">Filter by path</span>
                    <input type="search" x-model="tableSearch" placeholder="Filter by path" class="figma-filter-control h-[23px] rounded-[3px] border-0 bg-[#101010] px-[8px] py-0 text-[10px] text-[#8c8787] placeholder:text-[#8c8787] focus:ring-0">
                </label>
            </div>
        </div>

        @if (session('status'))
            <div class="mb-[14px] rounded-[8px] border border-white/30 bg-[#6400B2]/70 px-[14px] py-[10px] text-[13px] text-white">{{ session('status') }}</div>
        @endif

        {{-- Purple action bar --}}
        <div class="mb-[14px] flex flex-col gap-[12px] rounded-[10px] border border-white/25 bg-[#6400B2] px-[16px] py-[14px] sm:flex-row sm:items-center sm:justify-between sm:px-[22px] sm:py-[16px]">
            <form method="GET" action="{{ route('domains.index') }}" class="flex min-w-0 flex-1 items-center gap-[10px]">
                <span class="hidden shrink-0 text-white sm:inline">
                    <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </span>
                <input type="search" name="q" value="{{ $search }}" placeholder="Search for IP Address" class="h-[34px] min-w-0 flex-1 rounded-[6px] border border-white/30 bg-white px-[12px] text-[13px] text-[#101010] placeholder:text-[#8c8787] focus:border-white focus:ring-0">
            </form>
            <div class="flex shrink-0 flex-wrap items-center gap-[10px]">
                <a href="{{ route('billing.index') }}" class="rounded-[6px] border border-white bg-[#0d0d0d] px-[16px] py-[8px] text-[12px] font-semibold text-white hover:bg-black">Upgrade plan</a>
                <button type="button" @click="openAdd()" :disabled="!canAdd" class="inline-flex items-center gap-[6px] rounded-[6px] bg-white px-[16px] py-[8px] text-[12px] font-semibold text-[#6400B2] disabled:cursor-not-allowed disabled:opacity-50">
                    <span class="text-[16px] leading-none">+</span> Add domain
                </button>
            </div>
        </div>

        <p class="mb-[10px] text-[11px] text-[#a9a9a9]">{{ $domainCount }} / {{ $domainLimitDisplay }} domains used</p>

        {{-- Domains table --}}
        <div class="overflow-hidden rounded-[10px] border border-white/20 bg-[#151515]">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] border-collapse text-left text-[12px]">
                    <thead>
                        <tr class="border-b border-white/15 bg-[#1a1a1a] text-[11px] font-semibold uppercase tracking-wide text-[#a9a9a9]">
                            <th class="px-[16px] py-[12px]">Domain</th>
                            <th class="px-[12px] py-[12px]">Tag Management</th>
                            <th class="px-[12px] py-[12px]">Paid Advertising</th>
                            <th class="px-[12px] py-[12px]">Bot Protection</th>
                            <th class="w-[48px] px-[12px] py-[12px]"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($domains as $d)
                            <tr
                                x-show="rowVisible(@js($d->hostname))"
                                class="border-b border-white/10 text-white last:border-b-0"
                                data-hostname="{{ $d->hostname }}"
                            >
                                <td class="px-[16px] py-[14px]">
                                    <p class="text-[13px] font-medium text-white">{{ $d->hostname }}</p>
                                    <p class="mt-[2px] text-[10px] text-[#a9a9a9]">Last seen: {{ $d->last_seen_at?->diffForHumans() ?? '—' }}</p>
                                </td>
                                <td class="px-[12px] py-[14px]">
                                    @if ($d->tag_connected)
                                        <span class="inline-flex items-center gap-[6px] rounded-full bg-[#e8d4f8] px-[12px] py-[4px] text-[11px] font-medium text-[#4a0088]">
                                            Connected
                                            <a href="{{ route('domains.setup', $d) }}" class="text-[#6400B2] hover:text-[#4a0088]" aria-label="Edit tag setup">
                                                <svg class="h-[12px] w-[12px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M15.232 5.232l3.536 3.536M9 13l6.536-6.536a2 2 0 112.828 2.828L11.828 15.828a2 2 0 01-2.828 0L9 16"/></svg>
                                            </a>
                                        </span>
                                    @else
                                        <a href="{{ route('domains.setup', $d) }}" class="inline-block rounded-[4px] bg-[#0d0d0d] px-[14px] py-[5px] text-[11px] font-medium text-white ring-1 ring-white/30 hover:bg-black">Setup</a>
                                    @endif
                                </td>
                                <td class="px-[12px] py-[14px]">
                                    @if ($d->paid_marketing_connected)
                                        <span class="inline-flex rounded-full bg-[#e8d4f8] px-[12px] py-[4px] text-[11px] font-medium text-[#4a0088]">Connected</span>
                                    @else
                                        <span class="text-[11px] text-[#a9a9a9]">—</span>
                                    @endif
                                </td>
                                <td class="px-[12px] py-[14px]">
                                    <div class="flex flex-wrap items-center gap-[8px]">
                                        @if ($d->bot_mitigation_connected)
                                            <span class="inline-flex rounded-full bg-[#e8d4f8] px-[12px] py-[4px] text-[11px] font-medium text-[#4a0088]">Connected</span>
                                        @else
                                            <a href="{{ route('domains.setup', $d) }}" class="inline-block rounded-[4px] bg-[#0d0d0d] px-[14px] py-[5px] text-[11px] font-medium text-white ring-1 ring-white/30 hover:bg-black">Setup</a>
                                        @endif
                                        <label class="inline-flex cursor-pointer items-center gap-[6px] text-[10px] text-[#d9d9d9]">
                                            <input type="checkbox" class="peer sr-only" @change="toggleMode({{ $d->id }}, $event.target.checked)" {{ $d->monitoring_only_mode ? 'checked' : '' }}>
                                            <span class="relative h-[14px] w-[28px] rounded-full bg-[#3a3a3a] after:absolute after:left-[2px] after:top-[2px] after:h-[10px] after:w-[10px] after:rounded-full after:bg-white after:transition peer-checked:bg-[#9a1aff] peer-checked:after:translate-x-[14px]"></span>
                                            Mode on
                                        </label>
                                    </div>
                                </td>
                                <td class="relative px-[12px] py-[14px]">
                                    <button type="button" @click="toggleMenu({{ $d->id }})" class="flex h-[28px] w-[28px] items-center justify-center rounded-[4px] text-[#a9a9a9] hover:bg-white/10 hover:text-white" aria-label="Domain actions">
                                        <svg class="h-[16px] w-[16px]" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a1.5 1.5 0 110-3 1.5 1.5 0 010 3zM10 11.5a1.5 1.5 0 110-3 1.5 1.5 0 010 3zM10 17a1.5 1.5 0 110-3 1.5 1.5 0 010 3z"/></svg>
                                    </button>
                                    <div
                                        x-show="openMenuId === {{ $d->id }}"
                                        x-cloak
                                        @click.outside="openMenuId = null"
                                        class="absolute right-[8px] top-[42px] z-30 min-w-[210px] overflow-hidden rounded-[8px] border border-white/20 bg-[#d9d9d9] py-[6px] text-[12px] text-[#101010] shadow-lg"
                                    >
                                        <button type="button" @click="openKeys(@js(['id' => $d->id, 'hostname' => $d->hostname]))" class="flex w-full items-center gap-[8px] px-[12px] py-[8px] hover:bg-white/60">
                                            <svg class="h-[14px] w-[14px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.8" d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3" stroke-width="1.8"/></svg>
                                            See plugin keys
                                        </button>
                                        <a href="{{ route('paid-marketing.detection-settings', ['domain_id' => $d->id]) }}" class="flex w-full items-center gap-[8px] px-[12px] py-[8px] hover:bg-white/60">
                                            <svg class="h-[14px] w-[14px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.8" d="M12 2v4m0 12v4M4.93 4.93l2.83 2.83m8.48 8.48l2.83 2.83M2 12h4m12 0h4M4.93 19.07l2.83-2.83m8.48-8.48l2.83-2.83"/></svg>
                                            Set tracking parameters
                                        </a>
                                        <button type="button" @click="openEdit(@js(['id' => $d->id, 'hostname' => $d->hostname, 'paid_marketing_connected' => $d->paid_marketing_connected, 'bot_mitigation_connected' => $d->bot_mitigation_connected]))" class="flex w-full items-center gap-[8px] px-[12px] py-[8px] hover:bg-white/60">
                                            <svg class="h-[14px] w-[14px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.8" d="M15.232 5.232l3.536 3.536M9 13l6.536-6.536a2 2 0 112.828 2.828L11.828 15.828a2 2 0 01-2.828 0L9 16"/></svg>
                                            Edit domain
                                        </button>
                                        <button type="button" @click="removeDomain({{ $d->id }})" class="flex w-full items-center gap-[8px] px-[12px] py-[8px] text-rose-700 hover:bg-white/60">
                                            <svg class="h-[14px] w-[14px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.8" d="M6 7h12M9 7V5a1 1 0 011-1h4a1 1 0 011 1v2m2 0v12a2 2 0 01-2 2H8a2 2 0 01-2-2V7h12z"/></svg>
                                            Remove
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-[16px] py-[40px] text-center text-[13px] text-[#a9a9a9]">
                                    No domains yet. Click <strong class="text-white">Add domain</strong> to connect your first site.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($domains->hasPages())
                <div class="flex flex-col items-center justify-between gap-[10px] border-t border-white/10 px-[16px] py-[12px] text-[11px] text-[#a9a9a9] sm:flex-row">
                    <span>Showing {{ $domains->firstItem() }}–{{ $domains->lastItem() }} of {{ $domains->total() }} results</span>
                    {{ $domains->withQueryString()->links() }}
                </div>
            @endif
        </div>

        {{-- Plan overview --}}
        @if ($planTiers->isNotEmpty())
            <div class="mt-[28px] grid gap-[16px] sm:grid-cols-3">
                @foreach ($planTiers as $tier)
                    @php
                        $active = $currentPlan && $currentPlan->id === $tier->id;
                        $usedPct = $domainLimitDisplay === '∞' ? 35 : min(100, (int) round(($domainCount / max(1, (int) $domainLimit)) * 100));
                    @endphp
                    <article class="rounded-[10px] border border-white/15 bg-[#151515] p-[20px] text-center">
                        <h3 class="mb-[14px] text-[16px] font-semibold text-white">{{ $tier->name }} plan</h3>
                        <div class="relative mx-auto h-[100px] w-[100px]">
                            <svg class="h-full w-full -rotate-90" viewBox="0 0 100 100">
                                <circle cx="50" cy="50" r="42" fill="none" stroke="#2a2a2a" stroke-width="10"/>
                                <circle cx="50" cy="50" r="42" fill="none" stroke="#6400B2" stroke-width="10" stroke-linecap="round" stroke-dasharray="{{ $active ? (264 * $usedPct / 100) : 80 }} 264"/>
                            </svg>
                            <span class="absolute inset-0 flex items-center justify-center text-[14px] font-medium text-[#a9a9a9]">{{ $active ? $usedPct . '%' : '—' }}</span>
                        </div>
                        @if ($active)
                            <p class="mt-[10px] text-[11px] text-[#a9a9a9]">Current plan</p>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    {{-- Add domain modal --}}
    <div class="fixed inset-0 z-[80] flex items-center justify-center bg-black/70 p-[16px]" x-show="modal === 'add'" x-cloak x-transition @click.self="modal = null">
        <div class="w-full max-w-[520px] overflow-hidden rounded-[12px] bg-[#6400B2] text-white shadow-2xl" @click.stop>
            <header class="border-b border-white/25 px-[24px] py-[18px]">
                <h2 class="text-[20px] font-semibold">Add domains</h2>
            </header>
            <div class="space-y-[16px] px-[24px] py-[20px]">
                @unless ($canAddDomain)
                    <div class="rounded-[8px] border border-[#9a1aff]/50 bg-[#e8d4f8]/90 px-[14px] py-[12px] text-[12px] text-[#4a0088]">
                        Domain limit reached. Enhance your capabilities by exploring our upgrade options.
                        <a href="{{ route('upgrade-plan') }}" class="font-semibold underline">Upgrade now.</a>
                    </div>
                @endunless
                <div>
                    <textarea x-model="addForm.hostname" rows="3" placeholder="Example: www.yourdomainnamehere.com" class="w-full rounded-[6px] border border-white/30 bg-[#4a0088]/60 px-[14px] py-[12px] text-[13px] text-white placeholder:text-white/50 focus:border-white focus:ring-0" :disabled="!canAdd"></textarea>
                    <p class="mt-[6px] text-[11px] text-white/70">One domain per line for bulk add.</p>
                </div>
                <div class="flex justify-end">
                    <button type="button" @click="submitAdd()" :disabled="!canAdd || addBusy" class="rounded-[6px] bg-white px-[20px] py-[8px] text-[13px] font-semibold text-[#6400B2] disabled:opacity-50">Continue</button>
                </div>
            </div>
            <footer class="flex justify-end gap-[10px] border-t border-white/25 px-[24px] py-[16px]">
                <button type="button" @click="modal = null" class="rounded-[6px] border border-white px-[18px] py-[8px] text-[13px] text-white">Close</button>
                <button type="button" @click="submitAdd()" :disabled="!canAdd || addBusy" class="rounded-[6px] bg-white px-[18px] py-[8px] text-[13px] font-semibold text-[#6400B2] disabled:opacity-50">Add Selected</button>
            </footer>
        </div>
    </div>

    {{-- Edit domain modal --}}
    <div class="fixed inset-0 z-[80] flex items-center justify-center bg-black/70 p-[16px]" x-show="modal === 'edit'" x-cloak x-transition @click.self="modal = null">
        <div class="w-full max-w-[440px] overflow-hidden rounded-[10px] bg-[#d9d9d9] text-[#101010] shadow-2xl" @click.stop>
            <header class="border-b border-black/10 px-[22px] py-[16px]">
                <h2 class="text-[18px] font-bold">Edit Domain</h2>
            </header>
            <div class="space-y-[14px] px-[22px] py-[18px]">
                <label class="block">
                    <span class="mb-[6px] block text-[12px] font-medium">Domain url</span>
                    <input type="text" x-model="editForm.hostname" class="h-[36px] w-full rounded-[4px] border border-black/20 bg-white px-[12px] text-[13px] focus:border-[#6400B2] focus:ring-[#6400B2]/30">
                </label>
                <label class="flex items-center gap-[10px] text-[13px]">
                    <input type="checkbox" x-model="editForm.paid_marketing_connected" class="rounded border-black/30 text-[#6400B2] focus:ring-[#6400B2]">
                    Paid Marketing Protection
                </label>
                <label class="flex items-center gap-[10px] text-[13px]">
                    <input type="checkbox" x-model="editForm.bot_mitigation_connected" class="rounded border-black/30 text-[#6400B2] focus:ring-[#6400B2]">
                    Bot Protection
                </label>
            </div>
            <footer class="flex justify-end gap-[12px] border-t border-black/10 px-[22px] py-[14px]">
                <button type="button" @click="modal = null" class="text-[13px] font-medium text-[#6400B2]">Cancel</button>
                <button type="button" @click="saveEdit()" :disabled="editBusy" class="rounded-[6px] bg-[#6400B2] px-[22px] py-[8px] text-[13px] font-semibold text-white disabled:opacity-50">Save</button>
            </footer>
        </div>
    </div>

    {{-- Plugin keys modal --}}
    <div class="fixed inset-0 z-[80] flex items-center justify-center bg-black/70 p-[16px]" x-show="modal === 'keys'" x-cloak x-transition @click.self="modal = null">
        <div class="w-full max-w-[560px] overflow-hidden rounded-[12px] bg-[#6400B2] text-white shadow-2xl" @click.stop>
            <header class="flex items-center justify-between border-b border-white/25 px-[24px] py-[18px]">
                <h2 class="text-[18px] font-semibold">Finish Setup <span class="text-[13px] font-normal text-white/80">(Required For WordPress Domains)</span></h2>
                <button type="button" @click="copyAllKeys()" class="flex items-center gap-[6px] text-[12px] text-white/90 hover:text-white">
                    <svg class="h-[14px] w-[14px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.8" d="M8 8h8v8H8zM4 4h8v2H6v6H4z"/></svg>
                    Copy all
                </button>
            </header>
            <div class="space-y-[14px] px-[24px] py-[20px]">
                <p class="text-[12px] text-white/85" x-text="'Installation keys for ' + (keysForm.hostname || 'domain')"></p>
                <template x-for="row in keyRows" :key="row.label">
                    <div class="flex flex-col gap-[6px] sm:flex-row sm:items-center sm:gap-[12px]">
                        <span class="w-[130px] shrink-0 text-[12px] font-medium" x-text="row.label"></span>
                        <div class="min-w-0 flex-1 rounded-[4px] border border-dashed border-white/70 bg-[#4a0088]/50 px-[12px] py-[8px] font-mono text-[11px] break-all" x-text="row.value || '…'"></div>
                        <button type="button" @click="copyText(row.value)" class="flex shrink-0 items-center gap-[4px] text-[11px] text-white/90 hover:text-white">
                            <svg class="h-[13px] w-[13px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.8" d="M8 8h8v8H8zM4 4h8v2H6v6H4z"/></svg>
                            Copy
                        </button>
                    </div>
                </template>
                <a :href="keysSetupUrl" class="inline-block text-[12px] text-white underline">Open full tracking setup →</a>
            </div>
            <footer class="flex justify-end border-t border-white/25 px-[24px] py-[14px]">
                <button type="button" @click="modal = null" class="rounded-[6px] bg-white px-[22px] py-[8px] text-[13px] font-semibold text-[#6400B2]">Done</button>
            </footer>
        </div>
    </div>

    <div class="fixed bottom-4 right-4 z-[90] rounded-[8px] bg-[#6400B2] px-[14px] py-[10px] text-[12px] text-white shadow-lg" x-show="toast" x-cloak x-text="toast" x-transition></div>
</div>

<script>
function siteManagementFigma() {
    return {
        modal: null,
        openMenuId: null,
        tableSearch: '',
        toast: '',
        canAdd: @json($canAddDomain),
        addBusy: false,
        editBusy: false,
        addForm: { hostname: '' },
        editForm: { id: null, hostname: '', paid_marketing_connected: false, bot_mitigation_connected: false },
        keysForm: { id: null, hostname: '' },
        keyRows: [],
        keysSetupUrl: '#',
        csrf: document.querySelector('meta[name=csrf-token]')?.content || '',
        rowVisible(hostname) {
            const q = (this.tableSearch || '').trim().toLowerCase();
            if (!q) return true;
            return (hostname || '').toLowerCase().includes(q);
        },
        closeAll() {
            this.modal = null;
            this.openMenuId = null;
        },
        toggleMenu(id) {
            this.openMenuId = this.openMenuId === id ? null : id;
        },
        openAdd() {
            this.addForm.hostname = '';
            this.modal = 'add';
            this.openMenuId = null;
        },
        openEdit(row) {
            this.editForm = { ...row };
            this.modal = 'edit';
            this.openMenuId = null;
        },
        async openKeys(row) {
            this.keysForm = row;
            this.keysSetupUrl = `/domains/${row.id}/setup`;
            this.modal = 'keys';
            this.openMenuId = null;
            this.keyRows = [
                { label: 'Domain Key', value: '…' },
                { label: 'Secret key', value: '…' },
                { label: 'Authentication Key', value: '…' },
            ];
            try {
                const res = await fetch(`/domains/${row.id}/api-key`, { headers: { Accept: 'application/json' } });
                const data = await res.json();
                this.keyRows = [
                    { label: 'Domain Key', value: data.domain_key },
                    { label: 'Secret key', value: data.secret_key },
                    { label: 'Authentication Key', value: data.authentication_key },
                ];
            } catch (_) {}
        },
        showToast(msg) {
            this.toast = msg;
            setTimeout(() => { this.toast = ''; }, 2200);
        },
        copyText(text) {
            if (!text) return;
            navigator.clipboard?.writeText(text);
            this.showToast('Copied');
        },
        copyAllKeys() {
            const blob = this.keyRows.map(r => `${r.label}: ${r.value}`).join('\n');
            this.copyText(blob);
        },
        async submitAdd() {
            const lines = (this.addForm.hostname || '').split('\n').map(v => v.trim()).filter(Boolean);
            if (!lines.length) return;
            this.addBusy = true;
            try {
                if (lines.length === 1) {
                    const res = await fetch('{{ route('domains.store') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.csrf,
                            Accept: 'application/json',
                        },
                        body: JSON.stringify({ hostname: lines[0] }),
                    });
                    if (!res.ok) {
                        const err = await res.json().catch(() => ({}));
                        alert(err.message || 'Could not add domain.');
                        return;
                    }
                } else {
                    const res = await fetch('/domains/bulk-add', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.csrf,
                            Accept: 'application/json',
                        },
                        body: JSON.stringify({ hostnames: lines }),
                    });
                    const data = await res.json();
                    if (!(data.added || []).length && (data.skipped || []).length) {
                        alert('No domains were added. Check duplicates or plan limit.');
                        return;
                    }
                }
                window.location.reload();
            } finally {
                this.addBusy = false;
            }
        },
        async saveEdit() {
            if (!this.editForm.id) return;
            this.editBusy = true;
            try {
                const res = await fetch(`/domains/${this.editForm.id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({
                        hostname: this.editForm.hostname,
                        paid_marketing_connected: this.editForm.paid_marketing_connected,
                        bot_mitigation_connected: this.editForm.bot_mitigation_connected,
                    }),
                });
                if (res.ok) window.location.reload();
                else {
                    const err = await res.json().catch(() => ({}));
                    alert(err.message || 'Could not save domain.');
                }
            } finally {
                this.editBusy = false;
            }
        },
        async removeDomain(id) {
            if (!confirm('Remove this domain?')) return;
            const res = await fetch(`/domains/${id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': this.csrf, Accept: 'application/json' },
            });
            if (res.ok) window.location.reload();
        },
        async toggleMode(id, on) {
            await fetch(`/domains/${id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrf,
                    Accept: 'application/json',
                },
                body: JSON.stringify({ monitoring_only_mode: on }),
            });
        },
    };
}
</script>
@endsection
