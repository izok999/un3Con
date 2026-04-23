<?php

namespace App\Console\Commands;

use App\Services\LegacyAlumnoUserSyncService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('alumnos:sync-legacy-users {--documento= : Sincroniza un solo documento} {--solo-faltantes : Solo crea usuarios inexistentes} {--chunk=500 : Tamaño del lote} {--dry-run : Simula el proceso sin guardar cambios}')]
#[Description('Sincroniza usuarios locales de alumnos desde la base legacy')]
class SyncLegacyAlumnoUsersCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(LegacyAlumnoUserSyncService $legacyAlumnoUserSyncService): int
    {
        $result = $legacyAlumnoUserSyncService->sync([
            'documento' => $this->option('documento'),
            'solo_faltantes' => (bool) $this->option('solo-faltantes'),
            'chunk' => (int) $this->option('chunk'),
            'dry_run' => (bool) $this->option('dry-run'),
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
