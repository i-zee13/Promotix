<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class AutomationController extends Controller
{
    public function index()
    {
        $items = [
            [
                'title' => 'Suspend Unpaid Users',
                'description' => 'suspend users with unpaid invoices.',
                'schedule' => 'Daily at 3:00 Am',
                'queue_badge' => 'Queue Healthy',
                'queue_healthy' => true,
                'status' => 'Active',
                'icon' => 'exclamation',
                'middle_title' => 'Queue Monitor:',
                'middle_bars' => true,
                'middle_badges' => null,
                'right_title' => 'Queue Monitor:',
                'right_pills' => true,
                'right_grid' => false,
            ],
            [
                'title' => 'Auto-Delete Old data',
                'description' => 'Delete data older than 6 months.',
                'schedule' => 'weekly at 3:00 Am',
                'queue_badge' => 'Queue Healthy',
                'queue_healthy' => true,
                'status' => 'Active',
                'icon' => 'trash',
                'middle_title' => 'Status:',
                'middle_bars' => true,
                'middle_badges' => null,
                'right_title' => 'Queue Pointer:',
                'right_pills' => true,
                'right_grid' => false,
            ],
            [
                'title' => 'Auto-Sync Google Ads',
                'description' => 'Sync with Google Ads data.',
                'schedule' => 'Hourly at 3:00 Am',
                'queue_badge' => 'Queue Paused',
                'queue_healthy' => false,
                'status' => 'Paused',
                'icon' => 'google',
                'middle_title' => 'Queue schedule:',
                'middle_bars' => false,
                'middle_badges' => ['Queue Paused'],
                'right_title' => null,
                'right_pills' => false,
                'right_grid' => true,
            ],
            [
                'title' => 'Retry Failed Jobs',
                'description' => 'Retry any failed Queue jobs.',
                'schedule' => 'Hourly at 5:00 Am',
                'queue_badge' => 'Queue Healthy',
                'queue_healthy' => true,
                'status' => 'Active',
                'icon' => 'refresh',
                'middle_title' => 'Status:',
                'middle_bars' => false,
                'middle_badges' => ['27 Retrying', 'Phase 6'],
                'right_title' => null,
                'right_pills' => false,
                'right_grid' => true,
            ],
        ];

        $total = 56;
        $from = 1;
        $to = 10;

        return view('automation', compact('items', 'total', 'from', 'to'));
    }
}
