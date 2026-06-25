<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'periodo_evaluacion_id',
    'formulario_evaluacion_id',
    'docente_contexto_id',
    'docente_id',
    'evaluador_user_id',
    'tipo_evaluador',
    'puntaje_total',
    'estado',
    'fecha_envio',
    'docente_nombre_snapshot',
    'docente_documento_snapshot',
    'contexto_snapshot',
])]
class EvaluacionDocente extends Model
{
    public const ESTADO_BORRADOR = 'borrador';

    public const ESTADO_ENVIADA = 'enviada';

    use HasFactory;

    protected $table = 'evaluaciones_docentes';

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(PeriodoEvaluacion::class, 'periodo_evaluacion_id');
    }

    public function formulario(): BelongsTo
    {
        return $this->belongsTo(FormularioEvaluacion::class, 'formulario_evaluacion_id');
    }

    public function docente(): BelongsTo
    {
        return $this->belongsTo(Docente::class);
    }

    public function docenteContexto(): BelongsTo
    {
        return $this->belongsTo(DocenteContexto::class);
    }

    public function evaluador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluador_user_id');
    }

    public function respuestas(): HasMany
    {
        return $this->hasMany(EvaluacionRespuesta::class, 'evaluacion_docente_id');
    }

    protected function casts(): array
    {
        return [
            'puntaje_total' => 'decimal:2',
            'fecha_envio' => 'datetime',
            'contexto_snapshot' => 'array',
        ];
    }
}
