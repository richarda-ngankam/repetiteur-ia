<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->char('install_hash', 64);                    // empreinte de l'identifiant d'installation (jamais l'identifiant lui-même)
            $table->string('label', 80)->nullable();             // ex. « Tecno Spark 10 »
            $table->string('platform', 20)->default('android');  // android, ios
            $table->string('app_version', 20)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();         // appareil retiré par le parent
            $table->timestamps();

            $table->unique(['user_id', 'install_hash']);
        });

        DB::statement("ALTER TABLE devices ADD CONSTRAINT devices_platform_check CHECK (platform IN ('android', 'ios'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};