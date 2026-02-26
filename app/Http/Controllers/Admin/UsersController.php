<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class UsersController extends Controller
{
    public function index()
    {
        $users = [
            ['name' => 'Sarah Collins', 'email' => 'sarach.collins@email.com', 'role' => 'Admin', 'plan' => 'pro', 'status' => 'Active', 'created' => '3/1/2026'],
            ['name' => 'John Doe', 'email' => 'john.doe@email.com', 'role' => 'Member', 'plan' => 'basic', 'status' => 'Active', 'created' => '2/1/2026'],
            ['name' => 'Jane Smith', 'email' => 'jane.smith@email.com', 'role' => 'Team Lead', 'plan' => 'enterprise', 'status' => 'Suspended', 'created' => '2/1/2026'],
            ['name' => 'Mike Johnson', 'email' => 'mike.j@email.com', 'role' => 'Member', 'plan' => 'trial', 'status' => 'Pending', 'created' => '1/1/2026'],
            ['name' => 'Emily Brown', 'email' => 'emily.b@email.com', 'role' => 'Member', 'plan' => 'custom', 'status' => 'Active', 'created' => '1/1/2026'],
            ['name' => 'David Wilson', 'email' => 'david.w@email.com', 'role' => 'Member', 'plan' => 'basic', 'status' => 'Ban', 'created' => '31/12/2025'],
            ['name' => 'Lisa Davis', 'email' => 'lisa.d@email.com', 'role' => 'Admin', 'plan' => 'pro', 'status' => 'Active', 'created' => '30/12/2025'],
            ['name' => 'James Miller', 'email' => 'james.m@email.com', 'role' => 'Member', 'plan' => 'trial', 'status' => 'Pending', 'created' => '29/12/2025'],
            ['name' => 'Anna Taylor', 'email' => 'anna.t@email.com', 'role' => 'Team Lead', 'plan' => 'enterprise', 'status' => 'Active', 'created' => '28/12/2025'],
            ['name' => 'Chris Anderson', 'email' => 'chris.a@email.com', 'role' => 'Member', 'plan' => 'basic', 'status' => 'Active', 'created' => '27/12/2025'],
        ];

        $total = 56;
        $from = 1;
        $to = 10;
        $planClasses = [
            'basic' => 'bg-gray-700 text-white',
            'trial' => 'bg-indigo-600 text-white',
            'pro' => 'bg-purple-600 text-white',
            'enterprise' => 'bg-pink-600 text-white',
            'custom' => 'bg-yellow-600 text-gray-900',
        ];
        $statusClasses = [
            'Active' => 'bg-green-600 text-white',
            'Suspended' => 'bg-red-600 text-white',
            'Pending' => 'bg-orange-600 text-white',
            'Ban' => 'bg-red-800 text-white',
        ];

        return view('users', compact('users', 'total', 'from', 'to', 'planClasses', 'statusClasses'));
    }
}
