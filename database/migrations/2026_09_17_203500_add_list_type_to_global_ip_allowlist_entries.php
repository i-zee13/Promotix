<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('global_ip_allowlist_entries')) {
            return;
        }

        Schema::table('global_ip_allowlist_entries', function (Blueprint $table): void {
            if (! Schema::hasColumn('global_ip_allowlist_entries', 'list_type')) {
                $table->string('list_type', 16)->default('allow')->after('kind');
                $table->index(['list_type', 'enabled', 'kind']);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('global_ip_allowlist_entries')) {
            return;
        }

        Schema::table('global_ip_allowlist_entries', function (Blueprint $table): void {
            if (Schema::hasColumn('global_ip_allowlist_entries', 'list_type')) {
                $table->dropIndex(['list_type', 'enabled', 'kind']);
                $table->dropColumn('list_type');
            }
        });
    }
};
