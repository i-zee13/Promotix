<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IpLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IpLogsController extends Controller
{
    /**
     * Display a listing of IP logs.
     */
    public function index(Request $request): View
    {
        $query = IpLog::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('ip', 'like', "%{$search}%")
                    ->orWhere('user_agent', 'like', "%{$search}%")
                    ->orWhere('last_path', 'like', "%{$search}%")
                    ->orWhere('last_referrer', 'like', "%{$search}%");
            });
        }

        if (! is_null($blocked = $request->input('blocked'))) {
            if ($blocked === '1') {
                $query->where('is_blocked', true);
            } elseif ($blocked === '0') {
                $query->where('is_blocked', false);
            }
        }

        $logs = $query->orderByDesc('last_seen_at')->orderByDesc('id')->paginate(20)->withQueryString();

        return view('ip-logs', [
            'logs' => $logs,
        ]);
    }

    /**
     * Toggle block status for a single IP.
     */
    public function toggleBlock(IpLog $ipLog): RedirectResponse
    {
        $ipLog->is_blocked = ! $ipLog->is_blocked;
        $ipLog->save();

        return back()->with('status', 'IP ' . $ipLog->ip . ' has been ' . ($ipLog->is_blocked ? 'blocked' : 'unblocked') . '.');
    }

    /**
     * Remove a single IP log.
     */
    public function destroy(IpLog $ipLog): RedirectResponse
    {
        $ipLog->delete();

        return back()->with('status', 'IP ' . $ipLog->ip . ' has been deleted.');
    }
}

