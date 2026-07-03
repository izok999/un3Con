<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vincula la campaña de evaluación con el periodo lectivo del sistema
     * externo (ple_codigo). NULL mantiene el comportamiento anterior:
     * inscripciones vigentes (imi_vigent) en lugar de un periodo concreto.
     */
    public function up(): void
    {
        Schema::table('periodos_evaluacion', function (Blueprint $table) {
            $table->string('ple_codigo', 20)->nullable()->after('nombre');
        });
    }

    public function down(): void
    {
        Schema::table('periodos_evaluacion', function (Blueprint $table) {
            $table->dropColumn('ple_codigo');
        });
    }
};
