@extends('layouts.super-admin')

@section('title', 'Cross-domain intelligence')

@section('content')
<x-super-admin.page title="Cross-domain intelligence" subtitle="IPs seen on multiple domains. Score = evidence only — not an auto-block trigger.">
    @include('partials.super-admin.flash')

    <div class="mb-4">
        <a href="{{ route('super-admin.traffic.index') }}" class="text-[13px] text-[#FFB380] hover:underline">← Back to Traffic &amp; Bot Logs</a>
    </div>

    @include('partials.super-admin.cross-domain-intel-table', [
        'rows' => $rows,
        'scrollable' => true,
        'showViewAll' => false,
    ])
</x-super-admin.page>
@endsection
