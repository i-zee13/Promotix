<?php

return [
    'menu' => [
        'dashboard'     => ['route' => 'super-admin.dashboard',           'label' => 'Dashboard',          'icon' => 'home'],
        'users'         => ['route' => 'super-admin.users.index',         'label' => 'Users & Teams',     'icon' => 'users'],
        'products'      => ['route' => 'super-admin.products.index',      'label' => 'SaaS Products',     'icon' => 'box'],
        'plans'         => ['route' => 'super-admin.plans.index',         'label' => 'Plans & Pricing',   'icon' => 'tag'],
        'subscriptions' => ['route' => 'super-admin.subscriptions.index', 'label' => 'Subscriptions',     'icon' => 'repeat'],
        'payments'      => ['route' => 'super-admin.payments.index',      'label' => 'Payments',          'icon' => 'card'],
        'domains'       => ['route' => 'super-admin.domains.index',       'label' => 'Domains & Trackers','icon' => 'globe'],
        'analytics'     => ['route' => 'super-admin.analytics.index',     'label' => 'Analytics',         'icon' => 'chart'],
        'security'      => ['route' => 'super-admin.security.index',      'label' => 'Security & Logs',   'icon' => 'shield'],
        'tickets'       => ['route' => 'super-admin.tickets.index',       'label' => 'Support Tickets',   'icon' => 'support'],
        'settings'      => ['route' => 'super-admin.settings.index',      'label' => 'System Settings',   'icon' => 'settings'],
    ],
];
