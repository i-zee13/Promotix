<div
    x-show="createTeamModalOpen"
    x-cloak
    class="figma-sa-users-modal-backdrop"
    @keydown.escape.window="createTeamModalOpen = false"
>
    <div class="figma-sa-users-modal" @click.outside="createTeamModalOpen = false" role="dialog" aria-labelledby="create-team-title">
        <button type="button" class="figma-sa-users-modal-close" @click="createTeamModalOpen = false" aria-label="Close">&times;</button>
        <h2 id="create-team-title" class="figma-sa-users-modal-title">Create team</h2>
        <p class="figma-sa-users-modal-sub">
            Add a new team column (like Sales Team or Chat Support). Assign members from the column below or from any user profile.
        </p>

        <form method="POST" action="{{ route('super-admin.teams.store') }}" class="mt-5 space-y-4">
            @csrf
            <div>
                <label class="figma-sa-label">Team name <span class="text-rose-400">*</span></label>
                <input
                    type="text"
                    name="name"
                    required
                    maxlength="120"
                    autocomplete="off"
                    class="figma-input mt-1 w-full"
                    placeholder="e.g. Sales Team, Chat Support"
                    value="{{ old('name') }}"
                >
                @error('name')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="figma-sa-label">Description</label>
                <textarea name="description" rows="2" class="figma-input mt-1 w-full" placeholder="Optional — what this team handles">{{ old('description') }}</textarea>
            </div>
            @if (($departments ?? collect())->isNotEmpty())
                <div>
                    <label class="figma-sa-label">Department</label>
                    <select name="department_id" class="figma-select mt-1 w-full">
                        <option value="">— None —</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected(old('department_id') == $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="flex flex-wrap justify-end gap-3 pt-2">
                <button type="button" class="figma-sa-btn figma-sa-btn-outline" @click="createTeamModalOpen = false">Cancel</button>
                <button type="submit" class="figma-sa-btn figma-sa-btn-primary">Create team</button>
            </div>
        </form>
    </div>
</div>
