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
        Schema::create('docente_contextos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('docente_id')->constrained('docentes')->cascadeOnDelete();
            $table->integer('car_id')->nullable();
            $table->integer('sed_id')->nullable();
            $table->integer('ple_id')->nullable();
            $table->integer('mi2_id')->nullable();
            $table->integer('tur_id')->nullable();
            $table->integer('sec_id')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(
                ['docente_id', 'car_id', 'sed_id', 'ple_id', 'mi2_id', 'tur_id', 'sec_id'],
                'docente_contextos_unique_match',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('docente_contextos');
    }
};
