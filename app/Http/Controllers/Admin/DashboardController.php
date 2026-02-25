<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function index()
    {
        $failedPayments = [
            ['date' => 'Dec 12, 2025', 'email' => 'example@gmail.com', 'time' => '12:20 Pm'],
            ['date' => 'Dec 11, 2025', 'email' => 'user@example.com', 'time' => '09:15 Am'],
            ['date' => 'Dec 10, 2025', 'email' => 'contact@test.com', 'time' => '14:45 Pm'],
        ];

        $activeSubscriptions = [
            ['name' => 'Sarah Collins', 'email' => 'Example@gmail.com', 'date' => '8/1/2026', 'time' => '12:30 pm', 'price' => '$200', 'status' => 'Active'],
            ['name' => 'John Doe', 'email' => 'john@example.com', 'date' => '7/28/2026', 'time' => '09:15 am', 'price' => '$150', 'status' => 'Active'],
        ];

        return view('dashboard', compact('failedPayments', 'activeSubscriptions'));
    }
}
