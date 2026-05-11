@extends('layouts.super-admin')

@section('title', 'Security & Logs')
@section('subtitle', 'Blocked IPs and recent detection events')

@section('content')
    <div class="space-y-5">
        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <x-ui.kpi-card label="Blocked IPs" :value="number_format($blockedIps)" />
            <x-ui.kpi-card label="Detections (recent)" :value="number_format($recentDetections->count())" />
            <x-ui.kpi-card label="Unique threats" :value="number_format($recentDetections->pluck('threat_group')->filter()->unique()->count())" />
        </section>

        <x-ui.card class="!p-0 overflow-hidden">
            <div class="px-6 pt-6 pb-3 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-semibold text-night-100">Recent Detection Events</h2>
                    <p class="mt-1 text-sm text-night-300">Most recent suspicious or blocked traffic across all tenants.</p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="brand-table min-w-[760px]">
                    <thead>
                        <tr>
                            <th>IP</th>
                            <th>Threat</th>
                            <th>Action</th>
                            <th>Score</th>
                            <th>Detected</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentDetections as $row)
                            <tr>
                                <td class="font-mono text-sm">{{ $row->ip }}</td>
                                <td>
                                    <span class="brand-pill brand-pill-warning">{{ $row->threat_group }}</span>
                                </td>
                                <td>
                                    @if (strtolower($row->action_taken) === 'block')
                                        <span class="brand-pill brand-pill-danger">{{ $row->action_taken }}</span>
                                    @else
                                        <span class="brand-pill brand-pill-neutral">{{ $row->action_taken }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="font-semibold">{{ $row->threat_score }}</span>
                                </td>
                                <td class="text-night-300">{{ $row->detected_at }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-12 text-center text-night-300">No detection logs yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui.card>
    </div>
@endsection
