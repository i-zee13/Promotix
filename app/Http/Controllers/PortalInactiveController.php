<?php

namespace App\Http\Controllers;

use App\Models\SaasProduct;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortalInactiveController extends Controller
{
    public function __invoke(Request $request): View
    {
        $product = SaasProduct::portalProduct();

        return view('portal.inactive', [
            'productName' => $product?->name ?: 'ClickRonix',
            'product' => $product,
        ]);
    }
}
