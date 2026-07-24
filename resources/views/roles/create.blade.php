@extends('layouts.admin')

@section('title', 'New role')

@section('content')
@php
    $checkedIds = old('permissions', []);
    $grouped = $permissions->groupBy(fn ($p) => \Illuminate\Support\Str::of($p->slug)->before('.')->before('-')->title()->value() ?: 'General');
@endphp
<div class="min-h-[calc(100vh-49px)] px-[12px] pb-[28px] pt-[20px] sm:px-[18px]" style="background:var(--brand-background,#0d0d0d);">
    <div class="mb-[16px] flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-[28px] font-semibold text-white">New role</h1>
            <p class="mt-1 text-[13px] text-white/60">Define a custom role and assign permissions.</p>
        </div>
        <a href="{{ route('roles.index') }}" class="rounded-[8px] border border-white/25 px-[12px] py-[8px] text-[13px] text-white hover:bg-white/10">← Back</a>
    </div>

    <form method="POST" action="{{ route('roles.store') }}" class="mx-auto max-w-3xl space-y-5 rounded-[12px] border border-white/15 p-[18px] sm:p-[22px]" style="background:color-mix(in srgb, var(--brand-primary,#6400B2) 22%, #101010);" x-data="{ filter: '' }">
        @csrf
        <div>
            <label for="name" class="mb-1.5 block text-[12px] font-semibold uppercase tracking-wide text-white/70">Name</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" required placeholder="e.g. Support Agent"
                class="w-full rounded-[8px] border border-white/25 bg-black/30 px-[12px] py-[10px] text-[13px] text-white placeholder:text-white/40 focus:border-white focus:outline-none">
            @error('name')<p class="mt-1 text-[12px] text-rose-300">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="slug" class="mb-1.5 block text-[12px] font-semibold uppercase tracking-wide text-white/70">Slug</label>
            <input id="slug" name="slug" type="text" value="{{ old('slug') }}" required placeholder="e.g. support-agent"
                class="w-full rounded-[8px] border border-white/25 bg-black/30 px-[12px] py-[10px] text-[13px] text-white placeholder:text-white/40 focus:border-white focus:outline-none">
            @error('slug')<p class="mt-1 text-[12px] text-rose-300">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="description" class="mb-1.5 block text-[12px] font-semibold uppercase tracking-wide text-white/70">Description <span class="font-normal normal-case tracking-normal text-white/45">(optional)</span></label>
            <textarea id="description" name="description" rows="2" placeholder="What this role is for"
                class="w-full rounded-[8px] border border-white/25 bg-black/30 px-[12px] py-[10px] text-[13px] text-white placeholder:text-white/40 focus:border-white focus:outline-none">{{ old('description') }}</textarea>
        </div>

        <div>
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <label class="text-[12px] font-semibold uppercase tracking-wide text-white/70">Permissions</label>
                <input type="search" x-model="filter" placeholder="Filter permissions..."
                    class="w-full max-w-xs rounded-[8px] border border-white/25 bg-black/30 px-[12px] py-[8px] text-[13px] text-white placeholder:text-white/40 focus:border-white focus:outline-none">
            </div>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                @foreach ($grouped as $groupLabel => $items)
                    <div class="rounded-[10px] border border-white/15 bg-black/20 p-[12px]"
                        x-data="{ toggleAll(state) { this.$root.querySelectorAll('input[type=checkbox][data-perm]').forEach(el => el.checked = state); } }">
                        <div class="mb-2 flex items-center justify-between gap-2">
                            <p class="text-[11px] font-semibold uppercase tracking-wider" style="color:color-mix(in srgb, var(--brand-primary,#6400B2) 70%, white);">{{ $groupLabel }}</p>
                            <div class="flex gap-2 text-[10px] uppercase text-white/50">
                                <button type="button" class="hover:text-white" @click="toggleAll(true)">All</button>
                                <button type="button" class="hover:text-rose-300" @click="toggleAll(false)">None</button>
                            </div>
                        </div>
                        <div class="space-y-1">
                            @foreach ($items as $p)
                                <label class="flex cursor-pointer items-center gap-2 rounded-[6px] px-2 py-1.5 hover:bg-white/5"
                                    x-show="filter === '' || '{{ strtolower(addslashes($p->name.' '.$p->slug)) }}'.includes(filter.toLowerCase())">
                                    <input type="checkbox" data-perm name="permissions[]" value="{{ $p->id }}" @checked(in_array($p->id, $checkedIds)) class="rounded border-white/30" style="accent-color:var(--brand-primary,#6400B2);">
                                    <span class="text-[13px] text-white/90">{{ $p->name }}</span>
                                    <span class="ml-auto font-mono text-[10px] text-white/40">{{ $p->slug }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex flex-wrap gap-3 pt-2">
            <button type="submit" class="rounded-[8px] px-[16px] py-[10px] text-[13px] font-semibold text-white" style="background:var(--brand-primary,#6400B2);">Create role</button>
            <a href="{{ route('roles.index') }}" class="rounded-[8px] border border-white/25 px-[16px] py-[10px] text-[13px] text-white hover:bg-white/10">Cancel</a>
        </div>
    </form>
</div>
@endsection
