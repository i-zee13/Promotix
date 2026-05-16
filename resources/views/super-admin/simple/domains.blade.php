@extends('layouts.super-admin')

@section('title', 'Domains & Trackers')
@section('content')
<x-super-admin.page title="Domains & Trackers">
    <x-super-admin.card class="!p-0 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="figma-sa-table min-w-[850px]">
                <thead>
                    <tr>
                        <th>Domain</th>
                        <th>Owner</th>
                        <th>Status</th>
                        <th>Last Seen</th>
                        <th>Connected</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($domains as $domain)
                        <tr>
                            <td class="font-semibold">{{ $domain->hostname }}</td>
                            <td>{{ $domain->user?->email ?? '—' }}</td>
                            <td>
                                @php
                                    $s = strtolower($domain->status ?? '');
                                    $cls = match (true) {
                                        in_array($s, ['active','live','enabled']) => 'figma-sa-pill-success',
                                        in_array($s, ['paused','disabled']) => 'figma-sa-pill-warning',
                                        in_array($s, ['blocked','suspended']) => 'figma-sa-pill-danger',
                                        default => 'figma-sa-pill-neutral',
                                    };
                                @endphp
                                <span class="figma-sa-pill {{ $cls }}">{{ ucfirst($domain->status) }}</span>
                            </td>
                            <td>{{ $domain->last_seen_at?->diffForHumans() ?? '—' }}</td>
                            <td>
                                @if ($domain->tag_connected)
                                    <span class="figma-sa-pill figma-sa-pill-success">Tracking</span>
                                @else
                                    <span class="figma-sa-pill figma-sa-pill-warning">Pending</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-12 text-center text-[#a9a9a9]">No domains yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="figma-sa-pagination px-4 py-3">{{ $domains->links() }}</div>
    </x-super-admin.card>
</x-super-admin.page>
@endsection
