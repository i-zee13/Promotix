<?php

return [
    /*
    | Host substring => customer-facing brand. First match wins.
    | Super Admin / unknown hosts fall back to APP_NAME.
    */
    'hosts' => [
        'clickronix' => [
            'name' => 'Clickronix',
            'logo_dark' => 'images/clickronix-logo-dark.png',
            'logo_light' => 'images/clickronix-logo-light.png',
        ],
        'clickguard' => [
            'name' => 'ClickGuard',
            'logo_dark' => 'images/clickguard-logo-dark.png',
            'logo_light' => 'images/clickguard-logo-light.png',
        ],
    ],
];
