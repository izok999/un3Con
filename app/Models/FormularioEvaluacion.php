<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nombre', 'tipo_evaluador', 'descripcion', 'activo', 'escala_min', 'escala_max'])]
class FormularioEvaluacion extends Model
{
    public const TIPO_ALUMNO = 'alumno';

    public const TIPO_FUNCIONARIO = 'funcionario';

    use HasFactory;

    protected $table = 'formularios_evaluacion';

    public function criterios(): HasMany
    {
        return $this->hasMany(FormularioCriterio::class, 'formulario_evaluacion_id')->orderBy('orden');
    }

    public function evaluaciones(): HasMany
    {
        return $this->hasMany(EvaluacionDocente::class, 'formulario_evaluacion_id');
    }

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'escala_min' => 'integer',
            'escala_max' => 'integer',
        ];
    }
}
