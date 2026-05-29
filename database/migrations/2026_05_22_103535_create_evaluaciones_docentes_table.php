<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('evaluaciones_docentes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periodo_evaluacion_id')->constrained('periodos_evaluacion')->restrictOnDelete();
            $table->foreignId('formulario_evaluacion_id')->constrained('formularios_evaluacion')->restrictOnDelete();
            $table->foreignId('docente_id')->constrained('docentes')->restrictOnDelete();
            $table->foreignId('evaluador_user_id')->constrained('users')->restrictOnDelete();
            $table->string('tipo_evaluador', 20);
            $table->decimal('puntaje_total', total: 5, places: 2)->default(0);
            $table->string('estado', 20)->default('enviada');
            $table->timestamp('fecha_envio')->nullable();
            $table->string('docente_nombre_snapshot');
            $table->string('docente_documento_snapshot', 20)->nullable();
            $table->json('contexto_snapshot')->nullable();
            $table->timestamps();

            $table->unique(
                ['periodo_evaluacion_id', 'formulario_evaluacion_id', 'docente_id', 'evaluador_user_id'],
                'eval_docente_unique_envio',
            );
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE evaluaciones_docentes ADD CONSTRAINT evaluaciones_docentes_tipo_check CHECK (tipo_evaluador IN ('alumno', 'funcionario'))");
            DB::statement("ALTER TABLE evaluaciones_docentes ADD CONSTRAINT evaluaciones_docentes_estado_check CHECK (estado IN ('borrador', 'enviada'))");
            DB::statement('ALTER TABLE evaluaciones_docentes ADD CONSTRAINT evaluaciones_docentes_puntaje_check CHECK (puntaje_total >= 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evaluaciones_docentes');
    }
};
