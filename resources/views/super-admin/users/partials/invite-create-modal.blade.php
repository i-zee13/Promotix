@php
    $pickWorkspaceOwner = $pickWorkspaceOwner ?? false;
    $modalOpenVar = $modalOpenVar ?? 'inviteOpen';
    $isPortalMember = ($workspaceOwner ?? null) !== null || $pickWorkspaceOwner;
    $modalRoles = $isPortalMember ? ($teamRoles ?? $roles) : $roles;
    $showTeamPicker = ($assignableTeams ?? collect())->isNotEmpty() && ($pickWorkspaceOwner || $isPortalMember);
@endphp

<div
    x-show="{{ $modalOpenVar }}"
    x-cloak
    class="figma-sa-users-modal-backdrop"
    @keydown.escape.window="{{ $modalOpenVar }} = false"
>
    <div class="figma-sa-users-modal" @click.outside="{{ $modalOpenVar }} = false" role="dialog" aria-labelledby="invite-users-title">
        <button type="button" class="figma-sa-users-modal-close" @click="{{ $modalOpenVar }} = false" aria-label="Close">&times;</button>
        <h2 id="invite-users-title" class="figma-sa-users-modal-title">
            @if ($pickWorkspaceOwner)
                <span x-show="inviteMode === 'invite'">Invite team member</span>
                <span x-show="inviteMode === 'create'" x-cloak>Create team member</span>
            @else
                <span x-text="inviteMode === 'create' ? 'Create User' : 'Invite Users'"></span>
            @endif
        </h2>
        <p class="figma-sa-users-modal-sub" x-show="inviteMode === 'invite'">
            @if ($pickWorkspaceOwner)
                Choose a workspace owner, then send an invite for their portal team. No subscription email is sent.
            @elseif ($isPortalMember)
                Send an invite link for {{ $workspaceOwner->name }}&rsquo;s workspace. No subscription email is sent.
            @else
                Send an invite link. Plan is optional — invite email has no subscription card.
            @endif
        </p>
        <p class="figma-sa-users-modal-sub" x-show="inviteMode === 'create'" x-cloak>
            @if ($pickWorkspaceOwner)
                Choose a workspace owner and create their portal member now with a password.
            @elseif ($isPortalMember)
                Create the portal member now with a password under {{ $workspaceOwner->name }}&rsquo;s workspace.
            @else
                Create the account now with a password. Plan is optional and no subscription email is sent.
            @endif
        </p>

        <div class="mt-4 flex gap-2">
            <button type="button"
                class="figma-sa-btn"
                :class="inviteMode === 'invite' ? 'figma-sa-btn-primary' : 'figma-sa-btn-outline'"
                @click="inviteMode = 'invite'">Invite</button>
            <button type="button"
                class="figma-sa-btn"
                :class="inviteMode === 'create' ? 'figma-sa-btn-primary' : 'figma-sa-btn-outline'"
                @click="inviteMode = 'create'">Create user</button>
        </div>

        <form method="POST" action="{{ route('super-admin.users.invite') }}" class="mt-5 space-y-4" x-show="inviteMode === 'invite'">
            @csrf
            @if ($pickWorkspaceOwner)
                <div>
                    <label class="figma-sa-label">Workspace owner <span class="text-rose-400">*</span></label>
                    <select name="workspace_owner_id" required class="figma-select mt-1 w-full">
                        <option value="" disabled @selected(! old('workspace_owner_id'))>Select owner…</option>
                        @foreach ($workspaceOwners ?? [] as $owner)
                            <option value="{{ $owner->id }}" @selected(old('workspace_owner_id') == $owner->id)>{{ $owner->name }} ({{ $owner->email }})</option>
                        @endforeach
                    </select>
                    @error('workspace_owner_id')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
                </div>
            @elseif ($isPortalMember && ($workspaceOwner ?? null))
                <input type="hidden" name="workspace_owner_id" value="{{ $workspaceOwner->id }}">
            @endif
            <div>
                <label class="figma-sa-label">Email <span class="text-rose-400">*</span></label>
                <input type="email" name="email" required autocomplete="off" class="figma-input mt-1 w-full" placeholder="user@company.com" value="{{ old('email') }}">
                @error('email')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="figma-sa-label">Name</label>
                    <input type="text" name="name" autocomplete="off" class="figma-input mt-1 w-full" placeholder="Optional" value="{{ old('name') }}">
                </div>
                <div>
                    <label class="figma-sa-label">Role</label>
                    <select name="role_id" class="figma-select mt-1 w-full">
                        <option value="">— Default —</option>
                        @foreach ($modalRoles as $role)
                            <option value="{{ $role->id }}" @selected(old('role_id') == $role->id)>{{ $role->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            @unless ($isPortalMember)
                <div>
                    <label class="figma-sa-label">Plan <span class="text-[#8c8787] font-normal">(optional — no email card)</span></label>
                    <select name="plan_id" class="figma-select mt-1 w-full">
                        <option value="">— None —</option>
                        @foreach ($plans as $plan)
                            <option value="{{ $plan->id }}" @selected(old('plan_id') == $plan->id)>{{ $plan->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endunless
            @if ($showTeamPicker)
                <div>
                    <label class="figma-sa-label">Assign to team</label>
                    <select name="team_id" class="figma-select mt-1 w-full">
                        <option value="">— None —</option>
                        @foreach ($assignableTeams as $team)
                            <option value="{{ $team->id }}" @selected(old('team_id') == $team->id)>{{ $team->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-[#8c8787]">Optional — places the user in a team column on the Teams board.</p>
                </div>
            @endif
            <div class="flex flex-wrap justify-end gap-3 pt-2">
                <button type="button" class="figma-sa-btn figma-sa-btn-outline" @click="{{ $modalOpenVar }} = false">Cancel</button>
                <button type="submit" class="figma-sa-btn figma-sa-btn-primary">Send invite</button>
            </div>
        </form>

        <form method="POST" action="{{ route('super-admin.users.store') }}" class="mt-5 space-y-4" x-show="inviteMode === 'create'" x-cloak>
            @csrf
            @if ($pickWorkspaceOwner)
                <div>
                    <label class="figma-sa-label">Workspace owner <span class="text-rose-400">*</span></label>
                    <select name="workspace_owner_id" required class="figma-select mt-1 w-full">
                        <option value="" disabled @selected(! old('workspace_owner_id'))>Select owner…</option>
                        @foreach ($workspaceOwners ?? [] as $owner)
                            <option value="{{ $owner->id }}" @selected(old('workspace_owner_id') == $owner->id)>{{ $owner->name }} ({{ $owner->email }})</option>
                        @endforeach
                    </select>
                </div>
            @elseif ($isPortalMember && ($workspaceOwner ?? null))
                <input type="hidden" name="workspace_owner_id" value="{{ $workspaceOwner->id }}">
            @endif
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="figma-sa-label">Name <span class="text-rose-400">*</span></label>
                    <input type="text" name="name" required autocomplete="off" class="figma-input mt-1 w-full" placeholder="Full name" value="{{ old('name') }}">
                    @error('name')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="figma-sa-label">Email <span class="text-rose-400">*</span></label>
                    <input type="email" name="email" required autocomplete="off" class="figma-input mt-1 w-full" placeholder="user@company.com" value="{{ old('email') }}">
                    @error('email')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
                </div>
            </div>
            <div>
                <label class="figma-sa-label">Password <span class="text-rose-400">*</span></label>
                <input type="password" name="password" required minlength="8" autocomplete="new-password" class="figma-input mt-1 w-full" placeholder="Min. 8 characters">
                @error('password')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="figma-sa-label">Role</label>
                    <select name="role_id" class="figma-select mt-1 w-full">
                        <option value="">— Default —</option>
                        @foreach ($modalRoles as $role)
                            <option value="{{ $role->id }}" @selected(old('role_id') == $role->id)>{{ $role->name }}</option>
                        @endforeach
                    </select>
                </div>
                @unless ($isPortalMember)
                    <div>
                        <label class="figma-sa-label">Plan <span class="text-[#8c8787] font-normal">(optional)</span></label>
                        <select name="plan_id" class="figma-select mt-1 w-full">
                            <option value="">— None —</option>
                            @foreach ($plans as $plan)
                                <option value="{{ $plan->id }}" @selected(old('plan_id') == $plan->id)>{{ $plan->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endunless
            </div>
            @if ($showTeamPicker)
                <div>
                    <label class="figma-sa-label">Assign to team</label>
                    <select name="team_id" class="figma-select mt-1 w-full">
                        <option value="">— None —</option>
                        @foreach ($assignableTeams as $team)
                            <option value="{{ $team->id }}" @selected(old('team_id') == $team->id)>{{ $team->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="flex flex-wrap justify-end gap-3 pt-2">
                <button type="button" class="figma-sa-btn figma-sa-btn-outline" @click="{{ $modalOpenVar }} = false">Cancel</button>
                <button type="submit" class="figma-sa-btn figma-sa-btn-primary">Create user</button>
            </div>
        </form>
    </div>
</div>
