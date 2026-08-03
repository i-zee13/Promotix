<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportsController extends Controller
{
    public function index(Request $request): View
    {
        return view('reports.index', [
            'exports' => [
                [
                    'title' => 'Advanced View export (CSV)',
                    'description' => 'Invalid traffic rows with GCLID, risk, evidence, and session metadata.',
                    'href' => route('paid-marketing.detailed-export'),
                    'label' => 'Download CSV',
                ],
                [
                    'title' => 'Advanced View export (XLSX)',
                    'description' => 'Same Advanced View dataset in Excel format.',
                    'href' => route('paid-marketing.detailed-export-xlsx'),
                    'label' => 'Download XLSX',
                ],
                [
                    'title' => 'Paid IP export',
                    'description' => 'IP-level invalid traffic with device, browser, risk, and action.',
                    'href' => route('paid-marketing.ips.export'),
                    'label' => 'Download CSV',
                ],
                [
                    'title' => 'Bot protection export',
                    'description' => 'Bot dashboard visit export for the current filters.',
                    'href' => route('bot-protection.export'),
                    'label' => 'Download CSV',
                ],
            ],
        ]);
    }
}
