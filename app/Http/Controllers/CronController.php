<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class CronController extends Controller
{
    public function run(Request $request, string $token): JsonResponse
    {
        $expected = (string) env('CRON_TOKEN', '');

        if ($expected === '' || ! hash_equals($expected, $token)) {
            abort(404);
        }

        $exitCode = Artisan::call('schedule:run');
        $output = trim(Artisan::output());

        return response()->json([
            'ok' => $exitCode === 0,
            'exit_code' => $exitCode,
            'ran_at' => now()->toIso8601String(),
            'output' => $output,
        ]);
    }

    public function aggregate(Request $request, string $token): JsonResponse
    {
        $expected = (string) env('CRON_TOKEN', '');

        if ($expected === '' || ! hash_equals($expected, $token)) {
            abort(404);
        }

        $hours = max(1, (int) $request->query('hours', 2));
        $exitCode = Artisan::call('analytics:aggregate-hourly', ['--hours' => $hours]);
        $output = trim(Artisan::output());

        return response()->json([
            'ok' => $exitCode === 0,
            'exit_code' => $exitCode,
            'hours' => $hours,
            'ran_at' => now()->toIso8601String(),
            'output' => $output,
        ]);
    }
}
