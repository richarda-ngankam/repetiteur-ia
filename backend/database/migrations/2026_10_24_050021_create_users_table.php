<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 20);                          // parent, student, staff
            $table->string('email')->nullable()->unique();
            $table->string('phone', 20)->nullable()->unique();   // format international : +237...
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('password')->nullable();              // seulement pour le personnel
            $table->char('country_code', 2);
            $table->string('locale', 10)->default('fr');
            $table->string('status', 20)->default('active');     // active, limited, suspended
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();

            $table->foreign('country_code')->references('code')->on('countries');
        });

        // Un compte doit avoir au moins un moyen de contact, un type et un statut valides.
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_contact_check CHECK (email IS NOT NULL OR phone IS NOT NULL)');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_type_check CHECK (type IN ('parent', 'student', 'staff'))");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ('active', 'limited', 'suspended'))");

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
    }
};