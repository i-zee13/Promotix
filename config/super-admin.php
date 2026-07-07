<?php

return [
    /*
    | Sidebar order matches Figma "Admin design" (S4xxveSTWNNpSly0NPzNLK).
    */
    'menu' => [
        'dashboard'     => ['route' => 'super-admin.dashboard',           'label' => 'Dashboard',          'icon' => 'home'],
        'users'         => ['route' => 'super-admin.users.index',         'label' => 'Users & Teams',      'icon' => 'users'],
        'roles'         => ['route' => 'super-admin.roles.index',         'label' => 'Roles & Permissions','icon' => 'shield'],
        'products'      => ['route' => 'super-admin.products.index',      'label' => 'SaaS Products',      'icon' => 'box'],
        'plans'         => ['route' => 'super-admin.plans.index',         'label' => 'Plans & Pricing',    'icon' => 'tag'],
        'subscriptions' => ['route' => 'super-admin.subscriptions.index', 'label' => 'Subscriptions',      'icon' => 'repeat'],
        'payments'      => ['route' => 'super-admin.payments.index',      'label' => 'Payments',           'icon' => 'card'],
        'domains'       => ['route' => 'super-admin.domains.index',       'label' => 'Domains & Trackers', 'icon' => 'globe'],
        'traffic'       => ['route' => 'super-admin.traffic.index',       'label' => 'Traffic & Bot Logs', 'icon' => 'shield'],
        'automation'    => ['route' => 'super-admin.automation.index',    'label' => 'Automation',         'icon' => 'repeat'],
        'integrations'  => ['route' => 'super-admin.integrations.index',  'label' => 'Integrations',       'icon' => 'plug'],
        'tickets'       => ['route' => 'super-admin.tickets.index',       'label' => 'Support System',     'icon' => 'support'],
        'analytics'     => ['route' => 'super-admin.analytics.index',     'label' => 'Analytics',          'icon' => 'chart'],
        'security'      => ['route' => 'super-admin.security.index',      'label' => 'Security & Logs',    'icon' => 'shield'],
        'settings'      => ['route' => 'super-admin.settings.index',      'label' => 'System Settings',    'icon' => 'settings'],
        'billing-automation' => ['route' => 'super-admin.billing-automation.index', 'label' => 'Billing Automation', 'icon' => 'repeat'],
    ],

    'groups' => [
        'HOME' => ['dashboard'],
        'USERS & BILLING' => ['users', 'roles', 'products', 'plans', 'subscriptions', 'payments'],
        'OPERATIONS' => ['domains', 'traffic', 'automation'],
        'SYSTEM' => ['integrations', 'tickets', 'analytics', 'security', 'settings', 'billing-automation'],
    ],

    'legacy_route_redirects' => [
        'users'            => 'super-admin.users.index',
        'roles.index'      => 'super-admin.roles.index',
        'roles.create'     => 'super-admin.roles.create',
        'roles.edit'       => 'super-admin.roles.edit',
        'saas-products'    => 'super-admin.products.index',
        'plans'            => 'super-admin.plans.index',
        'subscriptions'    => 'super-admin.subscriptions.index',
        'payments'         => 'super-admin.payments.index',
        'domains-trackers' => 'super-admin.domains.index',
        'traffic-bot-logs' => 'super-admin.traffic.index',
        'automation'       => 'super-admin.automation.index',
        'automation.show'  => 'super-admin.automation.index',
        'analytics'        => 'super-admin.analytics.index',
        'security-logs'    => 'super-admin.security.index',
        'system-settings'  => 'super-admin.settings.index',
        'support-system'   => 'super-admin.tickets.index',
        'support-system.show' => 'super-admin.tickets.index',
        'support-system.create' => 'super-admin.tickets.index',
    ],
];
