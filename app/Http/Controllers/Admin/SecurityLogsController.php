<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class SecurityLogsController extends Controller
{
    public function index()
    {
        $rows = [
            ['type' => 'Login Success', 'type_icon' => 'check', 'user' => 'David Miller', 'tag' => 'Login 81.182.52.31', 'details' => 'Login Success', 'ip' => '81.182.52.31', 'country' => 'Turkey', 'time' => '18 minutes ago', 'status' => 'Successful'],
            ['type' => 'Api.Keys/write', 'type_icon' => 'code', 'user' => 'Sarach.collins@email.com', 'tag' => 'Account Suspended', 'details' => 'Suspicious Login', 'ip' => '209.45.77.81', 'country' => 'United States', 'time' => '22 minutes ago', 'status' => 'Suspicious'],
            ['type' => 'GET /v1/payment/read', 'type_icon' => 'code', 'user' => 'David Miller', 'tag' => 'User Banned', 'details' => 'User Banned', 'ip' => '135.245.83.27', 'country' => 'Pakistan', 'time' => '1 hour ago', 'status' => 'Banned'],
            ['type' => 'Login Success', 'type_icon' => 'check', 'user' => 'Sarach.collins@email.com', 'tag' => 'Password changed', 'details' => 'Password changed', 'ip' => '76.219.54.179', 'country' => 'India', 'time' => '2 hours ago', 'status' => 'Successful'],
            ['type' => 'Api.Keys/write', 'type_icon' => 'folder', 'user' => 'David Miller', 'tag' => 'Login 81.182.52.31', 'details' => 'GET /v1/payment/read', 'ip' => '81.182.52.31', 'country' => 'Turkey', 'time' => '3 hours ago', 'status' => 'Integrated'],
            ['type' => 'Payment', 'type_icon' => 'payment', 'user' => 'Sarach.collins@email.com', 'tag' => 'Account Active', 'details' => 'Api.Keys/write', 'ip' => '209.45.77.81', 'country' => 'United States', 'time' => '5 hours ago', 'status' => 'Payment'],
            ['type' => 'Login Success', 'type_icon' => 'check', 'user' => 'David Miller', 'tag' => 'Login Success', 'details' => 'Login Success', 'ip' => '135.245.83.27', 'country' => 'Pakistan', 'time' => 'Yesterday', 'status' => 'Successful'],
            ['type' => 'GET /v1/payment/read', 'type_icon' => 'code', 'user' => 'Sarach.collins@email.com', 'tag' => 'Suspicious Login', 'details' => 'Suspicious Login', 'ip' => '76.219.54.179', 'country' => 'India', 'time' => 'Yesterday', 'status' => 'Suspicious'],
            ['type' => 'Api.Keys/write', 'type_icon' => 'info', 'user' => 'David Miller', 'tag' => 'Password changed', 'details' => 'Password changed', 'ip' => '81.182.52.31', 'country' => 'Turkey', 'time' => '2 days ago', 'status' => 'Integrated'],
            ['type' => 'Login Success', 'type_icon' => 'check', 'user' => 'Sarach.collins@email.com', 'tag' => 'Login 209.45.77.81', 'details' => 'Login Success', 'ip' => '209.45.77.81', 'country' => 'United States', 'time' => '2 days ago', 'status' => 'Successful'],
        ];

        $total = 56;
        $from = 1;
        $to = 10;
        $statusClasses = [
            'Successful' => 'bg-green-600 text-white',
            'Suspicious' => 'bg-yellow-600 text-white',
            'Banned' => 'bg-red-600 text-white',
            'Integrated' => 'bg-blue-600 text-white',
            'Payment' => 'bg-purple-600 text-white',
        ];

        return view('security-logs', compact('rows', 'total', 'from', 'to', 'statusClasses'));
    }
}
