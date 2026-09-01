<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TeamsController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless(Schema::hasTable('teams'), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('teams', 'name')],
            'description' => ['nullable', 'string', 'max:500'],
            'department_id' => ['nullable', 'exists:departments,id'],
        ]);

        Team::query()->create([
            'name' => trim($data['name']),
            'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(5)),
            'description' => $data['description'] ?? null,
            'department_id' => $data['department_id'] ?? null,
            'is_active' => true,
        ]);

        return redirect()
            ->route('super-admin.users.index', ['tab' => 'teams'])
            ->with('status', 'Team “'.$data['name'].'” created. Assign members from the column or user profile.');
    }

    public function update(Request $request, Team $team): RedirectResponse
    {
        abort_unless(Schema::hasTable('teams'), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('teams', 'name')->ignore($team->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $team->update([
            'name' => trim($data['name']),
            'description' => $data['description'] ?? null,
            'department_id' => $data['department_id'] ?? null,
            'is_active' => $request->boolean('is_active', $team->is_active),
        ]);

        return redirect()
            ->route('super-admin.users.index', ['tab' => 'teams'])
            ->with('status', 'Team updated.');
    }

    public function destroy(Team $team): RedirectResponse
    {
        abort_unless(Schema::hasTable('teams'), 404);

        $team->members()->detach();
        $team->update(['is_active' => false]);

        return redirect()
            ->route('super-admin.users.index', ['tab' => 'teams'])
            ->with('status', 'Team archived. Members are now unassigned.');
    }

    public function assignMember(Request $request, Team $team): RedirectResponse
    {
        abort_unless(Schema::hasTable('team_members'), 404);

        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $team->members()->syncWithoutDetaching([
            (int) $data['user_id'] => [
                'assigned_by' => $request->user()->id,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        ]);

        return redirect()
            ->route('super-admin.users.index', ['tab' => 'teams'])
            ->with('status', 'Member assigned to '.$team->name.'.');
    }
}
