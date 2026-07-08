<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('visits')) {
            Schema::table('visits', function (Blueprint $table): void {
                if (! Schema::hasColumn('visits', 'click_source')) {
                    $table->string('click_source', 16)->nullable()->after('is_paid_traffic')->index();
                }
                if (! Schema::hasColumn('visits', 'ad_click_meta')) {
                    $table->json('ad_click_meta')->nullable()->after('click_source');
                }
            });
        }

        if (Schema::hasTable('paid_marketing_clicks') && ! Schema::hasColumn('paid_marketing_clicks', 'click_source')) {
            Schema::table('paid_marketing_clicks', function (Blueprint $table): void {
                $table->string('click_source', 16)->nullable()->after('paid_id')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('visits')) {
            Schema::table('visits', function (Blueprint $table): void {
                foreach (['click_source', 'ad_click_meta'] as $column) {
                    if (Schema::hasColumn('visits', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('paid_marketing_clicks') && Schema::hasColumn('paid_marketing_clicks', 'click_source')) {
            Schema::table('paid_marketing_clicks', function (Blueprint $table): void {
                $table->dropColumn('click_source');
            });
        }
    }
};
