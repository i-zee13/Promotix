<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->string('source', 32)->default('manual')->after('hostname');
            $table->foreignId('google_ads_account_id')
                ->nullable()
                ->after('source')
                ->constrained('google_ads_accounts')
                ->nullOnDelete();
            $table->timestamp('ads_synced_at')->nullable()->after('google_ads_account_id');
        });
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropConstrainedForeignId('google_ads_account_id');
            $table->dropColumn(['source', 'ads_synced_at']);
        });
    }
};
