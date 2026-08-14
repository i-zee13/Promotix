<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        if (! Schema::hasColumn('users', 'avatar_path')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('avatar_path', 255)->nullable()->after('email');
            });
        }

        if (! Schema::hasColumn('users', 'google_avatar_url')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('google_avatar_url', 500)->nullable()->after('avatar_path');
            });
        }
    }

    public function down(): void
    {
        // Repair migration intentionally keeps avatar data on rollback.
    }
};
