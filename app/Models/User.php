<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\RoleName;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Spatie\Permission\Traits\HasRoles;

#[Fillable([
    'name',
    'email',
    'password',
    'documento',
    'email_verified_at',
    'auth_provider',
    'auth_provider_id',
    'avatar',
    'locale',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    public function academicUnitScopes(): HasMany
    {
        return $this->hasMany(UserAcademicUnitScope::class);
    }

    public function isGeneralAdmin(): bool
    {
        return $this->hasRole(RoleName::Admin->value);
    }

    public function isAcademicUnitAdmin(): bool
    {
        return $this->hasRole(RoleName::AdminUnidadAcademica->value);
    }

    /**
     * @return array<int, int>
     */
    public function managedSedeIds(): array
    {
        /** @var Collection<int, UserAcademicUnitScope> $scopes */
        $scopes = $this->academicUnitScopes()
            ->with('academicUnit:id,legacy_sede_ids')
            ->get();

        return $scopes
            ->flatMap(static fn (UserAcademicUnitScope $scope): array => $scope->resolvedSedeIds())
            ->sort()
            ->unique()
            ->values()
            ->all();
    }

    public function canManageSede(?int $sedId): bool
    {
        if ($this->isGeneralAdmin()) {
            return true;
        }

        if (! $this->isAcademicUnitAdmin() || $sedId === null) {
            return false;
        }

        return in_array($sedId, $this->managedSedeIds(), true);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
