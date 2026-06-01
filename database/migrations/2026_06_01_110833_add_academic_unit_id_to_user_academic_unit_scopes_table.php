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
        Schema::table('user_academic_unit_scopes', function (Blueprint $table) {
            $table->foreignId('academic_unit_id')
                ->nullable()
                ->after('user_id')
                ->constrained('academic_units')
                ->nullOnDelete();

            $table->unique(['user_id', 'academic_unit_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_academic_unit_scopes', function (Blueprint $table) {
            $table->dropUnique('user_academic_unit_scopes_user_id_academic_unit_id_unique');
            $table->dropConstrainedForeignId('academic_unit_id');
        });
    }
};
