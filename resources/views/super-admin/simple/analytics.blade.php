@extends('layouts.super-admin')

@section('title', 'Analytics')
@section('subtitle', 'Platform-wide event and fraud detection totals')

@section('content')
    <section class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <x-ui.kpi-card label="Visits" :value="number_format($visits)" />
        <x-ui.kpi-card label="Detections" :value="number_format($detections)" />
        <x-ui.kpi-card label="Hourly Buckets" :value="number_format($hourlyRows)" />
    </section>
@endsection
