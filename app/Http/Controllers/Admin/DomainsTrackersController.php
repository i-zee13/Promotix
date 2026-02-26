<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class DomainsTrackersController extends Controller
{
    public function index()
    {
        $rows = [
            ['name' => 'Sarah Collins', 'email' => 'Sarach.collins@email.com', 'plan' => 'Pro', 'amount' => 'Monthly $25 / mo.', 'time' => 'April 23, 2024', 'status' => 'Paid', 'payment_type' => 'card', 'method' => 'Visa', 'masked' => '•••• 5714', 'invoice' => null],
            ['name' => 'John Doe', 'email' => 'john.doe@email.com', 'plan' => 'basic', 'amount' => 'yearly $100 / yr.', 'time' => 'March 15, 2024', 'status' => 'Failed', 'payment_type' => 'invoice', 'method' => null, 'masked' => null, 'invoice' => 'INV-4531'],
            ['name' => 'Jane Smith', 'email' => 'jane.smith@email.com', 'plan' => 'Enterprise', 'amount' => 'Custom', 'time' => 'May 2, 2024', 'status' => 'Refunded', 'payment_type' => 'card', 'method' => 'Mastercard', 'masked' => '•••• 2345', 'invoice' => null],
            ['name' => 'Mike Johnson', 'email' => 'mike.j@email.com', 'plan' => 'Pro', 'amount' => 'Monthly $25 / mo.', 'time' => 'April 10, 2024', 'status' => 'Tracking Disabled', 'payment_type' => 'invoice', 'method' => null, 'masked' => null, 'invoice' => 'INV-4528'],
            ['name' => 'Emily Brown', 'email' => 'emily.b@email.com', 'plan' => 'Premium', 'amount' => 'Monthly $50 / mo.', 'time' => 'May 24, 2024', 'status' => 'Paid', 'payment_type' => 'card', 'method' => 'Visa', 'masked' => '•••• 9900', 'invoice' => null],
            ['name' => 'David Wilson', 'email' => 'david.w@email.com', 'plan' => 'basic', 'amount' => 'yearly $100 / yr.', 'time' => 'March 28, 2024', 'status' => 'Failed', 'payment_type' => 'invoice', 'method' => null, 'masked' => null, 'invoice' => 'INV-4520'],
            ['name' => 'Lisa Davis', 'email' => 'lisa.d@email.com', 'plan' => 'Pro', 'amount' => 'Monthly $25 / mo.', 'time' => 'April 5, 2024', 'status' => 'Paid', 'payment_type' => 'card', 'method' => 'Mastercard', 'masked' => '•••• 3344', 'invoice' => null],
            ['name' => 'James Miller', 'email' => 'james.m@email.com', 'plan' => 'Enterprise', 'amount' => 'Custom', 'time' => 'May 12, 2024', 'status' => 'Tracking Disabled', 'payment_type' => 'invoice', 'method' => null, 'masked' => null, 'invoice' => 'INV-4515'],
            ['name' => 'Anna Taylor', 'email' => 'anna.t@email.com', 'plan' => 'Pro', 'amount' => 'Monthly $25 / mo.', 'time' => 'April 18, 2024', 'status' => 'Paid', 'payment_type' => 'card', 'method' => 'Visa', 'masked' => '•••• 5566', 'invoice' => null],
            ['name' => 'Chris Anderson', 'email' => 'chris.a@email.com', 'plan' => 'basic', 'amount' => 'yearly $100 / yr.', 'time' => 'March 3, 2024', 'status' => 'Refunded', 'payment_type' => 'invoice', 'method' => null, 'masked' => null, 'invoice' => 'INV-4508'],
        ];

        $total = 56;
        $from = 1;
        $to = 10;
        $statusClasses = [
            'Paid' => 'bg-green-600 text-white',
            'Failed' => 'bg-red-600 text-white',
            'Refunded' => 'bg-yellow-600 text-white',
            'Tracking Disabled' => 'bg-gray-600 text-white',
        ];

        return view('domains-trackers', compact('rows', 'total', 'from', 'to', 'statusClasses'));
    }
}
