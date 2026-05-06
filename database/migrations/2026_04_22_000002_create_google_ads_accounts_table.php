<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_ads_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('google_connection_id')->constrained()->cascadeOnDelete();
            $table->string('customer_id')->index(); // e.g. 1234567890
            $table->string('display_customer_id')->nullable(); // e.g. AW-1234567890
            $table->string('account_name')->nullable();
            $table->string('manager_customer_id')->nullable();
            $table->boolean('is_manager')->default(false);
            $table->string('google_tag_id')->nullable(); // e.g. AW-123...
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['google_connection_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_ads_accounts');
    }
};

