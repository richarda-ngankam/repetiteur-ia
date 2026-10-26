<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Compte de connexion de l'élève (adolescent ou adulte). Vide pour un enfant de 9 à 12 ans.
            $table->foreignUuid('user_id')->nullable()->unique()->constrained('users')->cascadeOnDelete();
            // Compte qui a créé le profil : le parent, ou l'élève lui-même.
            $table->foreignUuid('created_by')->constrained('users')->cascadeOnDelete();

            $table->string('first_name', 60);
            $table->date('birth_date');                     // le palier d'âge se calcule, il n'est jamais stocké
            $table->char('country_code', 2);

            // Parcours scolaire : codes liés à la fiche pays (tables dédiées plus tard).
            $table->string('system_code', 30)->nullable();  // système éducatif
            $table->string('level_code', 30)->nullable();   // classe
            $table->string('track_code', 30)->nullable();   // série ou filière
            $table->timestamp('level_changed_at')->nullable();

            // Code PIN pour un enfant de 9 à 12 ans (stocké haché).
            $table->string('pin_hash')->nullable();
            $table->unsignedSmallInteger('pin_failed_attempts')->default(0);
            $table->timestamp('pin_locked_until')->nullable();

            $table->timestamps();

            $table->foreign('country_code')->references('code')->on('countries');
            $table->index(['country_code', 'level_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};