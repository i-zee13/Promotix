<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class AnalyticsController extends Controller
{
    public function index()
    {
        $usageRows = [
            ['date' => 'Apr 24', 'active_users' => '2,412', 'events' => '16,531'],
            ['date' => 'Apr 23', 'active_users' => '2,308', 'events' => '15,729'],
            ['date' => 'Apr 22', 'active_users' => '2,275', 'events' => '14,670'],
            ['date' => 'Apr 21', 'active_users' => '2,031', 'events' => '13,315'],
            ['date' => 'Apr 20', 'active_users' => '1,982', 'events' => '12,840'],
        ];

        return view('analytics', compact('usageRows'));
    }
}
