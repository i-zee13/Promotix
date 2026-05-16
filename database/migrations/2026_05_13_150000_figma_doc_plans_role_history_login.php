<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('plans')) {
            Schema::table('plans', function (Blueprint $table): void {
                if (! Schema::hasColumn('plans', 'price_yearly_cents')) {
                    $table->unsignedInteger('price_yearly_cents')->nullable()->after('price_cents');
                }
                if (! Schema::hasColumn('plans', 'short_description')) {
                    $table->text('short_description')->nullable()->after('slug');
                }
                if (! Schema::hasColumn('plans', 'sort_order')) {
                    $table->unsignedInteger('sort_order')->default(0)->after('is_active')->index();
                }
                if (! Schema::hasColumn('plans', 'is_highlighted')) {
                    $table->boolean('is_highlighted')->default(false)->after('sort_order')->index();
                }
                if (! Schema::hasColumn('plans', 'cta_label')) {
                    $table->string('cta_label', 80)->nullable()->after('is_highlighted');
                }
            });

            $ids = DB::table('plans')->pluck('id');
            foreach ($ids as $id) {
                DB::table('plans')->where('id', $id)->update(['sort_order' => (int) $id]);
            }
        }

        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'last_login_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->timestamp('last_login_at')->nullable()->after('remember_token')->index();
            });
        }

        if (! Schema::hasTable('role_changes')) {
            Schema::create('role_changes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('old_role_id')->nullable()->constrained('roles')->nullOnDelete();
                $table->foreignId('new_role_id')->nullable()->constrained('roles')->nullOnDelete();
                $table->foreignId('changed_by_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('role_changes');

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'last_login_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('last_login_at');
            });
        }

        if (Schema::hasTable('plans')) {
            Schema::table('plans', function (Blueprint $table): void {
                foreach (['price_yearly_cents', 'short_description', 'sort_order', 'is_highlighted', 'cta_label'] as $col) {
                    if (Schema::hasColumn('plans', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
