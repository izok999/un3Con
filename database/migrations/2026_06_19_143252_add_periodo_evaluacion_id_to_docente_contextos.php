<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('docente_contextos', function (Blueprint $table) {
            $table->foreignId('periodo_evaluacion_id')
                ->nullable()
                ->after('ple_id')
                ->constrained('periodos_evaluacion')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('docente_contextos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('periodo_evaluacion_id');
        });
    }
};
