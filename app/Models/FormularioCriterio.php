<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['formulario_evaluacion_id', 'pregunta', 'descripcion', 'peso', 'orden', 'tipo_respuesta', 'obligatoria', 'activo'])]
class FormularioCriterio extends Model
{
    public const TIPO_ESCALA = 'escala';

    public const TIPO_TEXTO = 'texto';

    public const TIPO_MIXTO = 'mixto';

    use HasFactory;

    public function formulario(): BelongsTo
    {
        return $this->belongsTo(FormularioEvaluacion::class, 'formulario_evaluacion_id');
    }

    public function respuestas(): HasMany
    {
        return $this->hasMany(EvaluacionRespuesta::class, 'formulario_criterio_id');
    }

    protected function casts(): array
    {
        return [
            'peso' => 'decimal:2',
            'orden' => 'integer',
            'obligatoria' => 'boolean',
            'activo' => 'boolean',
        ];
    }
}
