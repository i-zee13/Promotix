<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('guidance_articles')) {
            return;
        }

        Schema::table('guidance_articles', function (Blueprint $table): void {
            if (! Schema::hasColumn('guidance_articles', 'image_path')) {
                $table->string('image_path')->nullable()->after('steps');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('guidance_articles')) {
            return;
        }

        Schema::table('guidance_articles', function (Blueprint $table): void {
            if (Schema::hasColumn('guidance_articles', 'image_path')) {
                $table->dropColumn('image_path');
            }
        });
    }
};
