<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentsController extends Controller
{
    public function index(Request $request): View
    {
        $payments = Payment::with(['user', 'subscription.plan'])
            ->when($request->string('status')->toString(), fn ($q, string $status) => $q->where('status', $status))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('super-admin.payments.index', [
            'payments' => $payments,
            'statuses' => ['paid', 'pending', 'failed', 'refunded'],
        ]);
    }
}
