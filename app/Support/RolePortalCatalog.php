<?php

namespace App\Support;

/**
 * Clickronix Create Role catalogs — User Portal vs Admin Portal.
 * Package roles are intentionally excluded from Create Role.
 */
class RolePortalCatalog
{
    public const PORTAL_USER = 'user';

    public const PORTAL_ADMIN = 'admin';

    public const ACCESS_NONE = 'none';

    public const ACCESS_VIEW = 'view';

    public const ACCESS_FULL = 'full';

    /**
     * @return list<array{key: string, label: string, description: string}>
     */
    public static function portals(): array
    {
        return [
            [
                'key' => self::PORTAL_USER,
                'label' => 'User Portal',
                'description' => 'Workspace members and customers',
            ],
            [
                'key' => self::PORTAL_ADMIN,
                'label' => 'Admin Portal',
                'description' => 'Clickronix platform administrators',
            ],
        ];
    }

    /**
     * Named role colors for the Create Role picker.
     *
     * @return list<array{key: string, label: string, hex: string}>
     */
    public static function roleColors(): array
    {
        return [
            ['key' => 'orange', 'label' => 'Orange', 'hex' => '#FF6600'],
            ['key' => 'purple', 'label' => 'Purple', 'hex' => '#7C3AED'],
            ['key' => 'blue', 'label' => 'Blue', 'hex' => '#2563EB'],
            ['key' => 'teal', 'label' => 'Teal', 'hex' => '#0D9488'],
            ['key' => 'rose', 'label' => 'Rose', 'hex' => '#E11D48'],
            ['key' => 'slate', 'label' => 'Slate', 'hex' => '#64748B'],
        ];
    }

    /**
     * @return list<array{key: string, label: string, group: string, view_means: string, abilities: list<string>, permission_slug: ?string}>
     */
    public static function userPages(): array
    {
        return [
            [
                'key' => 'overview',
                'label' => 'Overview',
                'group' => 'Home',
                'view_means' => 'Workspace summary and assigned work',
                'abilities' => ['view_data'],
                'permission_slug' => 'dashboard',
            ],
            [
                'key' => 'dashboard',
                'label' => 'Dashboard',
                'group' => 'Paid Advertising',
                'view_means' => 'Performance cards and key metrics',
                'abilities' => ['view_data', 'export_data'],
                'permission_slug' => 'paid-marketing-dashboard',
            ],
            [
                'key' => 'advanced_view',
                'label' => 'Advanced View',
                'group' => 'Paid Advertising',
                'view_means' => 'Detailed filters and saved views',
                'abilities' => ['view_data', 'create_data', 'export_data'],
                'permission_slug' => 'paid-marketing-detailed',
            ],
            [
                'key' => 'platform_integrate',
                'label' => 'Platform Integrate',
                'group' => 'Paid Advertising',
                'view_means' => 'Connected ad and platform sources',
                'abilities' => ['view_data', 'edit_data'],
                'permission_slug' => 'paid-marketing-platform-connections',
            ],
            [
                'key' => 'detection_panel',
                'label' => 'Detection Panel',
                'group' => 'Paid Advertising',
                'view_means' => 'Detection verdicts and reasons',
                'abilities' => ['view_data', 'create_data', 'edit_data'],
                'permission_slug' => 'paid-marketing-detection-settings',
            ],
            [
                'key' => 'analytics_dashboard',
                'label' => 'Analytics Dashboard',
                'group' => 'Analytics',
                'view_means' => 'Campaign and conversion analytics',
                'abilities' => ['view_data', 'export_data', 'create_data'],
                'permission_slug' => 'bot-protection',
            ],
            [
                'key' => 'traffic_control',
                'label' => 'Traffic Control',
                'group' => 'Analytics',
                'view_means' => 'Repeated devices, IP changes and IP reputation',
                'abilities' => ['view_data', 'export_data', 'manage_exclusions', 'view_campaigns'],
                'permission_slug' => 'bot-protection',
            ],
            [
                'key' => 'visitor_journey',
                'label' => 'Visitor Journey',
                'group' => 'Analytics',
                'view_means' => 'Visitor paths and event sequences',
                'abilities' => ['view_data', 'export_data'],
                'permission_slug' => 'bot-protection',
            ],
            [
                'key' => 'domains',
                'label' => 'Domains',
                'group' => 'Site Management',
                'view_means' => 'Tracked domains and verification',
                'abilities' => ['view_domains', 'manage_domains', 'edit_data'],
                'permission_slug' => 'domain-management',
            ],
            [
                'key' => 'settings',
                'label' => 'Settings',
                'group' => 'Site Management',
                'view_means' => 'Workspace access and member settings',
                'abilities' => ['view_data', 'invite_members', 'assign_user_roles', 'remove_members'],
                'permission_slug' => 'team-invite',
            ],
        ];
    }

