<?php

namespace App\Console\Commands;

use App\Models\Docente;
use App\Models\DocenteContexto;
use App\Services\AlumnoExternoService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('evaluacion:sincronizar-contextos {--periodo= : Código del periodo lectivo (ple_codigo) a sincronizar, p.ej. 2026}')]
#[Description('Sincroniza los DocenteContexto desde las vistas del sistema externo (vw_anexo_items_profesores_questions + vw_alumnos_inscriptos_materias_14).')]
class SincronizarContextosDocentesCommand extends Command
{
    public function handle(AlumnoExternoService $service): int
    {
        $pleCodigo = $this->option('periodo') ? (string) $this->option('periodo') : null;

        $docentes = Docente::query()
            ->where('activo', true)
            ->whereNotNull('documento')
            ->get();

        if ($docentes->isEmpty()) {
            $this->warn('No hay docentes activos con documento registrado.');

            return self::SUCCESS;
        }

        $label = $pleCodigo ? "periodo {$pleCodigo}" : 'todos los periodos';
        $this->info("Sincronizando contextos para {$docentes->count()} docente(s) — {$label}...");

        $created = 0;
        $skipped = 0;

        foreach ($docentes as $docente) {
            $contextos = $service->contextosDocentePorDocumento($docente->documento, $pleCodigo);

            if ($contextos->isEmpty()) {
                $this->line("  <comment>Sin datos externos:</comment> {$docente->nombre} ({$docente->documento})");

                continue;
            }

            foreach ($contextos as $ctx) {
                $contexto = DocenteContexto::firstOrCreate(
                    [
                        'docente_id' => $docente->id,
                        'car_id' => $ctx['car_id'],
                        'sed_id' => $ctx['sed_id'],
                        'ple_id' => $ctx['ple_id'],
                        'mi2_id' => $ctx['mi2_id'],
                        'tur_id' => $ctx['tur_id'],
                        'sec_id' => $ctx['sec_id'],
                    ],
                    ['activo' => true],
                );

                if ($contexto->wasRecentlyCreated) {
                    $created++;
                } else {
                    $skipped++;
                }
            }
        }

        $this->info("Creados: {$created}");
        $this->info("Omitidos: {$skipped}");

        return self::SUCCESS;
    }
}
