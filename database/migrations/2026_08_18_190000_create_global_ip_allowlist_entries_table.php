<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('global_ip_allowlist_entries')) {
            Schema::create('global_ip_allowlist_entries', function (Blueprint $table): void {
                $table->id();
                $table->string('kind', 16); // provider | cidr
                $table->string('provider', 32)->nullable();
                $table->string('value', 128);
                $table->string('label')->nullable();
                $table->boolean('enabled')->default(true);
                $table->text('notes')->nullable();
                $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['kind', 'value']);
                $table->index(['enabled', 'kind']);
            });
        }

        $now = now();
        $providers = [
            [
                'kind' => 'provider',
                'provider' => 'google',
                'value' => 'google',
                'label' => 'Google (AdsBot / Googlebot)',
                'enabled' => true,
                'notes' => 'Official Google crawler and adsbot ranges (e.g. 66.249.0.0/16).',
            ],
            [
                'kind' => 'provider',
                'provider' => 'bing',
                'value' => 'bing',
                'label' => 'Microsoft Bing',
                'enabled' => false,
                'notes' => 'Bingbot crawler ranges.',
            ],
            [
                'kind' => 'provider',
                'provider' => 'meta',
                'value' => 'meta',
                'label' => 'Meta / Facebook',
                'enabled' => false,
                'notes' => 'Facebook / Meta crawler ranges.',
            ],
        ];

        foreach ($providers as $row) {
            $exists = DB::table('global_ip_allowlist_entries')
                ->where('kind', $row['kind'])
                ->where('value', $row['value'])
                ->exists();
            if ($exists) {
                continue;
            }

            DB::table('global_ip_allowlist_entries')->insert($row + [
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('global_ip_allowlist_entries');
    }
};
