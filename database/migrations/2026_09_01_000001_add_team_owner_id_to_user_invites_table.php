<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_invites') || Schema::hasColumn('user_invites', 'team_owner_id')) {
            return;
        }

        Schema::table('user_invites', function (Blueprint $table): void {
            $table->foreignId('team_owner_id')
                ->nullable()
                ->after('invited_by_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('user_invites') || ! Schema::hasColumn('user_invites', 'team_owner_id')) {
            return;
        }

        Schema::table('user_invites', function (Blueprint $table): void {
            $table->dropForeign(['team_owner_id']);
            $table->dropColumn('team_owner_id');
        });
    }
};
