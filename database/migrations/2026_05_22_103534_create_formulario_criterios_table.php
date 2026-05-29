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
        Schema::create('formulario_criterios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('formulario_evaluacion_id')->constrained('formularios_evaluacion')->cascadeOnDelete();
            $table->text('pregunta');
            $table->text('descripcion')->nullable();
            $table->decimal('peso', total: 5, places: 2)->default(0);
            $table->unsignedSmallInteger('orden');
            $table->string('tipo_respuesta', 20);
            $table->boolean('obligatoria')->default(true);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['formulario_evaluacion_id', 'orden'], 'form_criterios_unique_orden');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE formulario_criterios ADD CONSTRAINT formulario_criterios_tipo_check CHECK (tipo_respuesta IN ('escala', 'texto', 'mixto'))");
            DB::statement('ALTER TABLE formulario_criterios ADD CONSTRAINT formulario_criterios_peso_check CHECK (peso >= 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('formulario_criterios');
    }
};
