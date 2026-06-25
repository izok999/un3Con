<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluaciones_docentes', function (Blueprint $table) {
            $table->dropUnique('eval_docente_unique_envio');
        });

        Schema::table('evaluaciones_docentes', function (Blueprint $table) {
            $table->unique(
                ['periodo_evaluacion_id', 'formulario_evaluacion_id', 'docente_id', 'evaluador_user_id', 'docente_contexto_id'],
                'eval_docente_unique_envio',
            );
        });
    }

    public function down(): void
    {
        Schema::table('evaluaciones_docentes', function (Blueprint $table) {
            $table->dropUnique('eval_docente_unique_envio');
        });

        Schema::table('evaluaciones_docentes', function (Blueprint $table) {
            $table->unique(
                ['periodo_evaluacion_id', 'formulario_evaluacion_id', 'docente_id', 'evaluador_user_id'],
                'eval_docente_unique_envio',
            );
        });
    }
};
