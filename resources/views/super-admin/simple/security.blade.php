@extends('layouts.super-admin')

@section('title', 'Security & Logs')
@section('content')
<x-super-admin.page title="Security & Logs">
    <div class="space-y-5">
        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <x-super-admin.kpi label="Blocked IPs" :value="number_format($blockedIps)" />
            <x-super-admin.kpi label="Detections (recent)" :value="number_format($recentDetections->count())" />
            <x-super-admin.kpi label="Unique threats" :value="number_format($recentDetections->pluck('threat_group')->filter()->unique()->count())" />
        </section>

        <x-super-admin.card class="!p-0 overflow-hidden">
            <div class="px-6 pt-6 pb-3 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-semibold text-white">Recent Detection Events</h2>
                    <p class="mt-1 text-sm text-[#a9a9a9]">Most recent suspicious or blocked traffic across all tenants.</p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="figma-sa-table min-w-[760px]">
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
                                    <span class="figma-sa-pill figma-sa-pill-warning">{{ $row->threat_group }}</span>
                                </td>
                                <td>
                                    @if (strtolower($row->action_taken) === 'block')
                                        <span class="figma-sa-pill figma-sa-pill-danger">{{ $row->action_taken }}</span>
                                    @else
                                        <span class="figma-sa-pill figma-sa-pill-neutral">{{ $row->action_taken }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="font-semibold">{{ $row->threat_score }}</span>
                                </td>
                                <td class="text-[#a9a9a9]">{{ $row->detected_at }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-12 text-center text-[#a9a9a9]">No detection logs yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-super-admin.card>
    </div>
</x-super-admin.page>
@endsection
