@extends('layouts.super-admin')

@section('title', 'Analytics')
@section('content')
<x-super-admin.page title="Analytics">
    <section class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <x-super-admin.kpi label="Visits" :value="number_format($visits)" />
        <x-super-admin.kpi label="Detections" :value="number_format($detections)" />
        <x-super-admin.kpi label="Hourly Buckets" :value="number_format($hourlyRows)" />
    </section>
</x-super-admin.page>
@endsection
