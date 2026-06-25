<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluaciones_docentes', function (Blueprint $table) {
            $table->foreignId('docente_contexto_id')
                ->nullable()
                ->after('formulario_evaluacion_id')
                ->constrained('docente_contextos')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('evaluaciones_docentes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('docente_contexto_id');
        });
    }
};
