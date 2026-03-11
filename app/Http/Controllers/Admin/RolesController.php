<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RolesController extends Controller
{
    public function index(): View
    {
        $roles = Role::withCount('users')->withCount('permissions')->orderBy('name')->paginate(15);

        return view('roles.index', compact('roles'));
    }

    public function create(): View
    {
        $permissions = Permission::orderBy('name')->get();

        return view('roles.create', compact('permissions'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:roles,slug'],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
        ]);

        $role->permissions()->sync($request->input('permissions', []));

        return redirect()->route('roles.index')->with('status', 'Role created successfully.');
    }

    public function edit(Role $role): View
    {
        $permissions = Permission::orderBy('name')->get();
        $role->load('permissions');

        return view('roles.edit', compact('role', 'permissions'));
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:roles,slug,' . $role->id],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);

        $role->update([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
        ]);

        $role->permissions()->sync($request->input('permissions', []));

        return redirect()->route('roles.index')->with('status', 'Role updated successfully.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->slug === 'super-admin') {
            return back()->with('error', 'Cannot delete the Super Admin role.');
        }

        $role->users()->update(['role_id' => null]);
        $role->permissions()->detach();
        $role->delete();

        return redirect()->route('roles.index')->with('status', 'Role deleted successfully.');
    }
}
