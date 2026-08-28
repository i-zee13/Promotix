<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_invites')) {
            Schema::table('user_invites', function (Blueprint $table): void {
                if (! Schema::hasColumn('user_invites', 'page_slugs')) {
                    $table->json('page_slugs')->nullable()->after('plan_id');
                }
                if (! Schema::hasColumn('user_invites', 'domain_ids')) {
                    $table->json('domain_ids')->nullable()->after('page_slugs');
                }
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table): void {
                if (! Schema::hasColumn('users', 'allowed_page_slugs')) {
                    $table->json('allowed_page_slugs')->nullable()->after('team_owner_id');
                }
                if (! Schema::hasColumn('users', 'allowed_domain_ids')) {
                    $table->json('allowed_domain_ids')->nullable()->after('allowed_page_slugs');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('user_invites')) {
            Schema::table('user_invites', function (Blueprint $table): void {
                foreach (['page_slugs', 'domain_ids'] as $col) {
                    if (Schema::hasColumn('user_invites', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table): void {
                foreach (['allowed_page_slugs', 'allowed_domain_ids'] as $col) {
                    if (Schema::hasColumn('users', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
