<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Allows dropping/truncating admin ops tables in phpMyAdmin without FK errors.
 * Columns remain; only DB-level foreign keys are removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->dropForeignKeyIfExists('admin_job_runs', 'admin_automation_job_id');
        $this->dropForeignKeyIfExists('support_ticket_messages', 'support_ticket_id');
        $this->dropForeignKeyIfExists('support_tickets', 'user_id');
        $this->dropForeignKeyIfExists('support_tickets', 'requester_id');
        $this->dropForeignKeyIfExists('support_tickets', 'assigned_to_id');
        $this->dropForeignKeyIfExists('admin_automation_jobs', 'user_id');
        $this->dropForeignKeyIfExists('admin_integration_settings', 'user_id');
        $this->dropForeignKeyIfExists('admin_webhooks', 'user_id');
    }

    public function down(): void
    {
        if (Schema::hasTable('admin_automation_jobs') && Schema::hasTable('users')) {
            Schema::table('admin_automation_jobs', function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (Schema::hasTable('admin_job_runs') && Schema::hasTable('admin_automation_jobs')) {
            Schema::table('admin_job_runs', function (Blueprint $table) {
                $table->foreign('admin_automation_job_id')
                    ->references('id')
                    ->on('admin_automation_jobs')
                    ->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('admin_integration_settings') && Schema::hasTable('users')) {
            Schema::table('admin_integration_settings', function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (Schema::hasTable('admin_webhooks') && Schema::hasTable('users')) {
            Schema::table('admin_webhooks', function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (Schema::hasTable('support_tickets') && Schema::hasTable('users')) {
            Schema::table('support_tickets', function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('requester_id')->references('id')->on('users')->nullOnDelete();
                $table->foreign('assigned_to_id')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (Schema::hasTable('support_ticket_messages') && Schema::hasTable('support_tickets')) {
            Schema::table('support_ticket_messages', function (Blueprint $table) {
                $table->foreign('support_ticket_id')
                    ->references('id')
                    ->on('support_tickets')
                    ->cascadeOnDelete();
            });
        }
    }

    private function dropForeignKeyIfExists(string $table, string $column): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        $hasForeignKey = collect(Schema::getForeignKeys($table))
            ->contains(fn (array $foreignKey) => in_array($column, $foreignKey['columns'] ?? [], true));

        if (! $hasForeignKey) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($column) {
            $table->dropForeign([$column]);
        });
    }
};
