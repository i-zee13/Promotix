<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class SystemSettingsController extends Controller
{
    public function index()
    {
        $rows = [
            ['type' => 'Login Success', 'type_icon' => 'check', 'user' => 'Sarach.collins@email.com', 'tag' => 'Login 81.182.52.31', 'details' => 'Login Success', 'ip' => '81.182.52.31', 'country' => 'Turkey', 'time' => '18 minutes ago', 'status' => 'Successful'],
            ['type' => 'Suspicious Login', 'type_icon' => 'info', 'user' => 'Sarach.collins@email.com', 'tag' => 'Account Suspended', 'details' => 'Suspicious Login', 'ip' => '209.45.77.81', 'country' => 'United States', 'time' => '22 minutes ago', 'status' => 'Payment Failed'],
            ['type' => 'User Banned', 'type_icon' => 'folder', 'user' => 'Sarach.collins@email.com', 'tag' => 'User Banned', 'details' => 'User Banned', 'ip' => '135.245.83.27', 'country' => 'Pakistan', 'time' => '23 minutes ago', 'status' => 'Pending'],
            ['type' => 'Password changed', 'type_icon' => 'check', 'user' => 'Sarach.collins@email.com', 'tag' => 'Password changed', 'details' => 'Password changed', 'ip' => '76.219.54.179', 'country' => 'India', 'time' => '24 minutes ago', 'status' => 'Active'],
            ['type' => 'GET /v1/payment/read', 'type_icon' => 'code', 'user' => 'Sarach.collins@email.com', 'tag' => 'API access', 'details' => 'GET /v1/payment/read', 'ip' => '81.182.52.31', 'country' => 'Turkey', 'time' => '26 minutes ago', 'status' => 'Successful'],
            ['type' => 'Api.Keys/write', 'type_icon' => 'code', 'user' => 'Sarach.collins@email.com', 'tag' => 'Keys updated', 'details' => 'Api.Keys/write', 'ip' => '209.45.77.81', 'country' => 'United States', 'time' => '36 minutes ago', 'status' => 'Cancelled'],
            ['type' => 'Login Success', 'type_icon' => 'check', 'user' => 'Sarach.collins@email.com', 'tag' => 'Login Success', 'details' => 'Login Success', 'ip' => '135.245.83.27', 'country' => 'Pakistan', 'time' => '51 minutes ago', 'status' => 'Successful'],
            ['type' => 'Suspicious Login', 'type_icon' => 'info', 'user' => 'Sarach.collins@email.com', 'tag' => 'Suspicious Login', 'details' => 'Suspicious Login', 'ip' => '76.219.54.179', 'country' => 'India', 'time' => '57 minutes ago', 'status' => 'Payment Failed'],
            ['type' => 'Password changed', 'type_icon' => 'check', 'user' => 'Sarach.collins@email.com', 'tag' => 'Password changed', 'details' => 'Password changed', 'ip' => '81.182.52.31', 'country' => 'Turkey', 'time' => '1 hour ago', 'status' => 'Active'],
        ];

        $total = 56;
        $from = 1;
        $to = 10;
        $statusClasses = [
            'Successful' => 'bg-green-600 text-white',
            'Payment Failed' => 'bg-yellow-600 text-white',
            'Pending' => 'bg-blue-600 text-white',
            'Active' => 'bg-purple-600 text-white',
            'Cancelled' => 'bg-red-600 text-white',
        ];

        return view('system-settings', compact('rows', 'total', 'from', 'to', 'statusClasses'));
    }
}
