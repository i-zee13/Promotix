<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Admin menu items: permission slug => [route name, label]
    | Order here defines fallback order. User must have permission to see each item.
    |--------------------------------------------------------------------------
    */
    'menu' => [
        'dashboard'       => ['route' => 'dashboard',       'label' => 'Overview',          'icon' => 'home'],
        'paid-marketing-dashboard' => ['route' => 'paid-marketing.dashboard', 'label' => 'Dashboard',          'icon' => 'chart'],
        'paid-marketing-detailed' => ['route' => 'paid-marketing.detailed', 'label' => 'Advanced View',       'icon' => 'eye'],
        'paid-marketing-platform-connections' => ['route' => 'integrations', 'label' => 'Platform Integrate', 'icon' => 'plug', 'hidden' => true],
        'paid-marketing-detection-settings' => ['route' => 'paid-marketing.detection-settings', 'label' => 'Detection Panel', 'icon' => 'shield-check'],
        'bot-protection' => ['route' => 'bot-protection.dashboard', 'label' => 'Dashboard',  'icon' => 'shield'],
        'domain-management' => ['route' => 'domains.index', 'label' => 'Domains',            'icon' => 'globe'],
        'users'           => ['route' => 'users',           'label' => 'Users & Teams',     'icon' => 'users'],
        'ip-logs'         => ['route' => 'ip-logs',         'label' => 'Bot Mitigation',    'icon' => 'shield-x'],
        'roles'           => ['route' => 'roles.index',     'label' => 'Roles & Permissions','icon' => 'key'],
        'traffic-bot-logs' => ['route' => 'traffic-bot-logs', 'label' => 'Traffic Logs',     'icon' => 'chart'],
        'automation'      => ['route' => 'automation',      'label' => 'Automation',        'icon' => 'repeat'],
        'integrations'    => ['route' => 'integrations',    'label' => 'Integrations',      'icon' => 'plug'],
        'support-system'  => ['route' => 'support-system',  'label' => 'Support',           'icon' => 'users'],
        'security-logs'   => ['route' => 'security-logs',   'label' => 'Security Logs',     'icon' => 'shield'],
        'system-settings' => ['route' => 'system-settings', 'label' => 'Settings',          'icon' => 'settings'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Grouped sidebar layout (Batch A revamp)
    | Each entry references permission slugs from `menu` above. The sidebar
    | renderer uses this if present, otherwise falls back to the flat `menu`.
    | Override the default labels/routes/icons by re-declaring them here.
    |--------------------------------------------------------------------------
    */
    'groups' => [
        [
            'label' => 'HOME',
            'items' => [
                'dashboard' => ['route' => 'dashboard', 'label' => 'Overview', 'icon' => 'home'],
            ],
        ],
        [
            'label' => 'PAID ADVERTISING',
            'items' => [
                'paid-marketing-dashboard'         => ['route' => 'paid-marketing.dashboard',          'label' => 'Dashboard',          'icon' => 'chart'],
                'paid-marketing-detailed'          => ['route' => 'paid-marketing.detailed',           'label' => 'Advanced View',       'icon' => 'eye'],
                'paid-marketing-platform-connections' => ['route' => 'integrations',                   'label' => 'Platform Integrate', 'icon' => 'plug'],
                'paid-marketing-detection-settings'=> ['route' => 'paid-marketing.detection-settings', 'label' => 'Detection Panel',     'icon' => 'shield-check'],
            ],
        ],
        [
            'label' => 'BOT PROTECTION',
            'items' => [
                'bot-protection' => ['route' => 'bot-protection.dashboard', 'label' => 'Dashboard',     'icon' => 'shield'],
                // Advanced View shares the bot-protection permission slug — duplicate is OK because
                // canAccess() checks the slug, not the route.
                'bot-protection-advanced-alias' => ['route' => 'bot-protection.advanced', 'label' => 'Advanced View', 'icon' => 'eye', 'permission' => 'bot-protection'],
            ],
        ],
        [
            'label' => 'SITE MANAGEMENT',
            'items' => [
                'domain-management' => ['route' => 'domains.index', 'label' => 'Domains',          'icon' => 'globe'],
                'users'             => ['route' => 'users',         'label' => 'Users & Teams',   'icon' => 'users'],
                'roles'             => ['route' => 'roles.index',   'label' => 'Roles',           'icon' => 'key'],
                'ip-logs'           => ['route' => 'ip-logs',       'label' => 'Bot Mitigation',  'icon' => 'shield-x'],
            ],
        ],
        [
            'label' => 'ADMIN OPS',
            'items' => [
                'traffic-bot-logs' => ['route' => 'traffic-bot-logs', 'label' => 'Traffic Logs',  'icon' => 'chart'],
                'automation'       => ['route' => 'automation',       'label' => 'Automation',    'icon' => 'repeat'],
                'integrations'     => ['route' => 'integrations',     'label' => 'Integrations',  'icon' => 'plug'],
                'support-system'   => ['route' => 'support-system',   'label' => 'Support',       'icon' => 'users'],
                'security-logs'    => ['route' => 'security-logs',    'label' => 'Security Logs', 'icon' => 'shield'],
                'system-settings'  => ['route' => 'system-settings',  'label' => 'Settings',      'icon' => 'settings'],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Route name => permission slug (for middleware)
    |--------------------------------------------------------------------------
    */
    'route_permission' => [
        'dashboard'        => 'dashboard',
        'paid-marketing.detailed' => 'paid-marketing-detailed',
        'paid-marketing.dashboard' => 'paid-marketing-dashboard',
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
        'ip-logs'          => 'ip-logs',
        'ip-logs.toggle-block' => 'ip-logs',
        'ip-logs.destroy'  => 'ip-logs',
        'integrations'     => 'paid-marketing-platform-connections',
        'integrations.google.redirect' => 'paid-marketing-platform-connections',
        'integrations.google.callback' => 'paid-marketing-platform-connections',
        'integrations.google.sync-accounts' => 'paid-marketing-platform-connections',
        'integrations.google.disconnect' => 'paid-marketing-platform-connections',
        'integrations.store-account' => 'paid-marketing-platform-connections',
        'integrations.store-mapping' => 'paid-marketing-platform-connections',
        'integrations.destroy-mapping' => 'paid-marketing-platform-connections',
        'traffic-bot-logs' => 'traffic-bot-logs',
        'automation'       => 'automation',
        'automation.show'  => 'automation',
        'integrations'     => 'integrations',
        'support-system'   => 'support-system',
        'support-system.show' => 'support-system',
        'support-system.create' => 'support-system',
        'support-system.store' => 'support-system',
        'security-logs'    => 'security-logs',
        'system-settings'  => 'system-settings',
        'roles.index'      => 'roles',
        'roles.create'     => 'roles',
        'roles.store'      => 'roles',
        'roles.edit'       => 'roles',
        'roles.update'     => 'roles',
        'roles.destroy'    => 'roles',
    ],
];
