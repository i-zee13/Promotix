<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaidMarketingVisit;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaidMarketingController extends Controller
{
    public function detailedView(Request $request): View
    {
        $query = PaidMarketingVisit::query()->with(['clicks' => function ($q) {
            $q->orderBy('clicked_at');
        }]);

        if ($ip = $request->string('ip')->toString()) {
            $query->where('ip', 'like', '%' . $ip . '%');
        }

        if ($path = $request->string('path')->toString()) {
            $query->where('last_path', 'like', '%' . $path . '%');
        }

        if ($platform = $request->string('platform')->toString()) {
            $query->where('platform', $platform);
        }

        if ($from = $request->date('from')) {
            $query->whereDate('last_click_at', '>=', $from);
        }
        if ($to = $request->date('to')) {
            $query->whereDate('last_click_at', '<=', $to);
        }

        $visits = $query->orderByDesc('last_click_at')->paginate(25)->withQueryString();

        $platforms = PaidMarketingVisit::query()
            ->select('platform')
            ->whereNotNull('platform')
            ->distinct()
            ->orderBy('platform')
            ->pluck('platform');

        return view('paid-marketing.detailed-view', [
            'visits' => $visits,
            'platforms' => $platforms,
        ]);
    }
}

