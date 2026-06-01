<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'slug',
    'name',
    'address',
    'office_hours',
    'phone_numbers',
    'email_addresses',
    'website_url',
    'image_url',
    'legacy_sede_ids',
    'is_active',
])]
class AcademicUnit extends Model
{
    public function scopes(): HasMany
    {
        return $this->hasMany(UserAcademicUnitScope::class);
    }

    public function primarySedeId(): ?int
    {
        $sedId = $this->legacy_sede_ids[0] ?? null;

        return is_int($sedId) && $sedId > 0 ? $sedId : null;
    }

    protected function casts(): array
    {
        return [
            'office_hours' => 'array',
            'phone_numbers' => 'array',
            'email_addresses' => 'array',
            'legacy_sede_ids' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
