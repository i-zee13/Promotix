@extends('layouts.super-admin')

@section('title', 'Security & Logs')
@section('content')
<x-super-admin.page title="Security & Logs">
    <div class="space-y-[14px]">
        <form method="GET" class="flex flex-wrap items-center gap-[8px]">
            <label class="figma-sa-dash-search !min-w-[220px]">
                <svg class="h-[18px] w-[18px] shrink-0 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5-5m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Search logs..." class="figma-sa-dash-search-input">
            </label>
            <select name="type" onchange="this.form.submit()" class="figma-select h-[34px] !text-[16px]">
                <option value="">All Types</option>
                <option value="Login" @selected(request('type') === 'Login')>Login</option>
                <option value="Detection" @selected(request('type') === 'Detection')>Detection</option>
            </select>
            <select name="result" onchange="this.form.submit()" class="figma-select h-[34px] !text-[16px]">
                <option value="">All Results</option>
                <option value="Successful" @selected(request('result') === 'Successful')>Successful</option>
                <option value="Suspicious" @selected(request('result') === 'Suspicious')>Suspicious</option>
                <option value="Banned" @selected(request('result') === 'Banned')>Banned</option>
            </select>
            <button type="submit" class="figma-sa-btn figma-sa-btn-outline !px-4 !py-2 text-[13px]">Filter</button>

            <a href="{{ route('super-admin.security.index') }}" class="ml-auto inline-flex h-[43px] items-center gap-[6px] rounded-[6px] bg-[#6706b3] px-[16px] text-[16px] font-medium text-white hover:bg-[#7a1acc]">
                <svg class="h-[16px] w-[16px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                Export
            </a>
        </form>

        <div class="figma-sa-subs-panel overflow-hidden rounded-[6px] bg-[#6400b3]">
            <div class="overflow-x-auto">
                <table class="figma-sa-subs-table min-w-[960px] w-full">
                    <thead>
                        <tr>
                            <th class="w-[48px]"></th>
                            <th>Type</th>
                            <th>User/IP</th>
                            <th>Details</th>
                            <th>IP Address/Location</th>
                            <th>Time</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $i => $row)
                            @php
                                $statusClass = match ($row['variant']) {
                                    'banned' => 'is-cancelled',
                                    'suspicious' => 'is-past_due',
                                    default => 'is-active',
                                };
                                $detailColor = match (true) {
                                    $row['variant'] === 'banned' => 'text-[#ff8686]',
                                    $row['variant'] === 'suspicious' => 'text-[#ba4646]',
                                    default => 'text-white',
                                };
                            @endphp
                            <tr @class(['figma-sa-subs-row', 'is-alt' => $i % 2 === 1])>
                                <td><input type="checkbox" class="figma-sa-checkbox rounded" aria-label="Select row"></td>
                                <td class="text-[16px] font-medium text-white">{{ $row['type'] }}</td>
                                <td>
                                    @if ($row['user_name'])
                                        <p class="truncate text-[16px] font-medium text-white">{{ $row['user_name'] }}</p>
                                        <p class="truncate text-[13px] font-medium text-white/80">{{ $row['user_email'] }}</p>
                                    @else
                                        <p class="font-mono text-[13px] text-white/80">{{ $row['ip'] }}</p>
                                    @endif
                                </td>
                                <td class="text-[16px] font-medium {{ $detailColor }}">{{ $row['details'] }}</td>
                                <td class="font-mono text-[14px] font-medium text-white">{{ $row['ip'] }}</td>
                                <td class="text-[14px] font-medium text-white">{{ $row['time']?->diffForHumans() ?? '—' }}</td>
                                <td><span class="figma-sa-subs-status {{ $statusClass }}">{{ $row['status'] }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-12 text-center text-white/70">No security events yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="figma-sa-subs-pagination flex flex-wrap items-center justify-between gap-[10px] px-[24px] py-[16px]">
                <p class="text-[16px] font-medium text-white/90">Showing {{ $rows->firstItem() ?? 0 }}-{{ $rows->lastItem() ?? 0 }} of {{ $rows->total() }}</p>
                <div>{{ $rows->onEachSide(1)->links() }}</div>
            </div>
        </div>
    </div>
</x-super-admin.page>
@endsection
