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
        Schema::create('evaluacion_respuestas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluacion_docente_id')->constrained('evaluaciones_docentes')->cascadeOnDelete();
            $table->foreignId('formulario_criterio_id')->constrained('formulario_criterios')->restrictOnDelete();
            $table->decimal('valor_numerico', total: 5, places: 2)->nullable();
            $table->text('valor_texto')->nullable();
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->unique(['evaluacion_docente_id', 'formulario_criterio_id'], 'eval_respuesta_unique_criterio');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evaluacion_respuestas');
    }
};
