@extends('layouts.admin')

@section('title', 'New Support Ticket')

@section('content')
<div class="space-y-6">
    <x-ui.page-header
        title="Create support ticket"
        subtitle="Open a ticket on behalf of a customer or escalate an internal issue.">
        <x-slot:actions>
            <a href="{{ route('support-system') }}" class="brand-btn-secondary">Back to queue</a>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($errors->any())
        <div class="rounded-xl2 border border-rose-500/40 bg-rose-500/10 p-4 text-sm text-rose-200">
            <ul class="list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <x-ui.card class="p-6">
        <form method="POST" action="{{ route('support-system.store') }}" class="space-y-5">
            @csrf

            <div class="grid gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400" for="subject">Subject</label>
                    <input id="subject" name="subject" type="text" required maxlength="200"
                        value="{{ old('subject') }}"
                        class="brand-input mt-1 w-full"
                        placeholder="Short summary of the issue">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400" for="priority">Priority</label>
                    <select id="priority" name="priority" required class="brand-select mt-1 w-full">
                        @foreach (['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'urgent' => 'Urgent'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('priority', 'medium') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400" for="category">Category (optional)</label>
                    <input id="category" name="category" type="text" maxlength="80"
                        value="{{ old('category') }}"
                        class="brand-input mt-1 w-full"
                        placeholder="billing, integrations, abuse, ...">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400" for="requester_email">Requester email (optional)</label>
                    <input id="requester_email" name="requester_email" type="email" maxlength="200"
                        value="{{ old('requester_email') }}"
                        class="brand-input mt-1 w-full"
                        placeholder="customer@example.com">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400" for="assigned_to_id">Assign to (optional)</label>
                    <select id="assigned_to_id" name="assigned_to_id" class="brand-select mt-1 w-full">
                        <option value="">Unassigned</option>
                        @foreach ($agents as $agent)
                            <option value="{{ $agent->id }}" @selected(old('assigned_to_id') == $agent->id)>{{ $agent->name }} ({{ $agent->email }})</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400" for="sla_hours">SLA (hours)</label>
                    <input id="sla_hours" name="sla_hours" type="number" min="1" max="240"
                        value="{{ old('sla_hours', 24) }}"
                        class="brand-input mt-1 w-full">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400" for="body">Description</label>
                    <textarea id="body" name="body" rows="6" required class="brand-input mt-1 w-full" placeholder="Describe the issue, steps to reproduce, customer impact...">{{ old('body') }}</textarea>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
                <a href="{{ route('support-system') }}" class="brand-btn-secondary">Cancel</a>
                <button type="submit" class="brand-btn-primary">Create ticket</button>
            </div>
        </form>
    </x-ui.card>
</div>
@endsection
