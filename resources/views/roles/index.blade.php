@extends('layouts.admin')

@section('title', 'Roles & Permissions')

@section('content')
<div class="min-h-[calc(100vh-49px)] px-[12px] pb-[28px] pt-[20px] sm:px-[18px]" style="background:var(--brand-background,#0d0d0d);">
    <div class="mb-[16px] flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-[28px] font-semibold text-white">Roles &amp; Permissions</h1>
            <p class="mt-1 text-[13px] text-white/60">Create roles and assign permissions to control sidebar access.</p>
        </div>
        <a href="{{ route('roles.create') }}" class="rounded-[8px] px-[14px] py-[9px] text-[13px] font-semibold text-white" style="background:var(--brand-primary,#6400B2);">New role</a>
    </div>

    @if (session('status'))
        <div class="mb-[14px] rounded-[8px] border border-emerald-400/40 bg-emerald-500/15 px-[14px] py-[10px] text-[13px] text-emerald-100">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-[14px] rounded-[8px] border border-rose-400/40 bg-rose-500/15 px-[14px] py-[10px] text-[13px] text-rose-100">{{ session('error') }}</div>
    @endif

    <div class="overflow-hidden rounded-[12px] border border-white/15" style="background:color-mix(in srgb, var(--brand-primary,#6400B2) 22%, #101010);">
        <div class="overflow-x-auto">
            <table class="min-w-[640px] w-full text-left text-[13px]">
                <thead class="bg-black/25 text-white/70">
                    <tr>
                        <th class="px-[16px] py-[12px] font-semibold">Role</th>
                        <th class="px-[16px] py-[12px] font-semibold">Permissions</th>
                        <th class="px-[16px] py-[12px] font-semibold">Users</th>
                        <th class="px-[16px] py-[12px] font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/10">
                    @forelse ($roles as $role)
                        <tr class="text-white/90">
                            <td class="px-[16px] py-[14px]">
                                <p class="font-semibold text-white">{{ $role->name }}</p>
                                <p class="text-[11px] text-white/50">{{ $role->slug }}</p>
                                @if ($role->description)
                                    <p class="mt-1 text-[12px] text-white/65">{{ Str::limit($role->description, 80) }}</p>
                                @endif
                            </td>
                            <td class="px-[16px] py-[14px]">
                                <span class="rounded-full px-[10px] py-[3px] text-[11px] font-semibold text-white" style="background:var(--brand-primary,#6400B2);">{{ $role->permissions_count }}</span>
                            </td>
                            <td class="px-[16px] py-[14px]">
                                <span class="rounded-full bg-white/10 px-[10px] py-[3px] text-[11px] font-semibold text-white">{{ $role->users_count }}</span>
                            </td>
                            <td class="px-[16px] py-[14px] text-right">
                                <div class="inline-flex items-center gap-2">
                                    <a href="{{ route('roles.edit', $role) }}" class="rounded-[6px] border border-white/25 px-[12px] py-[6px] text-[12px] font-medium text-white hover:bg-white/10">Edit</a>
                                    @if ($role->slug !== 'super-admin')
                                        <form method="POST" action="{{ route('roles.destroy', $role) }}" class="inline" onsubmit="return confirm('Delete this role? Users with this role will have no role.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-[6px] border border-rose-400/40 px-[12px] py-[6px] text-[12px] font-medium text-rose-200 hover:bg-rose-500/15">Delete</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-[16px] py-[28px] text-center text-white/50">No roles yet. Create one to assign permissions.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($roles->hasPages())
            <div class="border-t border-white/10 px-[16px] py-[12px]">{{ $roles->links() }}</div>
        @endif
    </div>
</div>
@endsection
