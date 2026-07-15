<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('detection_settings_audits')) {
            return;
        }

        Schema::create('detection_settings_audits', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('domain_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('scope')->default('domain')->index();
            $table->string('action')->index(); // created|updated|enabled|disabled
            $table->string('field')->index();
            $table->json('previous_value')->nullable();
            $table->json('new_value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detection_settings_audits');
    }
};
