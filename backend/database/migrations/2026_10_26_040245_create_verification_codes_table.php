<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('purpose', 30);        // register, login, parent_confirmation, unlock...
            $table->string('channel', 10);        // sms, email
            $table->string('destination');        // numéro ou email visé
            $table->string('code_hash');          // le code est stocké haché, jamais en clair
            $table->foreignUuid('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('profile_id')->nullable()->constrained('profiles')->cascadeOnDelete();  // confirmation parentale d'un adolescent
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('max_attempts')->default(5);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['destination', 'purpose']);
            $table->index('expires_at');
        });

        DB::statement("ALTER TABLE verification_codes ADD CONSTRAINT verification_codes_channel_check CHECK (channel IN ('sms', 'email'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_codes');
    }
};