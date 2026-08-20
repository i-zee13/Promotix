<?php

return [

    /*
    | Dedicated Google Ads tracking-template host (Transparent Click Tracker).
    | DNS for this host should point at the same app. Empty = use APP_URL.
    */
    'url' => rtrim((string) env('CLICK_TRACKER_URL', 'https://track.clickronix.com'), '/'),

];
