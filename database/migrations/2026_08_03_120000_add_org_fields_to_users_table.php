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

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'company_address')) {
                $table->string('company_address', 500)->nullable()->after('website_url');
            }
            if (! Schema::hasColumn('users', 'support_email')) {
                $table->string('support_email', 255)->nullable()->after('company_address');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $drop = [];
            if (Schema::hasColumn('users', 'company_address')) {
                $drop[] = 'company_address';
            }
            if (Schema::hasColumn('users', 'support_email')) {
                $drop[] = 'support_email';
            }
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};
