<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_ads_campaign_daily_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
            $table->foreignId('google_ads_account_id')->constrained()->cascadeOnDelete();
            $table->string('campaign_id', 32);
            $table->string('campaign_name');
            $table->string('status', 32)->nullable();
            $table->date('metric_date');
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->decimal('cost', 12, 2)->default(0);
            $table->decimal('cpc', 10, 2)->default(0);
            $table->decimal('conversions', 12, 2)->default(0);
            $table->unsignedInteger('phone_calls')->default(0);
            $table->decimal('ctr', 8, 2)->default(0);
            $table->timestamps();

            $table->unique(['domain_id', 'campaign_id', 'metric_date'], 'ga_daily_domain_camp_date_uniq');
            $table->index(['domain_id', 'metric_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_ads_campaign_daily_metrics');
    }
};
