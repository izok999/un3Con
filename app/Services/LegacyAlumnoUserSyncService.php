<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;
use Throwable;
use Spatie\Permission\Models\Role;

class LegacyAlumnoUserSyncService
{
    /**
     * @param  array{documento?: string|null, solo_faltantes?: bool, chunk?: int, dry_run?: bool}  $options
     * @return array{processed: int, created: int, updated: int, skipped: int, conflicts: int, errors: int}
     */
    public function __construct(public AlumnoExternoService $alumnoExternoService)
    {
    }

    /**
     * @param  array{documento?: string|null, solo_faltantes?: bool, chunk?: int, dry_run?: bool}  $options
     * @return array{processed: int, created: int, updated: int, skipped: int, conflicts: int, errors: int}
     */
    public function sync(array $options = []): array
    {
        $stats = [
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'conflicts' => 0,
            'errors' => 0,
        ];

        $documento = $this->normalizeDocumento($options['documento'] ?? null);
        $onlyMissing = (bool) ($options['solo_faltantes'] ?? false);
        $chunkSize = max(1, (int) ($options['chunk'] ?? 500));
        $dryRun = (bool) ($options['dry_run'] ?? false);

        Role::findOrCreate('ALUMNO', 'web');

        $this->alumnoExternoService
            ->alumnosParaSincronizar($documento)
            ->chunk($chunkSize)
            ->each(function (iterable $legacyStudents) use (&$stats, $dryRun, $onlyMissing): void {
                foreach ($legacyStudents as $legacyStudent) {
                    $this->syncLegacyStudent((array) $legacyStudent, $stats, $dryRun, $onlyMissing);
                }
            });

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $legacyStudent
     * @param  array{processed: int, created: int, updated: int, skipped: int, conflicts: int, errors: int}  $stats
     */
    protected function syncLegacyStudent(array $legacyStudent, array &$stats, bool $dryRun, bool $onlyMissing): void
    {
        $stats['processed']++;

        $documento = $this->normalizeDocumento($legacyStudent['alu_perdoc'] ?? null);

        if ($documento === null) {
            $stats['skipped']++;

            return;
        }

        if ((int) ($legacyStudent['duplicate_count'] ?? 1) > 1) {
            $stats['conflicts']++;

            return;
        }

        $existingUser = User::query()->firstWhere('documento', $documento);

        if ($onlyMissing && $existingUser) {
            $stats['skipped']++;

            return;
        }

        $email = $this->resolveEmail($existingUser, $documento);

        if ($this->hasEmailConflict($email, $documento)) {
            $stats['conflicts']++;

            return;
        }

        if ($dryRun) {
            if ($existingUser) {
                $stats['updated']++;
            } else {
                $stats['created']++;
            }

            return;
        }

        try {
            $attributes = [
                'name' => $this->resolveName($legacyStudent, $existingUser),
                'email' => $email,
            ];

            if (! $existingUser) {
                $attributes['password'] = Str::random(40);
                $attributes['email_verified_at'] = null;
            }

            $user = User::query()->updateOrCreate(
                ['documento' => $documento],
                $attributes,
            );

            if (! $user->hasRole('ALUMNO')) {
                $user->assignRole('ALUMNO');
            }

            if ($existingUser) {
                $stats['updated']++;
            } else {
                $stats['created']++;
            }
        } catch (Throwable $exception) {
            report($exception);
            $stats['errors']++;
        }
    }

    protected function normalizeDocumento(mixed $documento): ?string
    {
        if (! is_string($documento) || trim($documento) === '') {
            return null;
        }

        $normalizedDocumento = preg_replace('/\D+/', '', trim($documento));

        if ($normalizedDocumento === null || $normalizedDocumento === '') {
            $normalizedDocumento = trim($documento);
        }

        return $normalizedDocumento !== '' ? $normalizedDocumento : null;
    }

    /**
     * @param  array<string, mixed>  $legacyStudent
     */
    protected function resolveName(array $legacyStudent, ?User $existingUser): string
    {
        $firstName = trim((string) ($legacyStudent['per_nombre'] ?? ''));
        $lastName = trim((string) ($legacyStudent['per_apelli'] ?? ''));
        $fullName = trim($firstName.' '.$lastName);

        if ($fullName !== '') {
            return $fullName;
        }

        if ($existingUser && $existingUser->name !== '') {
            return $existingUser->name;
        }

        return 'Alumno';
    }

    protected function resolveEmail(?User $existingUser, string $documento): string
    {
        if ($existingUser && $existingUser->email !== '') {
            return $existingUser->email;
        }

        return sprintf('alumno-%s@consultor.invalid', $documento);
    }

    protected function hasEmailConflict(string $email, string $documento): bool
    {
        return User::query()
            ->where('email', $email)
            ->where('documento', '!=', $documento)
            ->exists();
    }
}
