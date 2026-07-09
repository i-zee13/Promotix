@extends('layouts.super-admin')

@section('title', 'Security & Logs')

@section('content')
<x-super-admin.page title="Security & Logs">
    <div class="figma-sa-subs"
        x-data="{
            csrf: '{{ csrf_token() }}',
            urls: {
                block: '{{ route('super-admin.security.block-ip') }}',
                unblock: '{{ route('super-admin.security.unblock-ip') }}',
                flag: '{{ url('super-admin/security') }}',
            },
            toast: '',
            notify(msg) { this.toast = msg; setTimeout(() => this.toast = '', 3000); },
            async post(url, body) {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf },
                    body: JSON.stringify(body || {}),
                });
                const data = await res.json().catch(() => ({}));
                this.notify(data.message || 'Done.');
                if (res.ok) setTimeout(() => window.location.reload(), 700);
            },
            copyLog(row) { navigator.clipboard.writeText(JSON.stringify(row, null, 2)); this.notify('Log data copied.'); },
        }">
        <template x-if="toast">
            <div class="figma-sa-msg border-emerald-500/30 bg-emerald-500/10 text-emerald-200" x-text="toast"></div>
        </template>

        <form method="GET" action="{{ route('super-admin.security.index') }}" class="figma-sa-subs-filters" id="security-filter-form">
            <input type="hidden" name="type" id="filter-security-type" value="{{ request('type') }}">
            <input type="hidden" name="result" id="filter-security-result" value="{{ request('result') }}">

            <label class="figma-sa-subs-search">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Search logs..." autocomplete="off">
            </label>

            <x-super-admin.dashboard-dropdown align="left">
                <x-slot:trigger>
                    <button type="button" class="figma-sa-subs-filter-chip">
                        <span>{{ request('type') ?: 'All Types' }}</span>
                        <span class="figma-sa-subs-chip-chevron">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </span>
                    </button>
                </x-slot:trigger>
                <button type="button" class="figma-sa-users-action-item" onclick="document.getElementById('filter-security-type').value=''; document.getElementById('security-filter-form').submit();">All Types</button>
                <button type="button" class="figma-sa-users-action-item" onclick="document.getElementById('filter-security-type').value='Login'; document.getElementById('security-filter-form').submit();">Login</button>
                <button type="button" class="figma-sa-users-action-item" onclick="document.getElementById('filter-security-type').value='Detection'; document.getElementById('security-filter-form').submit();">Detection</button>
            </x-super-admin.dashboard-dropdown>

            <x-super-admin.dashboard-dropdown align="left">
                <x-slot:trigger>
                    <button type="button" class="figma-sa-subs-filter-chip figma-sa-subs-filter-chip--wide">
                        <span>{{ request('result') ?: 'All Results' }}</span>
                        <span class="figma-sa-subs-chip-chevron">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </span>
                    </button>
                </x-slot:trigger>
                <button type="button" class="figma-sa-users-action-item" onclick="document.getElementById('filter-security-result').value=''; document.getElementById('security-filter-form').submit();">All Results</button>
                <button type="button" class="figma-sa-users-action-item" onclick="document.getElementById('filter-security-result').value='Successful'; document.getElementById('security-filter-form').submit();">Successful</button>
                <button type="button" class="figma-sa-users-action-item" onclick="document.getElementById('filter-security-result').value='Suspicious'; document.getElementById('security-filter-form').submit();">Suspicious</button>
                <button type="button" class="figma-sa-users-action-item" onclick="document.getElementById('filter-security-result').value='Banned'; document.getElementById('security-filter-form').submit();">Banned</button>
            </x-super-admin.dashboard-dropdown>

            <label class="figma-sa-subs-filter-chip !cursor-pointer">
                <input type="date" name="date" value="{{ request('date') }}" onchange="this.form.submit()" class="w-full border-0 bg-transparent p-0 text-inherit outline-none" style="color-scheme:dark;">
            </label>

            <div class="figma-sa-subs-actions">
                <a href="{{ route('super-admin.security.index') }}" class="figma-sa-subs-export-btn">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M8 17h8M12 4v9m0 0l-3-3m3 3l3-3M5 19h14a1 1 0 001-1v-4"/></svg>
                    Export
                </a>
            </div>
        </form>

        <div class="figma-sa-subs-panel">
            <div class="figma-sa-subs-table-scroll">
                <table class="figma-sa-subs-table">
                    <thead>
                        <tr>
                            <th class="figma-sa-subs-th-check"><input type="checkbox" class="figma-sa-subs-checkbox" aria-label="Select all"></th>
                            <th>Type</th>
                            <th>User/IP</th>
                            <th>Status</th>
                            <th>Details / Location</th>
                            <th>Time</th>
                            <th class="figma-sa-subs-th-action">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            @php
                                $statusClass = match ($row['variant']) {
                                    'banned' => 'is-cancelled',
                                    'suspicious' => 'is-past_due',
                                    default => 'is-active',
                                };
                                $iconClass = match ($row['variant']) {
                                    'banned' => 'is-banned',
                                    'suspicious' => 'is-suspicious',
                                    default => '',
                                };
                            @endphp
                            <tr class="figma-sa-subs-row">
                                <td class="figma-sa-subs-td-check">
                                    <input type="checkbox" class="figma-sa-subs-checkbox" aria-label="Select row">
                                </td>
                                <td>
                                    <div class="flex items-center gap-[10px]">
                                        <span class="figma-sa-security-icon {{ $iconClass }}" aria-hidden="true">
                                            @if ($row['icon'] === 'check')
                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            @elseif ($row['icon'] === 'ban')
                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.36 6.64a9 9 0 11-12.73 0M12 8v4m0 4h.01"/></svg>
                                            @elseif ($row['icon'] === 'warning')
                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4m0 4h.01M10.29 3.86l-8.18 14.18A1.5 1.5 0 003.5 20.5h17a1.5 1.5 0 001.39-2.46L13.7 3.86a1.5 1.5 0 00-2.42 0z"/></svg>
                                            @else
                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                                            @endif
                                        </span>
                                        <span class="figma-sa-subs-plan-tier">{{ $row['type'] }}</span>
                                    </div>
                                </td>
                                <td>
                                    @if ($row['user_name'])
                                        <span class="figma-sa-subs-plan-tier">{{ $row['user_name'] }}</span>
                                        <span class="figma-sa-subs-plan-detail">{{ $row['user_email'] }}</span>
                                    @else
                                        <span class="figma-sa-subs-plan-tier">{{ $row['ip'] }}</span>
                                    @endif
                                </td>
                                <td><span class="figma-sa-subs-status-pill {{ $statusClass }}">{{ $row['status'] }}</span></td>
                                <td>
                                    <span class="figma-sa-subs-billing">{{ $row['details'] }}</span>
                                    <span class="figma-sa-security-country">
                                        <span class="figma-sa-security-flag" aria-hidden="true"></span>
                                        {{ $row['country'] ?? 'Unknown' }}
                                    </span>
                                </td>
                                <td><span class="figma-sa-subs-date">{{ $row['time']?->diffForHumans() ?? '—' }}</span></td>
                                <td class="figma-sa-subs-td-action">
                                    <x-super-admin.dashboard-dropdown align="right">
                                        <x-slot:trigger>
                                            <button type="button" class="figma-sa-subs-kebab" aria-label="Row actions">
                                                <svg fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zm0 4a2 2 0 110-4 2 2 0 010 4zm0 4a2 2 0 110-4 2 2 0 010 4z"/></svg>
                                            </button>
                                        </x-slot:trigger>
                                        <button type="button" class="figma-sa-users-action-item w-full text-left" @click="copyLog(@js($row))">Copy Log Data</button>
                                        @if ($row['type'] === 'Detection' && $row['variant'] !== 'suspicious')
                                            <button type="button" class="figma-sa-users-action-item w-full text-left" @click="post(urls.flag + '/{{ $row['id'] }}/flag')">Flag Suspicious</button>
                                        @endif
                                        @if ($row['blocked'])
                                            <button type="button" class="figma-sa-users-action-item w-full text-left" @click="post(urls.unblock, { ip: '{{ $row['ip'] }}' })">Allow IP</button>
                                        @else
                                            <button type="button" class="figma-sa-users-action-item figma-sa-users-action-item--danger w-full text-left" @click="post(urls.block, { ip: '{{ $row['ip'] }}' })">Block IP</button>
                                        @endif
                                    </x-super-admin.dashboard-dropdown>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="figma-sa-subs-empty">No security events yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="figma-sa-subs-pagination">
                <p class="figma-sa-subs-pagination-meta">
                    @if ($rows->total())
                        Showing {{ $rows->firstItem() }}–{{ $rows->lastItem() }} of {{ $rows->total() }}
                    @else
                        Showing 0 of 0
                    @endif
                </p>
                <div class="figma-sa-subs-pagination-controls">
                    @if ($rows->hasPages())
                        <div class="figma-sa-subs-page-btns">
                            @if ($rows->onFirstPage())
                                <span class="figma-sa-subs-page-btn figma-sa-subs-page-btn--disabled" aria-hidden="true">&lt;</span>
                            @else
                                <a href="{{ $rows->previousPageUrl() }}" class="figma-sa-subs-page-btn" aria-label="Previous page">&lt;</a>
                            @endif
                            <span class="figma-sa-subs-page-btn figma-sa-subs-page-btn--current">{{ $rows->currentPage() }}</span>
                            @if ($rows->hasMorePages())
                                <a href="{{ $rows->nextPageUrl() }}" class="figma-sa-subs-page-btn" aria-label="Next page">&gt;</a>
                            @else
                                <span class="figma-sa-subs-page-btn figma-sa-subs-page-btn--disabled" aria-hidden="true">&gt;</span>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-super-admin.page>
@endsection
