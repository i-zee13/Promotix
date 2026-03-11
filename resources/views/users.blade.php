@extends('layouts.admin')

@section('title', 'Users & Teams')

@section('content')
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-xl border border-emerald-500 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">{{ session('status') }}</div>
        @endif

        <section class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-center sm:gap-3">
            <form method="GET" action="{{ route('users') }}" class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                <label for="users-search" class="sr-only">Search users</label>
                <div class="relative min-w-0 flex-1 sm:max-w-xs">
                    <input id="users-search" name="search" type="search" value="{{ request('search') }}" placeholder="Search by name or email"
                        class="w-full rounded-xl border border-dark-border bg-dark-card py-2 pl-10 pr-4 text-sm text-white placeholder-gray-500 focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent">
                </div>
                <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-accent px-4 py-2.5 text-sm font-medium text-white hover:bg-accent-hover">Search</button>
            </form>
        </section>

        <section class="rounded-xl border border-dark-border bg-dark-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[700px] text-left text-sm">
                    <thead>
                        <tr class="border-b border-dark-border bg-accent">
                            <th scope="col" class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">User</th>
                            <th scope="col" class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">Role</th>
                            <th scope="col" class="px-4 py-3 text-xs font-medium uppercase tracking-wider text-white">Created</th>
                            <th scope="col" class="px-4 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-dark-border">
                        @forelse ($users as $user)
                            <tr class="transition hover:bg-gray-800/50">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-dark-border text-sm font-medium text-gray-400">
                                            {{ strtoupper(mb_substr($user->name, 0, 1)) }}
                                        </span>
                                        <div>
                                            <p class="font-semibold text-white">{{ $user->name }}</p>
                                            <p class="text-xs text-gray-500">{{ $user->email }}</p>
                                            @if ($user->is_admin)
                                                <span class="inline-flex rounded px-1.5 py-0.5 text-xs font-medium bg-amber-500/20 text-amber-400">Super Admin</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <form method="POST" action="{{ route('users.update-role', $user) }}" class="inline" x-data="{ submitted: false }">
                                        @csrf
                                        @method('PATCH')
                                        <select name="role_id" onchange="this.form.submit()"
                                            class="rounded-lg border border-dark-border bg-dark py-1.5 pl-2 pr-8 text-sm text-white focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent">
                                            <option value="">— No role —</option>
                                            @foreach ($roles as $role)
                                                <option value="{{ $role->id }}" @selected($user->role_id === $role->id)>{{ $role->name }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                </td>
                                <td class="px-4 py-3 text-gray-400">{{ $user->created_at?->format('M j, Y') }}</td>
                                <td class="px-4 py-3">—</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-gray-400">No users found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($users->hasPages())
                <div class="border-t border-dark-border px-4 py-3">
                    {{ $users->links() }}
                </div>
            @endif
        </section>
    </div>
@endsection
