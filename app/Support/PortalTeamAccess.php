<?php

namespace App\Support;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;

class PortalTeamAccess
{
    public const ROLE_SLUGS = [
        'managing' => 'team-managing',
        'editor' => 'team-editor',
        'viewable' => 'team-viewable',
    ];

    /** @return list<array{slug: string, label: string}> */
    public static function pageOptions(): array
    {
        $menu = config('admin.menu', []);
        $options = [];

        foreach ($menu as $slug => $item) {
            $options[] = [
                'slug' => (string) $slug,
                'label' => (string) ($item['label'] ?? $slug),
            ];
        }

        return $options;
    }

    public static function ensureTeamRoles(): void
    {
        $allPermissionIds = Permission::query()->pluck('id');

        $editorPermissionIds = Permission::query()
            ->whereNotIn('slug', ['upgrade-plan', 'team-invite'])
            ->pluck('id');

        $viewablePermissionIds = Permission::query()
            ->whereIn('slug', [
                'dashboard',
                'paid-marketing-dashboard',
                'paid-marketing-detailed',
                'paid-marketing-platform-connections',
                'paid-marketing-detection-settings',
                'bot-protection',
                'domain-management',
            ])
            ->pluck('id');

        $roles = [
            self::ROLE_SLUGS['managing'] => ['name' => 'Managing', 'permissions' => $allPermissionIds],
            self::ROLE_SLUGS['editor'] => ['name' => 'Editor', 'permissions' => $editorPermissionIds],
            self::ROLE_SLUGS['viewable'] => ['name' => 'Viewable', 'permissions' => $viewablePermissionIds],
        ];

        foreach ($roles as $slug => $meta) {
            $role = Role::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $meta['name'], 'description' => 'Portal team role']
            );
            $role->permissions()->sync($meta['permissions']);
        }
    }

    /** @return Collection<int, Role> */
    public static function teamRoles(): Collection
    {
        self::ensureTeamRoles();

        return Role::query()
            ->whereIn('slug', array_values(self::ROLE_SLUGS))
            ->get()
            ->sortBy(fn (Role $role) => array_search($role->slug, array_values(self::ROLE_SLUGS), true))
            ->values();
    }

    /** @param  list<string>|null  $pageSlugs */
    public static function normalizePageSlugs(?array $pageSlugs): ?array
    {
        $allowed = collect(self::pageOptions())->pluck('slug')->all();
        $picked = array_values(array_unique(array_filter(array_map(
            fn ($slug) => is_string($slug) ? trim($slug) : '',
            $pageSlugs ?? []
        ))));

        $picked = array_values(array_intersect($picked, $allowed));

        return $picked === [] ? null : $picked;
    }

    /** @param  list<int|string>|null  $domainIds */
    public static function normalizeDomainIds(User $owner, ?array $domainIds): ?array
    {
        $ownerId = $owner->team_owner_id ?: $owner->id;
        $allowed = $owner->domains()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $picked = array_values(array_unique(array_map('intval', array_filter($domainIds ?? [], fn ($id) => $id !== '' && $id !== null))));

        $picked = array_values(array_intersect($picked, $allowed));

        return $picked === [] ? null : $picked;
    }

    public static function applyToUser(User $user, ?int $roleId, ?array $pageSlugs, ?array $domainIds): void
    {
        $payload = array_filter([
            'role_id' => $roleId,
            'allowed_page_slugs' => self::normalizePageSlugs($pageSlugs),
            'allowed_domain_ids' => self::normalizeDomainIds(
                $user->teamOwner ?: $user,
                $domainIds
            ),
        ], fn ($value) => $value !== null);

        if ($payload !== []) {
            $user->forceFill($payload)->save();
        }
    }

    public static function workspaceOwner(User $user): User
    {
        if ($user->team_owner_id && $user->relationLoaded('teamOwner') && $user->teamOwner) {
            return $user->teamOwner;
        }

        if ($user->team_owner_id) {
            return User::query()->find($user->team_owner_id) ?? $user;
        }

        return $user;
    }
}
