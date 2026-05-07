<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Admin menu items: permission slug => [route name, label]
    | Order here defines sidebar order. User must have permission to see each item.
    |--------------------------------------------------------------------------
    */
    'menu' => [
        'dashboard'       => ['route' => 'dashboard',       'label' => 'Dashboard'],
        'paid-marketing-detailed' => ['route' => 'paid-marketing.detailed', 'label' => 'Paid Marketing'],
        'paid-marketing-platform-connections' => ['route' => 'integrations', 'label' => 'Platform Connections', 'hidden' => true],
        'paid-marketing-detection-settings' => ['route' => 'paid-marketing.detection-settings', 'label' => 'Detection Settings'],
        'bot-protection' => ['route' => 'bot-protection.dashboard', 'label' => 'Bot Protection'],
        'domain-management' => ['route' => 'domains.index', 'label' => 'Domain Management'],
        'users'           => ['route' => 'users',           'label' => 'Users & Teams'],
        // Extra modules (kept in routes, hidden from sidebar until enabled)
        // 'saas-products'   => ['route' => 'saas-products',   'label' => 'SaaS Products'],
        // 'plans'           => ['route' => 'plans',           'label' => 'Plans & Pricing'],
        // 'subscriptions'   => ['route' => 'subscriptions',   'label' => 'Subscriptions'],
        // 'payments'        => ['route' => 'payments',        'label' => 'Payments'],
        // 'domains-trackers' => ['route' => 'domains-trackers', 'label' => 'Domains & Trackers'],
        // 'traffic-bot-logs'=> ['route' => 'traffic-bot-logs', 'label' => 'Traffic & Bot Logs'],
        'ip-logs'         => ['route' => 'ip-logs',         'label' => 'Bot Mitigation'], // view list, toggle block, delete IPs
        // 'automation'      => ['route' => 'automation',      'label' => 'Automation'],
        // 'integrations'    => ['route' => 'integrations',    'label' => 'Integrations'],
        // 'support-system'  => ['route' => 'support-system',  'label' => 'Support System'],
        // 'analytics'       => ['route' => 'analytics',       'label' => 'Analytics'],
        // 'security-logs'   => ['route' => 'security-logs',   'label' => 'Security & Logs'],
        // 'system-settings' => ['route' => 'system-settings', 'label' => 'System Settings'],
        'roles'           => ['route' => 'roles.index',     'label' => 'Roles & Permissions'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Route name => permission slug (for middleware)
    |--------------------------------------------------------------------------
    */
    'route_permission' => [
        'dashboard'        => 'dashboard',
        'paid-marketing.detailed' => 'paid-marketing-detailed',
        'paid-marketing.detection-settings' => 'paid-marketing-detection-settings',
        'paid-marketing.detection-settings.update' => 'paid-marketing-detection-settings',
        'bot-protection.dashboard' => 'bot-protection',
        'bot-protection.advanced' => 'bot-protection',
        'bot-protection.export' => 'bot-protection',
        'domains.index'     => 'domain-management',
        'domains.store'     => 'domain-management',
        'domains.setup'     => 'domain-management',
        'domains.wp-plugin' => 'domain-management',
        'users'            => 'users',
        'users.update-role' => 'users',
        // Extra modules (kept in routes, access can be enabled later)
        // 'saas-products'    => 'saas-products',
        // 'plans'            => 'plans',
        // 'subscriptions'    => 'subscriptions',
        // 'payments'         => 'payments',
        // 'domains-trackers' => 'domains-trackers',
        // 'traffic-bot-logs' => 'traffic-bot-logs',
        // IP management (list, block/unblock, delete)
        'ip-logs'          => 'ip-logs',
        'ip-logs.toggle-block' => 'ip-logs',
        'ip-logs.destroy'  => 'ip-logs',
        // 'automation'       => 'automation',
        'integrations'     => 'paid-marketing-platform-connections',
        'integrations.google.redirect' => 'paid-marketing-platform-connections',
        'integrations.google.callback' => 'paid-marketing-platform-connections',
        'integrations.google.sync-accounts' => 'paid-marketing-platform-connections',
        'integrations.google.disconnect' => 'paid-marketing-platform-connections',
        'integrations.store-account' => 'paid-marketing-platform-connections',
        'integrations.store-mapping' => 'paid-marketing-platform-connections',
        'integrations.destroy-mapping' => 'paid-marketing-platform-connections',
        // 'support-system'   => 'support-system',
        // 'analytics'        => 'analytics',
        // 'security-logs'    => 'security-logs',
        // 'system-settings'  => 'system-settings',
        'roles.index'      => 'roles',
        'roles.create'     => 'roles',
        'roles.store'      => 'roles',
        'roles.edit'       => 'roles',
        'roles.update'     => 'roles',
        'roles.destroy'    => 'roles',
    ],
];
