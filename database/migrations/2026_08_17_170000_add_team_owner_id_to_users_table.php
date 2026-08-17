<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || Schema::hasColumn('users', 'team_owner_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('team_owner_id')
                ->nullable()
                ->after('role_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->index('team_owner_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'team_owner_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['team_owner_id']);
            $table->dropIndex(['team_owner_id']);
            $table->dropColumn('team_owner_id');
        });
    }
};
