@php
    $isSuperAdminUser = (bool) (auth()->user()?->is_super_admin ?? false);
@endphp

@if ($isSuperAdminUser)
    @php
        $onSuperAdmin = request()->routeIs('super-admin.*');
    @endphp
    <div class="figma-portal-switch inline-flex h-[34px] shrink-0 items-stretch overflow-hidden rounded-[7px] border border-[#6400B2] text-[12px] font-semibold leading-none sm:text-[13px]" role="navigation" aria-label="Switch portal">
        <a
            href="{{ route('super-admin.dashboard') }}"
            @class([
                'inline-flex h-full items-center justify-center px-[12px] leading-none transition sm:px-[14px]',
                'bg-[#6400B2] text-white' => $onSuperAdmin,
                'bg-[#0D0D0D] text-white/65 hover:bg-[#6400B2]/35 hover:text-white' => ! $onSuperAdmin,
            ])
        >Super Admin</a>
        <a
            href="{{ route('dashboard') }}"
            @class([
                'inline-flex h-full items-center justify-center border-l border-[#6400B2] px-[12px] leading-none transition sm:px-[14px]',
                'bg-[#6400B2] text-white' => ! $onSuperAdmin,
                'bg-[#0D0D0D] text-white/65 hover:bg-[#6400B2]/35 hover:text-white' => $onSuperAdmin,
            ])
        >Customer</a>
    </div>
@endif
