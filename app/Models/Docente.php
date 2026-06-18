<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['user_id', 'docente_externo_id', 'documento', 'nombre', 'activo'])]
class Docente extends Model
{
    use HasFactory, SoftDeletes;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contextos(): HasMany
    {
        return $this->hasMany(DocenteContexto::class);
    }

    public function evaluaciones(): HasMany
    {
        return $this->hasMany(EvaluacionDocente::class);
    }

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }
}
