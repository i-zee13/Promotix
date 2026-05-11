@extends('layouts.super-admin')

@section('title', 'Domains & Trackers')
@section('subtitle', 'All client domains using the shared platform')

@section('content')
    <x-ui.card class="!p-0 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="brand-table min-w-[850px]">
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
                                        in_array($s, ['active','live','enabled']) => 'brand-pill-success',
                                        in_array($s, ['paused','disabled']) => 'brand-pill-warning',
                                        in_array($s, ['blocked','suspended']) => 'brand-pill-danger',
                                        default => 'brand-pill-neutral',
                                    };
                                @endphp
                                <span class="brand-pill {{ $cls }}">{{ ucfirst($domain->status) }}</span>
                            </td>
                            <td>{{ $domain->last_seen_at?->diffForHumans() ?? '—' }}</td>
                            <td>
                                @if ($domain->tag_connected)
                                    <span class="brand-pill brand-pill-success">Tracking</span>
                                @else
                                    <span class="brand-pill brand-pill-warning">Pending</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-12 text-center text-night-300">No domains yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-night-700/60 px-4 py-3">{{ $domains->links() }}</div>
    </x-ui.card>
@endsection
