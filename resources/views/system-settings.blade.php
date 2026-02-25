@extends('layouts.admin')

@section('title', 'System Settings')

@section('content')
    <div class="space-y-6">
        <x-system-settings-content :rows="$rows" :total="$total" :from="$from" :to="$to" :status-classes="$statusClasses" />
    </div>
@endsection
