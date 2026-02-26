<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class TrafficBotLogsController extends Controller
{
    public function index()
    {
        $rows = [
            ['name' => 'Sarah Collins', 'email' => 'Sarach.collins@email.com', 'bot_score' => 'Pro', 'bot_detail' => 'Monthly $25 / mo.', 'status' => 'Active', 'country' => 'Turkey', 'threat_group' => 'May 24, 2026'],
            ['name' => 'John Doe', 'email' => 'john.doe@email.com', 'bot_score' => 'basic', 'bot_detail' => 'yearly $100 / yr.', 'status' => 'Payment Failed', 'country' => 'United States', 'threat_group' => 'Feb 23, 2026'],
            ['name' => 'Jane Smith', 'email' => 'jane.smith@email.com', 'bot_score' => 'Enterprise', 'bot_detail' => 'Custom', 'status' => 'Pending', 'country' => 'Pakistan', 'threat_group' => 'Mar 15, 2026'],
            ['name' => 'Mike Johnson', 'email' => 'mike.j@email.com', 'bot_score' => 'Pro', 'bot_detail' => 'Monthly $25 / mo.', 'status' => 'Cancelled', 'country' => 'India', 'threat_group' => 'Apr 10, 2026'],
            ['name' => 'Emily Brown', 'email' => 'emily.b@email.com', 'bot_score' => 'Premium', 'bot_detail' => 'Monthly $50 / mo.', 'status' => 'Active', 'country' => 'United States', 'threat_group' => 'May 5, 2026'],
            ['name' => 'David Wilson', 'email' => 'david.w@email.com', 'bot_score' => 'basic', 'bot_detail' => 'yearly $100 / yr.', 'status' => 'Pending', 'country' => 'Turkey', 'threat_group' => 'Jun 1, 2026'],
            ['name' => 'Lisa Davis', 'email' => 'lisa.d@email.com', 'bot_score' => 'Pro', 'bot_detail' => 'Monthly $25 / mo.', 'status' => 'Active', 'country' => 'India', 'threat_group' => 'Feb 19, 2026'],
            ['name' => 'James Miller', 'email' => 'james.m@email.com', 'bot_score' => 'Enterprise', 'bot_detail' => 'Custom', 'status' => 'Payment Failed', 'country' => 'Pakistan', 'threat_group' => 'Mar 28, 2026'],
            ['name' => 'Anna Taylor', 'email' => 'anna.t@email.com', 'bot_score' => 'Pro', 'bot_detail' => 'Monthly $25 / mo.', 'status' => 'Active', 'country' => 'United States', 'threat_group' => 'May 12, 2026'],
            ['name' => 'Chris Anderson', 'email' => 'chris.a@email.com', 'bot_score' => 'basic', 'bot_detail' => 'yearly $100 / yr.', 'status' => 'Cancelled', 'country' => 'Turkey', 'threat_group' => 'Apr 22, 2026'],
        ];

        $total = 56;
        $from = 1;
        $to = 10;
        $statusClasses = [
            'Active' => 'bg-green-600 text-white',
            'Pending' => 'bg-orange-600 text-white',
            'Payment Failed' => 'bg-yellow-600 text-white',
            'Cancelled' => 'bg-red-600 text-white',
        ];

        return view('traffic-bot-logs', compact('rows', 'total', 'from', 'to', 'statusClasses'));
    }
}
