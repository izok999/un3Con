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
        Schema::create('formularios_evaluacion', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('tipo_evaluador', 20);
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('escala_min')->default(1);
            $table->unsignedSmallInteger('escala_max')->default(5);
            $table->timestamps();

            $table->unique(['nombre', 'tipo_evaluador'], 'formularios_eval_unique_tipo');
            $table->index(['tipo_evaluador', 'activo'], 'formularios_eval_tipo_activo_idx');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE formularios_evaluacion ADD CONSTRAINT formularios_eval_tipo_check CHECK (tipo_evaluador IN ('alumno', 'funcionario'))");
            DB::statement('ALTER TABLE formularios_evaluacion ADD CONSTRAINT formularios_eval_escala_check CHECK (escala_min >= 0 AND escala_max >= escala_min)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('formularios_evaluacion');
    }
};
