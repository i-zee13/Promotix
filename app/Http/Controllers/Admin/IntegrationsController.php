<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class IntegrationsController extends Controller
{
    public function index()
    {
        $total = 56;
        $from = 1;
        $to = 10;

        return view('integrations', compact('total', 'from', 'to'));
    }
}
