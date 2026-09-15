<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            if (! Schema::hasColumn('roles', 'portal')) {
                $table->string('portal', 16)->default('user')->after('description');
            }
            if (! Schema::hasColumn('roles', 'color')) {
                $table->string('color', 32)->nullable()->after('portal');
            }
            if (! Schema::hasColumn('roles', 'is_temporary')) {
                $table->boolean('is_temporary')->default(false)->after('color');
            }
            if (! Schema::hasColumn('roles', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('is_temporary');
            }
            if (! Schema::hasColumn('roles', 'base_role_id')) {
                $table->foreignId('base_role_id')->nullable()->after('expires_at')->constrained('roles')->nullOnDelete();
            }
            if (! Schema::hasColumn('roles', 'page_access')) {
                $table->json('page_access')->nullable()->after('base_role_id');
            }
            if (! Schema::hasColumn('roles', 'abilities')) {
                $table->json('abilities')->nullable()->after('page_access');
            }
            if (! Schema::hasColumn('roles', 'scope')) {
                $table->json('scope')->nullable()->after('abilities');
            }
        });

        if (! Schema::hasTable('role_permission_audits')) {
            Schema::create('role_permission_audits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
                $table->string('action', 32); // created|updated|deleted
                $table->string('portal', 16)->nullable();
                $table->json('scope')->nullable();
                $table->json('before')->nullable();
                $table->json('after')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permission_audits');

        Schema::table('roles', function (Blueprint $table) {
            foreach (['scope', 'abilities', 'page_access', 'base_role_id', 'expires_at', 'is_temporary', 'color', 'portal'] as $col) {
                if (Schema::hasColumn('roles', $col)) {
                    if ($col === 'base_role_id') {
                        $table->dropConstrainedForeignId('base_role_id');
                    } else {
                        $table->dropColumn($col);
                    }
                }
            }
        });
    }
};
