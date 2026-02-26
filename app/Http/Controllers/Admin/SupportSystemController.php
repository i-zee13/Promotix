<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class SupportSystemController extends Controller
{
    public function index()
    {
        $rows = [
            ['id' => '4263', 'name' => 'Sarah Collins', 'email' => 'Sarach.collins@email.com', 'status' => 'Open', 'priority' => 'High', 'agent' => 'Sarah Collins', 'last_update' => '26 minutes ago', 'sla' => '2.24 hrs remaining'],
            ['id' => '4213', 'name' => 'Sarah Collins', 'email' => 'Sarach.collins@email.com', 'status' => 'Waiting', 'priority' => 'Medium', 'agent' => 'Sarah Collins', 'last_update' => '36 minutes ago', 'sla' => '6.24 hrs remaining'],
            ['id' => '4223', 'name' => 'Sarah Collins', 'email' => 'Sarach.collins@email.com', 'status' => 'Resolved', 'priority' => 'Low', 'agent' => 'Sarah Collins', 'last_update' => '51 minutes ago', 'sla' => 'Today'],
            ['id' => '4240', 'name' => 'Sarah Collins', 'email' => 'Sarach.collins@email.com', 'status' => 'Open', 'priority' => 'High', 'agent' => 'Sarah Collins', 'last_update' => 'Yesterday', 'sla' => '2.24 hrs remaining'],
            ['id' => '4251', 'name' => 'Sarah Collins', 'email' => 'Sarach.collins@email.com', 'status' => 'Waiting', 'priority' => 'Medium', 'agent' => 'Sarah Collins', 'last_update' => '1 hour ago', 'sla' => 'Today'],
            ['id' => '4260', 'name' => 'Sarah Collins', 'email' => 'Sarach.collins@email.com', 'status' => 'Open', 'priority' => 'Low', 'agent' => 'Sarah Collins', 'last_update' => 'Today', 'sla' => '6.24 hrs remaining'],
            ['id' => '4261', 'name' => 'Sarah Collins', 'email' => 'Sarach.collins@email.com', 'status' => 'Resolved', 'priority' => 'Medium', 'agent' => 'Sarah Collins', 'last_update' => '26 minutes ago', 'sla' => 'Today'],
            ['id' => '4262', 'name' => 'Sarah Collins', 'email' => 'Sarach.collins@email.com', 'status' => 'Waiting', 'priority' => 'High', 'agent' => 'Sarah Collins', 'last_update' => 'Yesterday', 'sla' => '2.24 hrs remaining'],
            ['id' => '4264', 'name' => 'Sarah Collins', 'email' => 'Sarach.collins@email.com', 'status' => 'Open', 'priority' => 'Medium', 'agent' => 'Sarah Collins', 'last_update' => '1 hour ago', 'sla' => 'Today'],
            ['id' => '4265', 'name' => 'Sarah Collins', 'email' => 'Sarach.collins@email.com', 'status' => 'Resolved', 'priority' => 'Low', 'agent' => 'Sarah Collins', 'last_update' => '36 minutes ago', 'sla' => 'Today'],
        ];

        $total = 56;
        $from = 1;
        $to = 10;
        $statusClasses = [
            'Open' => 'bg-green-600 text-white',
            'Waiting' => 'bg-yellow-600 text-white',
            'Resolved' => 'bg-gray-600 text-white',
        ];
        $priorityClasses = [
            'High' => 'bg-red-600 text-white',
            'Medium' => 'bg-yellow-600 text-white',
            'Low' => 'bg-green-700 text-white',
        ];

        return view('support-system', compact('rows', 'total', 'from', 'to', 'statusClasses', 'priorityClasses'));
    }
}
