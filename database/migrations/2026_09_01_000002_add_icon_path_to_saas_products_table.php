<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('saas_products')) {
            return;
        }

        Schema::table('saas_products', function (Blueprint $table): void {
            if (! Schema::hasColumn('saas_products', 'icon_path')) {
                $table->string('icon_path')->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('saas_products')) {
            return;
        }

        Schema::table('saas_products', function (Blueprint $table): void {
            if (Schema::hasColumn('saas_products', 'icon_path')) {
                $table->dropColumn('icon_path');
            }
        });
    }
};
