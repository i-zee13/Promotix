<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('visits')) {
            return;
        }

        Schema::table('visits', function (Blueprint $table): void {
            if (! Schema::hasColumn('visits', 'user_agent')) {
                $table->text('user_agent')->nullable()->after('os');
            }
            if (! Schema::hasColumn('visits', 'is_crawler')) {
                $table->boolean('is_crawler')->default(false)->index()->after('is_invalid_traffic');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('visits')) {
            return;
        }

        Schema::table('visits', function (Blueprint $table): void {
            $cols = [];
            foreach (['user_agent', 'is_crawler'] as $c) {
                if (Schema::hasColumn('visits', $c)) {
                    $cols[] = $c;
                }
            }
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
