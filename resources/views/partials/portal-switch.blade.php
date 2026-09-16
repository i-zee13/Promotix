@php
    $isSuperAdminUser = (bool) (auth()->user()?->is_super_admin ?? false);
@endphp

@if ($isSuperAdminUser)
    @php
        $onSuperAdmin = request()->routeIs('super-admin.*');
    @endphp
    <div class="figma-portal-switch" role="navigation" aria-label="Switch portal">
        <a
            href="{{ route('super-admin.dashboard') }}"
            class="figma-portal-switch__seg {{ $onSuperAdmin ? 'is-on' : 'is-off' }}"
        >Super Admin</a>
        <a
            href="{{ route('dashboard') }}"
            class="figma-portal-switch__seg {{ $onSuperAdmin ? 'is-off' : 'is-on' }}"
        >Customer</a>
    </div>
@endif
