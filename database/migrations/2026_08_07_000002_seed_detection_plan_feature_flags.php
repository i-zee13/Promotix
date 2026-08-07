<?php

use App\Support\DetectionPlanFeatures;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        if (Schema::hasTable('feature_flags')) {
            foreach (DetectionPlanFeatures::catalog() as $row) {
                DB::table('feature_flags')->updateOrInsert(
                    ['key' => $row['key']],
                    [
                        'name' => $row['label'],
                        'description' => $row['description'],
                        'enabled' => true,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );
            }
        }

        if (! Schema::hasTable('plans')) {
            return;
        }

        $plans = DB::table('plans')->select(['id', 'feature_flags'])->get();
        foreach ($plans as $plan) {
            $existing = json_decode((string) ($plan->feature_flags ?? '{}'), true);
            if (! is_array($existing)) {
                $existing = [];
            }
            $merged = DetectionPlanFeatures::mergeIntoFlags($existing);
            DB::table('plans')->where('id', $plan->id)->update([
                'feature_flags' => json_encode($merged),
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('feature_flags')) {
            DB::table('feature_flags')->whereIn('key', DetectionPlanFeatures::keys())->delete();
        }
    }
};
