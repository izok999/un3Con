<?php

namespace App\Console\Commands;

use App\Services\LegacyAlumnoUserSyncService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('alumnos:sync-legacy-users
    {--documento= : Sincroniza un solo documento}
    {--solo-faltantes : Solo crea usuarios inexistentes}
    {--chunk=500 : Tamaño del lote}
    {--dry-run : Simula el proceso sin guardar cambios}
    {--carrera= : Limita a alumnos con habilitación en la carrera (car_id)}
    {--sede= : Limita a alumnos con habilitación en la sede (sed_id)}
    {--unidad= : Limita por unidad académica (coincidencia parcial sobre uac_descri)}
    {--periodo-desde= : Limita a habilitaciones desde ese periodo lectivo (ple_codigo)}')]
#[Description('Sincroniza usuarios locales de alumnos desde la base legacy')]
class SyncLegacyAlumnoUsersCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(LegacyAlumnoUserSyncService $legacyAlumnoUserSyncService): int
    {
        foreach (['carrera', 'sede', 'periodo-desde'] as $numericOption) {
            $value = $this->option($numericOption);

            if ($value !== null && ! ctype_digit((string) $value)) {
                $this->error("La opción --{$numericOption} debe ser numérica (recibido: {$value}).");

                return self::FAILURE;
            }
        }

        $filters = array_filter([
            'carrera' => $this->option('carrera'),
            'sede' => $this->option('sede'),
            'unidad' => $this->option('unidad'),
            'periodo-desde' => $this->option('periodo-desde'),
        ], fn (?string $value): bool => filled($value));

        if ($filters !== []) {
            $this->info('Filtros aplicados: '.collect($filters)->map(fn ($value, $key) => "--{$key}={$value}")->implode(' '));
        }

        $result = $legacyAlumnoUserSyncService->sync([
            'documento' => $this->option('documento'),
            'solo_faltantes' => (bool) $this->option('solo-faltantes'),
            'chunk' => (int) $this->option('chunk'),
            'dry_run' => (bool) $this->option('dry-run'),
            'carrera' => $this->option('carrera'),
            'sede' => $this->option('sede'),
            'unidad' => $this->option('unidad'),
            'periodo_desde' => $this->option('periodo-desde'),
        ]);

        $this->newLine();
        $this->info('Sincronización finalizada.');
        $this->line('Procesados: '.$result['processed']);
        $this->line('Creados: '.$result['created']);
        $this->line('Actualizados: '.$result['updated']);
        $this->line('Omitidos: '.$result['skipped']);
        $this->line('Conflictos: '.$result['conflicts']);
        $this->line('Errores: '.$result['errors']);

        return self::SUCCESS;
    }
}
