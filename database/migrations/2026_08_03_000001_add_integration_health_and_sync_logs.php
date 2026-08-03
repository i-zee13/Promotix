<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('google_connections')) {
            Schema::table('google_connections', function (Blueprint $table): void {
                if (! Schema::hasColumn('google_connections', 'last_sync_at')) {
                    $table->timestamp('last_sync_at')->nullable()->after('connected_at');
                }
                if (! Schema::hasColumn('google_connections', 'last_sync_status')) {
                    $table->string('last_sync_status', 32)->nullable()->after('last_sync_at');
                }
                if (! Schema::hasColumn('google_connections', 'last_sync_message')) {
                    $table->string('last_sync_message', 500)->nullable()->after('last_sync_status');
                }
                if (! Schema::hasColumn('google_connections', 'last_health_check_at')) {
                    $table->timestamp('last_health_check_at')->nullable()->after('last_sync_message');
                }
                if (! Schema::hasColumn('google_connections', 'health_status')) {
                    $table->string('health_status', 32)->nullable()->after('last_health_check_at');
                }
            });
        }

        if (! Schema::hasTable('integration_sync_logs')) {
            Schema::create('integration_sync_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('google_connection_id')->nullable()->constrained('google_connections')->nullOnDelete();
                $table->foreignId('domain_id')->nullable()->constrained('domains')->nullOnDelete();
                $table->string('action', 64);
                $table->string('status', 32)->default('ok');
                $table->string('message', 500)->nullable();
                $table->json('meta')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['user_id', 'created_at']);
                $table->index(['action', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_sync_logs');

        if (Schema::hasTable('google_connections')) {
            Schema::table('google_connections', function (Blueprint $table): void {
                foreach (['health_status', 'last_health_check_at', 'last_sync_message', 'last_sync_status', 'last_sync_at'] as $column) {
                    if (Schema::hasColumn('google_connections', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
