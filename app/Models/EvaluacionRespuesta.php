<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['evaluacion_docente_id', 'formulario_criterio_id', 'valor_numerico', 'valor_texto', 'observacion'])]
class EvaluacionRespuesta extends Model
{
    use HasFactory;

    public function evaluacion(): BelongsTo
    {
        return $this->belongsTo(EvaluacionDocente::class, 'evaluacion_docente_id');
    }

    public function criterio(): BelongsTo
    {
        return $this->belongsTo(FormularioCriterio::class, 'formulario_criterio_id');
    }

    protected function casts(): array
    {
        return [
            'valor_numerico' => 'decimal:2',
        ];
    }
}
