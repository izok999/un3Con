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
        Schema::table('users', function (Blueprint $table) {
            $table->string('documento')->unique()->after('email');
            $table->string('password')->nullable()->change();
            $table->string('auth_provider')->nullable()->after('password');
            $table->string('auth_provider_id')->nullable()->after('auth_provider');
            $table->string('avatar')->nullable()->after('auth_provider_id');
            $table->unique(['auth_provider', 'auth_provider_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['auth_provider', 'auth_provider_id']);
            $table->dropColumn(['documento', 'auth_provider', 'auth_provider_id', 'avatar']);
            $table->string('password')->nullable(false)->change();
        });
    }
};
