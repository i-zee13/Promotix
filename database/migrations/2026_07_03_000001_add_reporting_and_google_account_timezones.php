<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'reporting_timezone')) {
                $table->string('reporting_timezone', 32)->default('profile')->after('timezone_source');
            }
        });

        Schema::table('google_ads_accounts', function (Blueprint $table): void {
            if (! Schema::hasColumn('google_ads_accounts', 'time_zone')) {
                $table->string('time_zone', 64)->nullable()->after('account_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'reporting_timezone')) {
                $table->dropColumn('reporting_timezone');
            }
        });

        Schema::table('google_ads_accounts', function (Blueprint $table): void {
            if (Schema::hasColumn('google_ads_accounts', 'time_zone')) {
                $table->dropColumn('time_zone');
            }
        });
    }
};
