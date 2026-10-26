<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->char('code', 2)->primary();           // code ISO 3166-1 : CM, FR...
            $table->string('name');                        // nom affiché
            $table->string('phone_prefix', 8);             // indicatif : +237
            $table->char('currency', 3);                   // code ISO 4217 : XAF, EUR...
            $table->string('default_locale', 10)->default('fr');
            $table->string('timezone', 64);                // ex. Africa/Douala
            $table->jsonb('config')->default('{}');        // fiche pays : seuils d'âge, numéros d'aide, moyens de paiement...
            $table->boolean('is_open')->default(false);    // pays ouvert aux inscriptions
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};