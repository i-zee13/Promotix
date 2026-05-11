<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TrafficBotLogsController extends Controller
{
    public function index(Request $request)
    {
        $domainIds = Domain::query()->where('user_id', $request->user()->id)->pluck('id');

        $logs = Schema::hasTable('visits')
            ? DB::table('visits')
                ->whereIn('domain_id', $domainIds)
                ->orderByDesc('visited_at')
                ->paginate(10)
            : collect();

        $rows = $logs instanceof \Illuminate\Contracts\Pagination\Paginator
            ? $logs->getCollection()->map(fn ($row) => [
                'name' => $row->ip,
                'email' => $row->url ?? '—',
                'bot_score' => (string) ($row->threat_score ?? 0),
                'bot_detail' => $row->action_taken ?? 'allow',
                'status' => ($row->action_taken ?? 'allow') === 'block' ? 'Blocked' : (($row->is_invalid_traffic ?? false) ? 'Flagged' : 'Allowed'),
                'country' => $row->country ?? '—',
                'threat_group' => $row->threat_group ?? '—',
            ])->all()
            : [];

        $total = $logs instanceof \Illuminate\Contracts\Pagination\Paginator ? $logs->total() : 0;
        $from = $logs instanceof \Illuminate\Contracts\Pagination\Paginator ? $logs->firstItem() ?? 0 : 0;
        $to = $logs instanceof \Illuminate\Contracts\Pagination\Paginator ? $logs->lastItem() ?? 0 : 0;
        $base = Schema::hasTable('visits') ? DB::table('visits')->whereIn('domain_id', $domainIds) : null;
        $stats = [
            'total_requests' => $base ? (clone $base)->count() : 0,
            'threat_groups' => $base ? (clone $base)->whereNotNull('threat_group')->distinct('threat_group')->count('threat_group') : 0,
            'blocked_traffic' => $base ? (clone $base)->where('action_taken', 'block')->count() : 0,
            'allow_lists' => \App\Models\IpLog::query()->where('is_blocked', false)->count(),
        ];
        $statusClasses = [
            'Allowed' => 'bg-green-600 text-white',
            'Flagged' => 'bg-yellow-600 text-white',
            'Blocked' => 'bg-red-600 text-white',
        ];

        return view('traffic-bot-logs', compact('rows', 'total', 'from', 'to', 'stats', 'statusClasses'));
    }
}
