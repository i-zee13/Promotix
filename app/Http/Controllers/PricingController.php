<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\View\View;

class PricingController extends Controller
{
    public function index(): View
    {
        $plans = Plan::query()
            ->where('is_active', true)
            ->where('is_custom', false)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('pricing', [
            'plans' => $plans,
        ]);
    }
}
