@php
    $isSuperAdminUser = (bool) (auth()->user()?->is_super_admin ?? false);
@endphp

@if ($isSuperAdminUser)
    @php
        $onSuperAdmin = request()->routeIs('super-admin.*');
    @endphp
    <div class="figma-portal-switch inline-flex shrink-0 overflow-hidden rounded-[6px] border border-[#6400B2] text-[10px] font-semibold leading-none sm:text-[11px]" role="navigation" aria-label="Switch portal">
        <a
            href="{{ route('super-admin.dashboard') }}"
            @class([
                'px-[9px] py-[7px] transition sm:px-[11px]',
                'bg-[#6400B2] text-white' => $onSuperAdmin,
                'bg-[#0D0D0D] text-white/65 hover:bg-[#6400B2]/35 hover:text-white' => ! $onSuperAdmin,
            ])
        >Super Admin</a>
        <a
            href="{{ route('dashboard') }}"
            @class([
                'border-l border-[#6400B2] px-[9px] py-[7px] transition sm:px-[11px]',
                'bg-[#6400B2] text-white' => ! $onSuperAdmin,
                'bg-[#0D0D0D] text-white/65 hover:bg-[#6400B2]/35 hover:text-white' => $onSuperAdmin,
            ])
        >Customer</a>
    </div>
@endif
