<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_ads_advertised_hosts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('google_ads_account_id')->constrained('google_ads_accounts')->cascadeOnDelete();
            $table->string('hostname')->index();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['google_ads_account_id', 'hostname'], 'gaa_hosts_acct_host_uniq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_ads_advertised_hosts');
    }
};
