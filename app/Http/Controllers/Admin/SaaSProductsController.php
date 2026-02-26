<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class SaaSProductsController extends Controller
{
    public function index()
    {
        $products = [
            ['name' => 'Sarah Collins', 'email1' => 'Sarach.collins@email.com', 'email2' => 'Sarach.collins@email.com', 'plan_rulers' => 'Sarach.collins@email.com', 'status' => 'Active'],
            ['name' => 'John Doe', 'email1' => 'john.doe@email.com', 'email2' => 'john.doe@email.com', 'plan_rulers' => 'john.doe@email.com', 'status' => 'Active'],
            ['name' => 'Jane Smith', 'email1' => 'jane.smith@email.com', 'email2' => 'jane.smith@email.com', 'plan_rulers' => 'jane.smith@email.com', 'status' => 'Active'],
            ['name' => 'Mike Johnson', 'email1' => 'mike.j@email.com', 'email2' => 'mike.j@email.com', 'plan_rulers' => 'mike.j@email.com', 'status' => 'Deactivate'],
            ['name' => 'Emily Brown', 'email1' => 'emily.b@email.com', 'email2' => 'emily.b@email.com', 'plan_rulers' => 'emily.b@email.com', 'status' => 'Active'],
            ['name' => 'David Wilson', 'email1' => 'david.w@email.com', 'email2' => 'david.w@email.com', 'plan_rulers' => 'david.w@email.com', 'status' => 'Active'],
            ['name' => 'Lisa Davis', 'email1' => 'lisa.d@email.com', 'email2' => 'lisa.d@email.com', 'plan_rulers' => 'lisa.d@email.com', 'status' => 'Active'],
        ];

        $total = 56;
        $from = 1;
        $to = 10;

        return view('saas-products', compact('products', 'total', 'from', 'to'));
    }
}
