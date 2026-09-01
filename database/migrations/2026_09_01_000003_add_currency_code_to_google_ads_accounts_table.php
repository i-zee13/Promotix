<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('google_ads_accounts')) {
            return;
        }

        Schema::table('google_ads_accounts', function (Blueprint $table): void {
            if (! Schema::hasColumn('google_ads_accounts', 'currency_code')) {
                $table->string('currency_code', 3)->nullable()->after('time_zone');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('google_ads_accounts')) {
            return;
        }

        Schema::table('google_ads_accounts', function (Blueprint $table): void {
            if (Schema::hasColumn('google_ads_accounts', 'currency_code')) {
                $table->dropColumn('currency_code');
            }
        });
    }
};
