<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class PlansController extends Controller
{
    public function index()
    {
        return view('plans');
    }
}
