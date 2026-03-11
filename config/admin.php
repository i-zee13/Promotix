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
        'users'           => ['route' => 'users',           'label' => 'Users & Teams'],
        'saas-products'   => ['route' => 'saas-products',   'label' => 'SaaS Products'],
        'plans'           => ['route' => 'plans',           'label' => 'Plans & Pricing'],
        'subscriptions'   => ['route' => 'subscriptions',   'label' => 'Subscriptions'],
        'payments'        => ['route' => 'payments',        'label' => 'Payments'],
        'domains-trackers' => ['route' => 'domains-trackers', 'label' => 'Domains & Trackers'],
        'traffic-bot-logs'=> ['route' => 'traffic-bot-logs', 'label' => 'Traffic & Bot Logs'],
        'ip-logs'         => ['route' => 'ip-logs',         'label' => 'IP Logs'],
        'automation'      => ['route' => 'automation',      'label' => 'Automation'],
        'integrations'    => ['route' => 'integrations',    'label' => 'Integrations'],
        'support-system'  => ['route' => 'support-system',  'label' => 'Support System'],
        'analytics'       => ['route' => 'analytics',       'label' => 'Analytics'],
        'security-logs'   => ['route' => 'security-logs',   'label' => 'Security & Logs'],
        'system-settings' => ['route' => 'system-settings', 'label' => 'System Settings'],
        'roles'           => ['route' => 'roles.index',     'label' => 'Roles & Permissions'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Route name => permission slug (for middleware)
    |--------------------------------------------------------------------------
    */
    'route_permission' => [
        'dashboard'        => 'dashboard',
        'users'            => 'users',
        'users.update-role' => 'users',
        'saas-products'    => 'saas-products',
        'plans'            => 'plans',
        'subscriptions'    => 'subscriptions',
        'payments'         => 'payments',
        'domains-trackers' => 'domains-trackers',
        'traffic-bot-logs' => 'traffic-bot-logs',
        'ip-logs'          => 'ip-logs',
        'ip-logs.toggle-block' => 'ip-logs',
        'ip-logs.destroy'  => 'ip-logs',
        'automation'       => 'automation',
        'integrations'     => 'integrations',
        'support-system'   => 'support-system',
        'analytics'        => 'analytics',
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
