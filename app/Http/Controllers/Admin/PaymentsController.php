<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class PaymentsController extends Controller
{
    public function index()
    {
        $payments = [
            ['name' => 'Sarah Collins', 'email' => 'Sarach.collins@email.com', 'plan' => 'Enterprise', 'amount' => 'Custom', 'date' => 'May 24, 2026', 'status' => 'Active', 'method' => 'Visa', 'masked' => '•••• 5714', 'invoice' => 'INV-4531'],
            ['name' => 'John Doe', 'email' => 'john.doe@email.com', 'plan' => 'Pro', 'amount' => 'Monthly $25 / mo.', 'date' => 'Feb 23, 2026', 'status' => 'Cancelled', 'method' => 'Mastercard', 'masked' => '•••• 2345', 'invoice' => 'INV-4528'],
            ['name' => 'Jane Smith', 'email' => 'jane.smith@email.com', 'plan' => 'Enterprise', 'amount' => 'Custom', 'date' => 'Feb 19, 2026', 'status' => 'Active', 'method' => 'PayPal', 'masked' => '•••• 9012', 'invoice' => 'INV-4525'],
            ['name' => 'Mike Johnson', 'email' => 'mike.j@email.com', 'plan' => 'Pro', 'amount' => 'Monthly $25 / mo.', 'date' => 'Mar 1, 2026', 'status' => 'Paused', 'method' => 'Visa', 'masked' => '•••• 3344', 'invoice' => 'INV-4520'],
            ['name' => 'Emily Brown', 'email' => 'emily.b@email.com', 'plan' => 'Premium', 'amount' => 'Monthly $50 / mo.', 'date' => 'May 15, 2026', 'status' => 'Active', 'method' => 'Mastercard', 'masked' => '•••• 5566', 'invoice' => 'INV-4518'],
            ['name' => 'David Wilson', 'email' => 'david.w@email.com', 'plan' => 'basic', 'amount' => 'yearly $100 / yr.', 'date' => 'Apr 10, 2026', 'status' => 'Cancelled', 'method' => 'Visa', 'masked' => '•••• 7788', 'invoice' => 'INV-4512'],
            ['name' => 'Lisa Davis', 'email' => 'lisa.d@email.com', 'plan' => 'Pro', 'amount' => 'Monthly $25 / mo.', 'date' => 'Jun 10, 2026', 'status' => 'Active', 'method' => 'Visa', 'masked' => '•••• 9900', 'invoice' => 'INV-4509'],
            ['name' => 'James Miller', 'email' => 'james.m@email.com', 'plan' => 'Enterprise', 'amount' => 'Custom', 'date' => 'Feb 28, 2026', 'status' => 'Paused', 'method' => 'Mastercard', 'masked' => '•••• 1122', 'invoice' => 'INV-4505'],
            ['name' => 'Anna Taylor', 'email' => 'anna.t@email.com', 'plan' => 'Pro', 'amount' => 'Monthly $25 / mo.', 'date' => 'May 5, 2026', 'status' => 'Active', 'method' => 'PayPal', 'masked' => '•••• 3344', 'invoice' => 'INV-4501'],
            ['name' => 'Chris Anderson', 'email' => 'chris.a@email.com', 'plan' => 'basic', 'amount' => 'yearly $100 / yr.', 'date' => 'Jan 15, 2026', 'status' => 'Cancelled', 'method' => 'Visa', 'masked' => '•••• 5566', 'invoice' => 'INV-4498'],
        ];

        $total = 56;
        $from = 1;
        $to = 10;
        $statusClasses = [
            'Active' => 'bg-green-600 text-white',
            'Cancelled' => 'bg-red-600 text-white',
            'Paused' => 'bg-yellow-600 text-white',
        ];

        return view('payments', compact('payments', 'total', 'from', 'to', 'statusClasses'));
    }
}
