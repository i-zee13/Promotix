<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class SubscriptionsController extends Controller
{
    public function index()
    {
        $subscriptions = [
            ['name' => 'Sarah Collins', 'email' => 'Sarach.collins@email.com', 'plan' => 'Pro', 'billing' => 'Monthly $25 / mo.', 'status' => 'Active', 'next_payment' => 'May 24, 2026'],
            ['name' => 'John Doe', 'email' => 'john.doe@email.com', 'plan' => 'basic', 'billing' => 'yearly $100 / yr.', 'status' => 'Payment Failed', 'next_payment' => 'Feb 23, 2026'],
            ['name' => 'Jane Smith', 'email' => 'jane.smith@email.com', 'plan' => 'Enterprise', 'billing' => 'Custom', 'status' => 'Pending', 'next_payment' => 'Feb 19, 2026'],
            ['name' => 'Mike Johnson', 'email' => 'mike.j@email.com', 'plan' => 'Pro', 'billing' => 'Monthly $25 / mo.', 'status' => 'Cancelled', 'next_payment' => '—'],
            ['name' => 'Emily Brown', 'email' => 'emily.b@email.com', 'plan' => 'Premium', 'billing' => 'Monthly $50 / mo.', 'status' => 'Active', 'next_payment' => 'Mar 15, 2026'],
            ['name' => 'David Wilson', 'email' => 'david.w@email.com', 'plan' => 'basic', 'billing' => 'yearly $100 / yr.', 'status' => 'Paused', 'next_payment' => 'Apr 1, 2026'],
            ['name' => 'Lisa Davis', 'email' => 'lisa.d@email.com', 'plan' => 'Pro', 'billing' => 'Monthly $25 / mo.', 'status' => 'Active', 'next_payment' => 'Jun 10, 2026'],
            ['name' => 'James Miller', 'email' => 'james.m@email.com', 'plan' => 'Enterprise', 'billing' => 'Custom', 'status' => 'Pending', 'next_payment' => 'Feb 28, 2026'],
            ['name' => 'Anna Taylor', 'email' => 'anna.t@email.com', 'plan' => 'Pro', 'billing' => 'Monthly $25 / mo.', 'status' => 'Active', 'next_payment' => 'May 5, 2026'],
            ['name' => 'Chris Anderson', 'email' => 'chris.a@email.com', 'plan' => 'basic', 'billing' => 'yearly $100 / yr.', 'status' => 'Cancelled', 'next_payment' => '—'],
        ];

        $total = 56;
        $from = 1;
        $to = 10;
        $statusClasses = [
            'Active' => 'bg-green-600 text-white',
            'Pending' => 'bg-orange-600 text-white',
            'Payment Failed' => 'bg-yellow-600 text-white',
            'Cancelled' => 'bg-red-600 text-white',
            'Paused' => 'bg-gray-600 text-white',
        ];

        return view('subscriptions', compact('subscriptions', 'total', 'from', 'to', 'statusClasses'));
    }
}