    /**
     * @return list<array{key: string, label: string, definition: string, dependency: string, group: string, page_tag: string}>
     */
    public static function userAbilities(): array
    {
        return [
            ['key' => 'view_data', 'label' => 'View data', 'definition' => 'Read permitted pages, records, dashboards and reports.', 'dependency' => 'View or Full page access', 'group' => 'Data abilities', 'page_tag' => 'Dashboard'],
            ['key' => 'export_data', 'label' => 'Export data', 'definition' => 'Download permitted reports or data.', 'dependency' => 'Page access + Export', 'group' => 'Data abilities', 'page_tag' => 'Dashboard'],
            ['key' => 'create_data', 'label' => 'Create data', 'definition' => 'Create reports, saved views, rules or records.', 'dependency' => 'Full access on relevant page', 'group' => 'Data abilities', 'page_tag' => 'Advanced View'],
            ['key' => 'edit_data', 'label' => 'Edit data', 'definition' => 'Change existing filters, rules, records or settings.', 'dependency' => 'Full access + assigned scope', 'group' => 'Data abilities', 'page_tag' => 'Advanced View'],
            ['key' => 'delete_data', 'label' => 'Delete data', 'definition' => 'Delete records, rules, views or configurations.', 'dependency' => 'Sensitive; confirmation + audit', 'group' => 'Data abilities', 'page_tag' => 'Advanced View'],
            ['key' => 'view_campaigns', 'label' => 'View campaigns', 'definition' => 'See campaign lists and performance in permitted pages.', 'dependency' => 'Traffic Control View or Full', 'group' => 'Campaign abilities', 'page_tag' => 'Traffic Control'],
            ['key' => 'configure_campaigns', 'label' => 'Configure campaigns', 'definition' => 'Change permitted campaign settings.', 'dependency' => 'Campaign page Full access', 'group' => 'Campaign abilities', 'page_tag' => 'Traffic Control'],
            ['key' => 'manage_exclusions', 'label' => 'Manage exclusions', 'definition' => 'Edit suppression, audience or traffic rules.', 'dependency' => 'Traffic Control Full access', 'group' => 'Campaign abilities', 'page_tag' => 'Traffic Control'],
            ['key' => 'view_domains', 'label' => 'View domains', 'definition' => 'See tracked domains and verification status.', 'dependency' => 'Domains View or Full', 'group' => 'Domain abilities', 'page_tag' => 'Domains'],
            ['key' => 'manage_domains', 'label' => 'Add domain', 'definition' => 'Add a domain and start tracking setup.', 'dependency' => 'Domains Full access', 'group' => 'Domain abilities', 'page_tag' => 'Domains'],
            ['key' => 'edit_domain_settings', 'label' => 'Edit domain settings', 'definition' => 'Change domain and tracking configuration.', 'dependency' => 'Domains Full access', 'group' => 'Domain abilities', 'page_tag' => 'Domains'],
            ['key' => 'invite_members', 'label' => 'Invite members', 'definition' => 'Send a workspace invitation.', 'dependency' => 'Owner or Managing Access', 'group' => 'Team abilities', 'page_tag' => 'User Portal'],
            ['key' => 'assign_user_roles', 'label' => 'Assign User Portal roles', 'definition' => 'Assign or change workspace roles.', 'dependency' => 'Cannot exceed actor authority', 'group' => 'Team abilities', 'page_tag' => 'User Portal'],
            ['key' => 'remove_members', 'label' => 'Remove members', 'definition' => 'Suspend or remove workspace members.', 'dependency' => 'Owner or Managing Access', 'group' => 'Team abilities', 'page_tag' => 'User Portal'],
        ];
    }

