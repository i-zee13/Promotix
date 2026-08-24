@extends('layouts.super-admin')

@section('title', 'Cross-domain intelligence')

@section('content')
<x-super-admin.page title="Cross-domain intelligence" subtitle="IPs seen on multiple domains. Score = evidence only — not an auto-block trigger.">
    @include('partials.super-admin.flash')

    <div class="mb-4">
        <a href="{{ route('super-admin.traffic.index') }}" class="text-[13px] text-[#FFB380] hover:underline">← Back to Traffic &amp; Bot Logs</a>
    </div>

    <div class="figma-sa-subs-panel">
        <div class="figma-sa-subs-table-scroll">
            <table class="figma-sa-subs-table">
                <thead>
                    <tr>
                        <th>IP</th>
                        <th>Domain count</th>
                        <th>Domains</th>
                        <th>Hits (30d)</th>
                        <th>Evidence score</th>
                        <th>Max bot score</th>
                        <th>Auto-block</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr class="figma-sa-subs-row">
                            <td><span class="figma-sa-subs-plan-tier">{{ $row['ip'] }}</span></td>
                            <td>{{ $row['domain_count'] }}</td>
                            <td>
                                <span class="text-[12px] text-white/75">{{ implode(', ', $row['domains']) }}</span>
                            </td>
                            <td>{{ number_format($row['hits']) }}</td>
                            <td>
                                <span class="figma-sa-subs-plan-tier">{{ $row['evidence_score'] }}</span>
                                <span class="figma-sa-subs-plan-detail">evidence</span>
                            </td>
                            <td>{{ $row['max_bot_score'] }}</td>
                            <td><span class="text-white/45">No</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="figma-sa-subs-empty">No multi-domain IPs in the last 30 days.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-super-admin.page>
@endsection
