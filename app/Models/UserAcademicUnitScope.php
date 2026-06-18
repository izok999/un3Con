<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'academic_unit_id', 'sed_id', 'assigned_by', 'assigned_at'])]
class UserAcademicUnitScope extends Model
{
    public function academicUnit(): BelongsTo
    {
        return $this->belongsTo(AcademicUnit::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<int, int>
     */
    public function resolvedSedeIds(): array
    {
        $academicUnitSedeIds = $this->academicUnit?->legacy_sede_ids ?? [];

        return collect($academicUnitSedeIds)
            ->when($this->sed_id !== null, fn ($collection) => $collection->prepend($this->sed_id))
            ->map(static fn (mixed $sedId): int => (int) $sedId)
            ->filter(static fn (int $sedId): bool => $sedId > 0)
            ->unique()
            ->values()
            ->all();
    }

    protected function casts(): array
    {
        return [
            'academic_unit_id' => 'integer',
            'sed_id' => 'integer',
            'assigned_by' => 'integer',
            'assigned_at' => 'datetime',
        ];
    }
}
