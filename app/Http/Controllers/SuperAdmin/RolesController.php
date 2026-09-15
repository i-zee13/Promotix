<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermissionAudit;
use App\Support\RolePortalCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RolesController extends Controller
{
    public function index(): View
    {
        $roles = Role::with('permissions:id,name,slug,route_name')
            ->withCount('users')
            ->withCount('permissions')
            ->orderBy('portal')
            ->orderBy('name')
            ->paginate(15);

        $allRolesForReassign = Role::query()
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return view('super-admin.roles.index', compact('roles', 'allRolesForReassign'));
    }

    public function create(): View
    {
        return view('super-admin.roles.create', $this->wizardData());
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateWizard($request);

        if (! $request->boolean('confirm_review')) {
            return back()->withInput()->with('error', 'Confirm the Review Role checkbox before creating.');
        }

        $role = Role::create($this->roleAttributesFromValidated($validated));
        $this->syncPermissionsFromWizard($role, $validated);
        $this->audit($request, $role, 'created', null, $role->fresh()->snapshotForAudit());

        return redirect()->route('super-admin.roles.index')->with('status', 'Role created successfully.');
    }

    public function edit(Role $role): View
    {
        $role->load('permissions');

        return view('super-admin.roles.edit', [
            'role' => $role,
            ...$this->wizardData(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $before = $role->snapshotForAudit();
        $validated = $this->validateWizard($request, $role);

        if (! $request->boolean('confirm_review')) {
            return back()->withInput()->with('error', 'Confirm the Review Role checkbox before saving.');
        }

        $role->update($this->roleAttributesFromValidated($validated));
        $this->syncPermissionsFromWizard($role, $validated);
        $this->audit($request, $role, 'updated', $before, $role->fresh()->snapshotForAudit());

        return redirect()->route('super-admin.roles.index')->with('status', 'Role updated successfully.');
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        if ($role->slug === 'super-admin') {
            return back()->with('error', 'Cannot delete the Super Admin role without a protected transfer.');
        }

        $usersCount = $role->users()->count();
        if ($usersCount > 0) {
            $reassignTo = (int) $request->input('reassign_role_id', 0);
            if ($reassignTo <= 0 || $reassignTo === (int) $role->id) {
                return back()->with('error', "Role has {$usersCount} member(s). Reassign them to another role before deleting.");
            }

            $replacement = Role::query()->find($reassignTo);
            if (! $replacement) {
                return back()->with('error', 'Reassignment role not found.');
            }

            $role->users()->update(['role_id' => $replacement->id]);
        }

        $before = $role->snapshotForAudit();
        $this->audit($request, $role, 'deleted', $before, null);
        $role->permissions()->detach();
        $role->delete();

        return redirect()->route('super-admin.roles.index')->with('status', 'Role deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function wizardData(): array
    {
        $baseRoles = Role::query()
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'portal']);

        return [
            'portals' => RolePortalCatalog::portals(),
            'userPages' => RolePortalCatalog::userPages(),
            'userAbilities' => RolePortalCatalog::userAbilities(),
            'adminPages' => RolePortalCatalog::adminPages(),
            'abilityRequirements' => RolePortalCatalog::abilityPageRequirements(),
            'roleColors' => RolePortalCatalog::roleColors(),
            'baseRoles' => $baseRoles,
            'reassignRoles' => $baseRoles,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateWizard(Request $request, ?Role $existing = null): array
    {
        $slugRule = Rule::unique('roles', 'slug');
        if ($existing) {
            $slugRule = $slugRule->ignore($existing->id);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:255', $slugRule],
            'description' => ['nullable', 'string', 'max:250'],
            'portal' => ['required', Rule::in([RolePortalCatalog::PORTAL_USER, RolePortalCatalog::PORTAL_ADMIN])],
            'color' => ['nullable', 'string', 'max:32'],
            'is_temporary' => ['nullable', 'boolean'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'base_role_id' => ['nullable', 'exists:roles,id'],
            'page_access' => ['nullable', 'array'],
            'page_access.*' => ['in:none,view,full'],
            'abilities' => ['nullable', 'array'],
            'abilities.*' => ['string', 'max:64'],
            'scope' => ['nullable', 'array'],
            'scope.mode' => ['nullable', Rule::in(['workspace', 'project', 'campaign'])],
            'scope.include_future_projects' => ['nullable', 'boolean'],
            'scope.workspace_ids' => ['nullable', 'array'],
            'scope.project_ids' => ['nullable', 'array'],
            'scope.campaign_ids' => ['nullable', 'array'],
            'confirm_review' => ['accepted'],
        ]);

        // Security: User Portal roles cannot grant Admin Portal pages.
        if (($validated['portal'] ?? '') === RolePortalCatalog::PORTAL_USER) {
            $allowedKeys = collect(RolePortalCatalog::userPages())->pluck('key')->all();
            $validated['page_access'] = collect($validated['page_access'] ?? [])
                ->only($allowedKeys)
                ->all();
            $allowedAbilities = collect(RolePortalCatalog::userAbilities())->pluck('key')->all();
            $validated['abilities'] = array_values(array_intersect(
                $validated['abilities'] ?? [],
                $allowedAbilities
            ));
        } else {
            $allowedKeys = collect(RolePortalCatalog::adminPages())->pluck('key')->all();
            $validated['page_access'] = collect($validated['page_access'] ?? [])
                ->only($allowedKeys)
                ->all();
            // Admin portal uses page keys as granted abilities (page/ability combined in catalog).
            $validated['abilities'] = array_values(array_keys(array_filter(
                $validated['page_access'] ?? [],
                fn ($level) => in_array($level, ['view', 'full'], true)
            )));
        }

        // Abilities without matching page access are stripped (checklist #5).
        if (($validated['portal'] ?? '') === RolePortalCatalog::PORTAL_USER) {
            $validated['abilities'] = $this->filterAbilitiesByPageAccess(
                $validated['abilities'] ?? [],
                $validated['page_access'] ?? []
            );
        }

        // Future-project access must be explicit boolean (default false).
        $scope = $validated['scope'] ?? [];
        $scope['mode'] = $scope['mode'] ?? 'workspace';
        $scope['include_future_projects'] = (bool) ($scope['include_future_projects'] ?? false);
        $scope['workspace_ids'] = array_values(array_filter($scope['workspace_ids'] ?? []));
        $scope['project_ids'] = array_values(array_filter($scope['project_ids'] ?? []));
        $scope['campaign_ids'] = array_values(array_filter($scope['campaign_ids'] ?? []));
        $validated['scope'] = $scope;

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        return $validated;
    }

    /**
     * @param  list<string>  $abilities
     * @param  array<string, string>  $pageAccess
     * @return list<string>
     */
    private function filterAbilitiesByPageAccess(array $abilities, array $pageAccess): array
    {
        $hasAnyAccess = collect($pageAccess)->contains(fn ($l) => in_array($l, ['view', 'full'], true));
        $hasFull = fn (string $pageKey) => ($pageAccess[$pageKey] ?? 'none') === 'full';
        $reqs = RolePortalCatalog::abilityPageRequirements();

        return array_values(array_filter($abilities, function (string $ability) use ($hasAnyAccess, $hasFull, $reqs, $pageAccess) {
            if (! $hasAnyAccess) {
                return false;
            }
            // create/edit/delete need at least one Full page
            if (in_array($ability, ['create_data', 'edit_data', 'delete_data', 'manage_domains', 'edit_domain_settings', 'configure_campaigns', 'manage_exclusions'], true)) {
                if (! collect($pageAccess)->contains(fn ($l) => $l === 'full')) {
                    return false;
                }
            }
            $needed = $reqs[$ability] ?? [];
            if ($needed === []) {
                return true;
            }
            $viewOk = in_array($ability, ['view_data', 'export_data', 'view_campaigns', 'view_domains'], true);
            foreach ($needed as $pageKey) {
                $level = $pageAccess[$pageKey] ?? 'none';
                if ($viewOk && in_array($level, ['view', 'full'], true)) {
                    return true;
                }
                if (! $viewOk && $hasFull($pageKey)) {
                    return true;
                }
            }

            return false;
        }));
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function roleAttributesFromValidated(array $validated): array
    {
        return [
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
            'portal' => $validated['portal'],
            'color' => $validated['color'] ?? null,
            'is_temporary' => (bool) ($validated['is_temporary'] ?? false),
            'expires_at' => ! empty($validated['is_temporary']) ? ($validated['expires_at'] ?? null) : null,
            'base_role_id' => $validated['base_role_id'] ?? null,
            'page_access' => $validated['page_access'] ?? [],
            'abilities' => $validated['abilities'] ?? [],
            'scope' => $validated['scope'] ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncPermissionsFromWizard(Role $role, array $validated): void
    {
        // Create Role never assigns package roles — only page → permission slug mapping for User Portal.
        if (($validated['portal'] ?? '') !== RolePortalCatalog::PORTAL_USER) {
            $role->permissions()->sync([]);

            return;
        }

        $slugs = RolePortalCatalog::permissionSlugsForPageAccess($validated['page_access'] ?? []);
        $ids = Permission::query()->whereIn('slug', $slugs)->pluck('id')->all();
        $role->permissions()->sync($ids);
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    private function audit(Request $request, Role $role, string $action, ?array $before, ?array $after): void
    {
        RolePermissionAudit::create([
            'actor_id' => $request->user()?->id,
            'role_id' => $role->id,
            'action' => $action,
            'portal' => $role->portal,
            'scope' => $role->scope,
            'before' => $before,
            'after' => $after,
        ]);
    }
}
