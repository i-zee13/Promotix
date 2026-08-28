@php
    use App\Support\PortalTeamAccess;

    $canInviteTeam = auth()->user()?->canInviteTeamMembers() ?? false;
    $teamRoles = PortalTeamAccess::teamRoles();
    $pageOptions = PortalTeamAccess::pageOptions();
    $owner = PortalTeamAccess::workspaceOwner(auth()->user());
    $assignableDomains = $owner?->domains()->orderBy('hostname')->get(['id', 'hostname']) ?? collect();
@endphp

@if ($canInviteTeam)
    <div
        x-show="portalTeamInviteOpen"
        x-cloak
        class="figma-sa-users-modal-backdrop"
        style="z-index: 260;"
        @keydown.escape.window="portalTeamInviteOpen = false"
    >
        <div class="figma-sa-users-modal" style="max-width: 640px;" @click.outside="portalTeamInviteOpen = false" role="dialog" aria-labelledby="portal-team-invite-title">
            <button type="button" class="figma-sa-users-modal-close" @click="portalTeamInviteOpen = false" aria-label="Close">&times;</button>
            <h2 id="portal-team-invite-title" class="figma-sa-users-modal-title" x-text="portalTeamInviteMode === 'create' ? 'Create User' : 'Invite Users'"></h2>
            <p class="figma-sa-users-modal-sub" x-show="portalTeamInviteMode === 'invite'">
                Send an invite link. Choose role, pages, and domains for your workspace.
            </p>
            <p class="figma-sa-users-modal-sub" x-show="portalTeamInviteMode === 'create'" x-cloak>
                Create the account now with a password. No subscription email is sent.
            </p>

            <div class="mt-4 flex gap-2">
                <button type="button"
                    class="figma-sa-btn"
                    :class="portalTeamInviteMode === 'invite' ? 'figma-sa-btn-primary' : 'figma-sa-btn-outline'"
                    @click="portalTeamInviteMode = 'invite'">Invite</button>
                <button type="button"
                    class="figma-sa-btn"
                    :class="portalTeamInviteMode === 'create' ? 'figma-sa-btn-primary' : 'figma-sa-btn-outline'"
                    @click="portalTeamInviteMode = 'create'">Create user</button>
            </div>

            <form method="POST" action="{{ route('team.invite') }}" class="mt-5 space-y-4" x-show="portalTeamInviteMode === 'invite'">
                @csrf
                <div>
                    <label class="figma-sa-label">Email <span class="text-rose-400">*</span></label>
                    <input type="email" name="email" required class="figma-input mt-1 w-full" placeholder="user@company.com" value="{{ old('email') }}">
                    @error('email')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="figma-sa-label">Name</label>
                        <input type="text" name="name" class="figma-input mt-1 w-full" placeholder="Optional" value="{{ old('name') }}">
                    </div>
                    <div>
                        <label class="figma-sa-label">Role</label>
                        <select name="role_id" class="figma-select mt-1 w-full">
                            <option value="">— Default —</option>
                            @foreach ($teamRoles as $role)
                                <option value="{{ $role->id }}" @selected(old('role_id') == $role->id)>{{ $role->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label class="figma-sa-label">Pages <span class="text-[#8c8787] font-normal">(optional — leave empty for role defaults)</span></label>
                    <select name="page_slugs[]" class="figma-select mt-1 w-full" multiple size="5">
                        @foreach ($pageOptions as $page)
                            <option value="{{ $page['slug'] }}" @selected(collect(old('page_slugs', []))->contains($page['slug']))>{{ $page['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="figma-sa-label">Domain assigning <span class="text-[#8c8787] font-normal">(optional — all domains if empty)</span></label>
                    <select name="domain_ids[]" class="figma-select mt-1 w-full" multiple size="4">
                        @foreach ($assignableDomains as $domain)
                            <option value="{{ $domain->id }}" @selected(collect(old('domain_ids', []))->contains((string) $domain->id))>{{ $domain->hostname }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-wrap justify-end gap-3 pt-2">
                    <button type="button" class="figma-sa-btn figma-sa-btn-outline" @click="portalTeamInviteOpen = false">Cancel</button>
                    <button type="submit" class="figma-sa-btn figma-sa-btn-primary">Send invite</button>
                </div>
            </form>

            <form method="POST" action="{{ route('team.store') }}" class="mt-5 space-y-4" x-show="portalTeamInviteMode === 'create'" x-cloak>
                @csrf
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="figma-sa-label">Name <span class="text-rose-400">*</span></label>
                        <input type="text" name="name" required class="figma-input mt-1 w-full" placeholder="Full name" value="{{ old('name') }}">
                        @error('name')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="figma-sa-label">Email <span class="text-rose-400">*</span></label>
                        <input type="email" name="email" required class="figma-input mt-1 w-full" placeholder="user@company.com" value="{{ old('email') }}">
                        @error('email')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div>
                    <label class="figma-sa-label">Password <span class="text-rose-400">*</span></label>
                    <input type="password" name="password" required minlength="8" class="figma-input mt-1 w-full" placeholder="Min. 8 characters" autocomplete="new-password">
                    @error('password')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="figma-sa-label">Role</label>
                        <select name="role_id" class="figma-select mt-1 w-full">
                            <option value="">— Default —</option>
                            @foreach ($teamRoles as $role)
                                <option value="{{ $role->id }}" @selected(old('role_id') == $role->id)>{{ $role->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="figma-sa-label">Pages</label>
                        <select name="page_slugs[]" class="figma-select mt-1 w-full" multiple size="4">
                            @foreach ($pageOptions as $page)
                                <option value="{{ $page['slug'] }}" @selected(collect(old('page_slugs', []))->contains($page['slug']))>{{ $page['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label class="figma-sa-label">Domain assigning</label>
                    <select name="domain_ids[]" class="figma-select mt-1 w-full" multiple size="4">
                        @foreach ($assignableDomains as $domain)
                            <option value="{{ $domain->id }}" @selected(collect(old('domain_ids', []))->contains((string) $domain->id))>{{ $domain->hostname }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-wrap justify-end gap-3 pt-2">
                    <button type="button" class="figma-sa-btn figma-sa-btn-outline" @click="portalTeamInviteOpen = false">Cancel</button>
                    <button type="submit" class="figma-sa-btn figma-sa-btn-primary">Create user</button>
                </div>
            </form>
        </div>
    </div>
@endif
