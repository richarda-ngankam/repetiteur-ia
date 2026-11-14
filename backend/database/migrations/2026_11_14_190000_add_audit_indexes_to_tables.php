<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('guardianships', function (Blueprint $table) {
            $table->index(['guardian_id', 'status']);
        });

        Schema::table('profiles', function (Blueprint $table) {
            $table->index('created_by');
        });

        Schema::table('consents', function (Blueprint $table) {
            $table->index('profile_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('guardianships', function (Blueprint $table) {
            $table->dropIndex(['guardian_id', 'status']);
        });

        Schema::table('profiles', function (Blueprint $table) {
            $table->dropIndex(['created_by']);
        });

        Schema::table('consents', function (Blueprint $table) {
            $table->dropIndex(['profile_id']);
        });
    }
};
