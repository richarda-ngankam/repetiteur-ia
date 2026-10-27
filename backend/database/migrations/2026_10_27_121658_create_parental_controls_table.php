<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parental_controls', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('profile_id')->unique()->constrained('profiles')->cascadeOnDelete();
            $table->unsignedSmallInteger('daily_limit_minutes')->default(60);   // 30, 60, 90, 120 ou 180
            $table->time('quiet_start')->nullable()->default('21:00');          // horaires interdits : début
            $table->time('quiet_end')->nullable()->default('07:00');            // horaires interdits : fin
            $table->boolean('purchases_allowed')->default(false);
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Les horaires interdits sont soit complets (début et fin), soit absents (« Aucun »).
        DB::statement('ALTER TABLE parental_controls ADD CONSTRAINT parental_controls_quiet_check CHECK ((quiet_start IS NULL) = (quiet_end IS NULL))');
    }

    public function down(): void
    {
        Schema::dropIfExists('parental_controls');
    }
};