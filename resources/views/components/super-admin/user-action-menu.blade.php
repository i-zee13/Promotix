@props(['user'])

<x-super-admin.dashboard-dropdown align="right">
    <x-slot:trigger>
        <button type="button" @click="open = !open" class="figma-sa-users-kebab" aria-label="User actions">
            <svg class="h-[18px] w-[18px]" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zm0 4a2 2 0 110-4 2 2 0 010 4zm0 4a2 2 0 110-4 2 2 0 010 4z"/></svg>
        </button>
    </x-slot:trigger>

    <a href="{{ route('super-admin.users.show', $user) }}" class="figma-sa-users-action-item">
        <svg class="figma-sa-users-action-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
        View Profile
    </a>

    <form method="POST" action="{{ route('super-admin.users.impersonate', $user) }}">
        @csrf
        <button type="submit" class="figma-sa-users-action-item w-full" @disabled($user->is_super_admin)>
            <svg class="figma-sa-users-action-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            Login as User
        </button>
    </form>

    <form method="POST" action="{{ route('super-admin.users.reset-password', $user) }}" onsubmit="return confirm('Reset password for {{ $user->email }}?')">
        @csrf
        <button type="submit" class="figma-sa-users-action-item w-full">
            <svg class="figma-sa-users-action-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            Reset Password
        </button>
    </form>

    <a href="{{ route('super-admin.users.show', $user) }}#roles" class="figma-sa-users-action-item">
        <svg class="figma-sa-users-action-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V4a2 2 0 114 0v2m-4 6h.01M9 16h.01"/></svg>
        Roles
    </a>

    <form method="POST" action="{{ route('super-admin.users.status', $user) }}">
        @csrf
        @method('PATCH')
        <input type="hidden" name="status" value="suspended">
        <button type="submit" class="figma-sa-users-action-item figma-sa-users-action-item--warn w-full">
            <svg class="figma-sa-users-action-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            Suspend User
        </button>
    </form>

    <form method="POST" action="{{ route('super-admin.users.status', $user) }}">
        @csrf
        @method('PATCH')
        <input type="hidden" name="status" value="banned">
        <button type="submit" class="figma-sa-users-action-item figma-sa-users-action-item--ban w-full">
            <svg class="figma-sa-users-action-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
            Ban User
        </button>
    </form>

    <form method="POST" action="{{ route('super-admin.users.destroy', $user) }}" onsubmit="return confirm('Permanently remove {{ $user->email }}?')">
        @csrf
        @method('DELETE')
        <button type="submit" class="figma-sa-users-action-item figma-sa-users-action-item--danger w-full">
            <svg class="figma-sa-users-action-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            Remove User
        </button>
    </form>
</x-super-admin.dashboard-dropdown>
