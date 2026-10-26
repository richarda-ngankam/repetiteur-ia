<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardianships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->foreignUuid('guardian_id')->constrained('users')->cascadeOnDelete();   // parent ou tuteur
            $table->string('relation', 20)->default('parent');   // parent, tutor
            $table->string('role', 20)->default('full');         // full (paie et suit), payer, follower
            $table->string('status', 20)->default('pending');    // pending, confirmed, revoked
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['profile_id', 'guardian_id']);
        });

        DB::statement("ALTER TABLE guardianships ADD CONSTRAINT guardianships_relation_check CHECK (relation IN ('parent', 'tutor'))");
        DB::statement("ALTER TABLE guardianships ADD CONSTRAINT guardianships_role_check CHECK (role IN ('full', 'payer', 'follower'))");
        DB::statement("ALTER TABLE guardianships ADD CONSTRAINT guardianships_status_check CHECK (status IN ('pending', 'confirmed', 'revoked'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('guardianships');
    }
};