    /**
     * Admin Portal pages/abilities (platform operators).
     *
     * @return list<array{key: string, label: string, definition: string, recommended: string, sensitive: bool, group: string}>
     */
    public static function adminPages(): array
    {
        return [
            ['key' => 'users_create', 'label' => 'Users & Teams — Create user', 'definition' => 'Create a user and choose portal access.', 'recommended' => 'Super Admin / Full Access', 'sensitive' => false, 'group' => 'Users & Teams'],
            ['key' => 'users_invite', 'label' => 'Users & Teams — Invite user', 'definition' => 'Send an invitation with role and scope.', 'recommended' => 'Full Access', 'sensitive' => false, 'group' => 'Users & Teams'],
            ['key' => 'users_remove', 'label' => 'Users & Teams — Remove access', 'definition' => 'Revoke portal access without deleting history.', 'recommended' => 'Super Admin', 'sensitive' => true, 'group' => 'Users & Teams'],
            ['key' => 'users_suspend', 'label' => 'Users & Teams — Suspend user', 'definition' => 'Block sign-in and active sessions.', 'recommended' => 'Super Admin / Support Admin', 'sensitive' => true, 'group' => 'Users & Teams'],
            ['key' => 'roles_create', 'label' => 'Roles & Permissions — Create role', 'definition' => 'Create a User Portal or Admin Portal role.', 'recommended' => 'Super Admin / Full Access', 'sensitive' => false, 'group' => 'Roles & Permissions'],
            ['key' => 'roles_edit', 'label' => 'Roles & Permissions — Edit role', 'definition' => 'Change pages, abilities or scope.', 'recommended' => 'Super Admin / Full Access', 'sensitive' => false, 'group' => 'Roles & Permissions'],
            ['key' => 'roles_delete', 'label' => 'Roles & Permissions — Delete role', 'definition' => 'Delete a custom role after reassignment.', 'recommended' => 'Super Admin', 'sensitive' => true, 'group' => 'Roles & Permissions'],
            ['key' => 'plans_create', 'label' => 'Plans & Pricing — Create plan', 'definition' => 'Create price, limits and page entitlements.', 'recommended' => 'Super Admin / Billing Admin', 'sensitive' => true, 'group' => 'Billing'],
            ['key' => 'plans_edit', 'label' => 'Plans & Pricing — Edit plan', 'definition' => 'Change price, limits or page entitlements.', 'recommended' => 'Super Admin / Billing Admin', 'sensitive' => true, 'group' => 'Billing'],
            ['key' => 'subscriptions_manage', 'label' => 'Subscriptions — Manage', 'definition' => 'Pause, renew, change or cancel subscriptions.', 'recommended' => 'Super Admin / Billing Admin', 'sensitive' => true, 'group' => 'Billing'],
            ['key' => 'payments_manage', 'label' => 'Payments — Manage billing', 'definition' => 'View invoices, refunds and transactions.', 'recommended' => 'Super Admin / Billing Admin', 'sensitive' => true, 'group' => 'Billing'],
            ['key' => 'security_view', 'label' => 'Security & Logs — View', 'definition' => 'Review access changes and security events.', 'recommended' => 'Security Admin / Super Admin', 'sensitive' => false, 'group' => 'Security'],
            ['key' => 'settings_manage', 'label' => 'System Settings — Manage', 'definition' => 'Change global application settings.', 'recommended' => 'Super Admin', 'sensitive' => true, 'group' => 'Security'],
            ['key' => 'billing_automation', 'label' => 'Billing Automation — Manage', 'definition' => 'Create billing workflows, retries and rules.', 'recommended' => 'Super Admin / Billing Admin', 'sensitive' => true, 'group' => 'Billing'],
        ];
    }

    /**
     * Ability keys that require Full access on a related page.
     *
     * @return array<string, list<string>> ability => required page keys that need full access
     */
    public static function abilityPageRequirements(): array
    {
        return [
            'view_data' => [],
            'export_data' => [],
            'create_data' => [],
            'edit_data' => [],
            'delete_data' => [],
            'view_campaigns' => ['traffic_control', 'dashboard', 'advanced_view'],
            'configure_campaigns' => ['dashboard', 'advanced_view', 'traffic_control'],
            'manage_exclusions' => ['traffic_control'],
            'view_domains' => ['domains'],
            'manage_domains' => ['domains'],
            'edit_domain_settings' => ['domains'],
            'invite_members' => ['settings'],
            'assign_user_roles' => ['settings'],
            'remove_members' => ['settings'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function accessLevels(): array
    {
        return [self::ACCESS_NONE, self::ACCESS_VIEW, self::ACCESS_FULL];
    }

    public static function isValidPortal(string $portal): bool
    {
        return in_array($portal, [self::PORTAL_USER, self::PORTAL_ADMIN], true);
    }

    /**
     * Map wizard page_access (view|full) to existing Permission slugs for User Portal enforcement.
     *
     * @param  array<string, string>  $pageAccess
     * @return list<string>
     */
    public static function permissionSlugsForPageAccess(array $pageAccess): array
    {
        $slugs = [];
        foreach (self::userPages() as $page) {
            $level = $pageAccess[$page['key']] ?? self::ACCESS_NONE;
            if (! in_array($level, [self::ACCESS_VIEW, self::ACCESS_FULL], true)) {
                continue;
            }
            if (! empty($page['permission_slug'])) {
                $slugs[] = $page['permission_slug'];
            }
        }

        return array_values(array_unique($slugs));
    }
}
