<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();          // qui donne le consentement
            $table->foreignUuid('profile_id')->nullable()->constrained('profiles')->cascadeOnDelete();  // enfant concerné
            $table->string('type', 30);               // terms, privacy, parental, ai_processing, marketing
            $table->string('document_version', 20);   // version du texte accepté
            $table->char('country_code', 2);
            $table->timestamp('granted_at');
            $table->timestamp('withdrawn_at')->nullable();
            $table->char('ip_hash', 64)->nullable();  // empreinte de l'adresse IP, pas l'adresse
            $table->timestamps();

            $table->foreign('country_code')->references('code')->on('countries');
            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consents');
    }
};