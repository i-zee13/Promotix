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
                if (! Schema::hasColumn('visits', 'threat_score')) {
                    $table->unsignedTinyInteger('threat_score')->default(0)->after('is_invalid_traffic');
                }
                if (! Schema::hasColumn('visits', 'threat_group')) {
                    $table->string('threat_group')->nullable()->after('threat_score');
                }
                if (! Schema::hasColumn('visits', 'action_taken')) {
                    $table->string('action_taken', 20)->default('allow')->after('threat_group');
                }
                if (! Schema::hasColumn('visits', 'detection_reasons')) {
                    $table->json('detection_reasons')->nullable()->after('action_taken');
                }
            });
        }

        if (! Schema::hasTable('detection_logs')) {
            Schema::create('detection_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('visit_id')->nullable()->index();
                $table->string('ip', 45)->index();
                $table->unsignedTinyInteger('threat_score')->default(0);
                $table->string('threat_group')->nullable()->index();
                $table->string('action_taken', 20)->default('allow')->index();
                $table->json('reasons')->nullable();
                $table->timestamp('detected_at')->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('detection_logs');

        if (Schema::hasTable('visits')) {
            Schema::table('visits', function (Blueprint $table): void {
                $columns = [];
                foreach (['threat_score', 'threat_group', 'action_taken', 'detection_reasons'] as $column) {
                    if (Schema::hasColumn('visits', $column)) {
                        $columns[] = $column;
                    }
                }
                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
