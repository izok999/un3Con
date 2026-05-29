<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['docente_id', 'car_id', 'sed_id', 'ple_id', 'mi2_id', 'tur_id', 'sec_id', 'activo'])]
class DocenteContexto extends Model
{
    use HasFactory;

    public function docente(): BelongsTo
    {
        return $this->belongsTo(Docente::class);
    }

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }
}
