<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nombre', 'ple_codigo', 'fecha_inicio', 'fecha_fin', 'activo'])]
class PeriodoEvaluacion extends Model
{
    use HasFactory;

    protected $table = 'periodos_evaluacion';

    public function evaluaciones(): HasMany
    {
        return $this->hasMany(EvaluacionDocente::class, 'periodo_evaluacion_id');
    }

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'activo' => 'boolean',
        ];
    }
}
