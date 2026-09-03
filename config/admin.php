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
        'paid-marketing-dashboard' => ['route' => 'paid-marketing.dashboard', 'label' => 'Paid Ads Dashboard',          'icon' => 'chart'],
        'paid-marketing-detailed' => ['route' => 'paid-marketing.detailed', 'label' => 'Advanced View',       'icon' => 'eye'],
        'paid-marketing-platform-connections' => ['route' => 'integrations', 'label' => 'Platform Integrate', 'icon' => 'plug'],
        'paid-marketing-detection-settings' => ['route' => 'paid-marketing.detection-settings', 'label' => 'Detection Panel', 'icon' => 'shield-check'],
        'bot-protection' => ['route' => 'analytics.dashboard', 'label' => 'Analytics Dashboard',  'icon' => 'shield'],
        'domain-management' => ['route' => 'domains.index', 'label' => 'Domains',           'icon' => 'globe'],
        'upgrade-plan'      => ['route' => 'billing.index', 'label' => 'Billing',           'icon' => 'card'],
        'support-system'    => ['route' => 'support-system', 'label' => 'Support',          'icon' => 'support'],
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
                'paid-marketing-dashboard'         => ['route' => 'paid-marketing.dashboard',          'label' => 'Paid Ads Dashboard',          'icon' => 'chart'],
                'paid-marketing-detailed'          => ['route' => 'paid-marketing.detailed',           'label' => 'Advanced View',       'icon' => 'eye'],
                'paid-marketing-platform-connections' => ['route' => 'integrations',                   'label' => 'Platform Integrate', 'icon' => 'plug'],
                'paid-marketing-detection-settings'=> ['route' => 'paid-marketing.detection-settings', 'label' => 'Detection Panel',     'icon' => 'shield-check'],
            ],
        ],
        [
            'label' => 'ANALYTICS',
            'items' => [
                'bot-protection' => ['route' => 'analytics.dashboard', 'label' => 'Dashboard', 'icon' => 'home'],
                // Traffic Control shares the bot-protection permission slug — duplicate is OK because
                // canAccess() checks the slug, not the route.
                'bot-protection-advanced-alias' => ['route' => 'analytics.traffic-control', 'label' => 'Traffic Control', 'icon' => 'eye', 'permission' => 'bot-protection'],
                'bot-protection-journeys-alias' => ['route' => 'analytics.journeys', 'label' => 'Journeys', 'icon' => 'repeat', 'permission' => 'bot-protection'],
                'bot-protection-sources-alias' => ['route' => 'analytics.sources', 'label' => 'Sources', 'icon' => 'globe', 'permission' => 'bot-protection'],
                'bot-protection-sales-alias' => ['route' => 'analytics.sales', 'label' => 'Sales', 'icon' => 'card', 'permission' => 'bot-protection'],
            ],
        ],
        [
            'label' => 'SITE MANAGEMENT',
            'items' => [
                'domain-management' => ['route' => 'domains.index', 'label' => 'Domains', 'icon' => 'globe'],
                'upgrade-plan' => ['route' => 'billing.index', 'label' => 'Billing', 'icon' => 'card'],
                'support-system' => ['route' => 'support-system', 'label' => 'Support', 'icon' => 'support'],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Roles UI permission picker groups (Product vs Advanced View)
    | Sidebar nav still uses `groups` above; this only drives role create/edit.
    |--------------------------------------------------------------------------
    */
    'permission_groups' => [
        [
            'label' => 'PRODUCT',
            'slugs' => [
                'dashboard',
                'paid-marketing-dashboard',
                'paid-marketing-platform-connections',
                'paid-marketing-detection-settings',
                'bot-protection',
                'domain-management',
                'upgrade-plan',
                'team-invite',
                'provider-ip-whitelist',
            ],
        ],
        [
            'label' => 'ADVANCED VIEW',
            'slugs' => [
                'paid-marketing-detailed',
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
        'reports.index'    => 'dashboard',
        'paid-marketing.detailed' => 'paid-marketing-detailed',
        'paid-marketing.detailed-visits' => 'paid-marketing-detailed',
        'paid-marketing.detailed-export' => 'paid-marketing-detailed',
        'paid-marketing.devices-export-xlsx' => 'paid-marketing-detailed',
        'paid-marketing.session-recording' => 'paid-marketing-detailed',
        'paid-marketing.dashboard' => 'paid-marketing-dashboard',
        'paid-marketing.detection-settings' => 'paid-marketing-detection-settings',
        'paid-marketing.detection-settings.update' => 'paid-marketing-detection-settings',
        'paid-marketing.detection-settings.google-exclusion.push' => 'paid-marketing-detection-settings',
        'paid-marketing.detection-settings.google-exclusion.push-row' => 'paid-marketing-detection-settings',
        'paid-marketing.detection-settings.google-exclusion.push-bulk' => 'paid-marketing-detection-settings',
        'paid-marketing.detection-settings.google-exclusion.sync' => 'paid-marketing-detection-settings',
        'bot-protection.dashboard' => 'bot-protection',
        'bot-protection.advanced' => 'bot-protection',
        'bot-protection.export' => 'bot-protection',
        'analytics.dashboard' => 'bot-protection',
        'analytics.journeys' => 'bot-protection',
        'analytics.sources' => 'bot-protection',
        'analytics.sales' => 'bot-protection',
        'analytics.traffic-control' => 'bot-protection',
        'domains.index'     => 'domain-management',
        'domains.store'     => 'domain-management',
        'domains.update'    => 'domain-management',
        'domains.destroy'   => 'domain-management',
        'domains.setup'     => 'domain-management',
        'domains.wp-plugin' => 'domain-management',
        'billing.index'     => 'upgrade-plan',
        'billing.submit'    => 'upgrade-plan',
        'billing.payment-methods.store' => 'upgrade-plan',
        'billing.payment-methods.primary' => 'upgrade-plan',
        'billing.payment-methods.destroy' => 'upgrade-plan',
        'upgrade-plan'      => 'upgrade-plan',
        'upgrade-plan.submit' => 'upgrade-plan',
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
        // Customer Support inbox is available to every portal user (like Billing).
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
