<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\FeatureFlag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class SupportPagesController extends Controller
{
    public function domains(): View
    {
        return view('super-admin.simple.domains', [
            'domains' => Domain::with('user')->latest('id')->paginate(25),
        ]);
    }

    public function analytics(): View
    {
        return view('super-admin.simple.analytics', [
            'visits' => Schema::hasTable('visits') ? DB::table('visits')->count() : 0,
            'detections' => Schema::hasTable('detection_logs') ? DB::table('detection_logs')->count() : 0,
            'hourlyRows' => Schema::hasTable('analytics_hourly') ? DB::table('analytics_hourly')->count() : 0,
        ]);
    }

    public function security(): View
    {
        return view('super-admin.simple.security', [
            'blockedIps' => Schema::hasTable('ip_logs') ? DB::table('ip_logs')->where('is_blocked', true)->count() : 0,
            'recentDetections' => Schema::hasTable('detection_logs')
                ? DB::table('detection_logs')->latest('detected_at')->limit(20)->get()
                : collect(),
        ]);
    }

    public function settings(): View
    {
        return view('super-admin.simple.settings', [
            'featureFlags' => FeatureFlag::orderBy('name')->get(),
        ]);
    }

    public function storeFeatureFlag(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:100', 'unique:feature_flags,key'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'enabled' => ['nullable', 'boolean'],
        ]);

        FeatureFlag::create([
            'key' => $data['key'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'enabled' => (bool) ($data['enabled'] ?? true),
        ]);

        return back()->with('status', 'Feature flag created.');
    }

    public function toggleFeatureFlag(FeatureFlag $featureFlag): RedirectResponse
    {
        $featureFlag->update(['enabled' => ! $featureFlag->enabled]);

        return back()->with('status', 'Feature flag updated.');
    }
}
