<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('pin_hash')->nullable();                          // PIN du parent (haché)
            $table->unsignedSmallInteger('pin_failed_attempts')->default(0);
            $table->timestamp('pin_locked_until')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['pin_hash', 'pin_failed_attempts', 'pin_locked_until']);
        });
    }
};