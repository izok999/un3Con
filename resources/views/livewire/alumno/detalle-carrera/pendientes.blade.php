<?php

use App\Services\AlumnoExternoService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Lazy;
use Livewire\Volt\Component;

new #[Lazy] class extends Component
{
    public int $aluId = 0;
    public int $halId = 0;
    public int $carId = 0;
    public int $rscId = 0;
    public Collection $materiasPendientes;
    public bool $usesFallbackFilter = false;

    public function boot(): void
    {
        $this->materiasPendientes = collect();
    }

    public function mount(int $aluId, int $halId, int $carId, int $rscId, AlumnoExternoService $service): void
    {
        $this->aluId = $aluId;
        $this->halId = $halId;
        $this->carId = $carId;
        $this->rscId = $rscId;

        $malla = $service->mallaCurricular($aluId);

        $mallaFiltrada = $this->filterByContext($malla);
        $pendientes = $mallaFiltrada->filter(fn (mixed $fila): bool => $this->isPending($fila))->values();

        if ($pendientes->isEmpty() && $mallaFiltrada->isNotEmpty() && ! $this->hasRecognizedPendingField($mallaFiltrada)) {
            $this->usesFallbackFilter = true;
            $pendientes = $mallaFiltrada->values();
        }

        $this->materiasPendientes = $pendientes
            ->sortBy([
                fn (mixed $fila): string => (string) ($this->field($fila, ['niv_descri', 'sem_descri', 'cur_descri', 'cic_descri', 'nivel', 'curso']) ?? ''),
                fn (mixed $fila): string => (string) ($this->field($fila, ['mat_descri', 'mtr_descri', 'asi_descri', 'materia']) ?? ''),
            ])
            ->values();
    }

    protected function filterByContext(Collection $malla): Collection
    {
        $mallaFiltrada = $this->filterByFirstAvailableField($malla, $this->carId, [
            'car_id',
            'hal_idcar',
            'mll_idcar',
            'maa_idcar',
            'mal_idcar',
            'pac_id',
        ]);

        $mallaFiltrada = $this->filterByFirstAvailableField($mallaFiltrada, $this->rscId, [
            'rsc_id',
            'hal_idrsc',
            'mll_idrsc',
            'maa_idrsc',
            'mal_idrsc',
        ]);

        return $this->filterByFirstAvailableField($mallaFiltrada, $this->halId, [
            'hal_id',
            'mll_idhal',
            'maa_idhal',
            'mal_idhal',
        ]);
    }

    protected function filterByFirstAvailableField(Collection $rows, int $expectedValue, array $candidateFields): Collection
    {
        if ($expectedValue <= 0) {
            return $rows;
        }

        $field = collect($candidateFields)->first(function (string $candidate) use ($rows): bool {
            return $rows->contains(fn (mixed $row): bool => $this->field($row, [$candidate]) !== null);
        });

        if ($field === null) {
            return $rows;
        }

        return $rows->filter(function (mixed $row) use ($field, $expectedValue): bool {
            return (string) $this->field($row, [$field]) === (string) $expectedValue;
        })->values();
    }

    protected function hasRecognizedPendingField(Collection $rows): bool
    {
        $candidateFields = [
            'mal_estado',
            'maa_estado',
            'mtr_estado',
            'estado',
            'situacion',
            'sit_descri',
            'cal_situac',
            'aprobada',
            'aprobado',
            'mal_aprobada',
            'maa_aprobada',
        ];

        return $rows->contains(function (mixed $row) use ($candidateFields): bool {
            return $this->field($row, $candidateFields) !== null;
        });
    }

    protected function isPending(mixed $fila): bool
    {
        $statusValue = $this->field($fila, [
            'mal_estado',
            'maa_estado',
            'mtr_estado',
            'estado',
            'situacion',
            'sit_descri',
            'cal_situac',
        ]);

        if ($statusValue !== null) {
            if (is_numeric($statusValue)) {
                return (int) $statusValue !== 1;
            }

            $normalizedStatus = Str::of((string) $statusValue)->lower()->ascii()->value();

            if (Str::contains($normalizedStatus, ['aprob', 'convalid', 'homolog'])) {
                return false;
            }

            if (Str::contains($normalizedStatus, ['pend', 'reprob', 'aplaz', 'curs', 'regular', 'inscrip'])) {
                return true;
            }
        }

        $approvedFlag = $this->field($fila, [
            'aprobada',
            'aprobado',
            'mal_aprobada',
            'maa_aprobada',
        ]);

        if ($approvedFlag !== null) {
            return ! filter_var($approvedFlag, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)
                && (string) $approvedFlag !== '1';
        }

        return false;
    }

    protected function field(mixed $fila, array $candidateFields): mixed
    {
        foreach ($candidateFields as $candidateField) {
            if (is_array($fila) && array_key_exists($candidateField, $fila)) {
                return $fila[$candidateField];
            }

            if (is_object($fila) && property_exists($fila, $candidateField)) {
                return $fila->{$candidateField};
            }
        }

        return null;
    }

    public function statusLabel(mixed $fila): string
    {
        $statusValue = $this->field($fila, [
            'mal_estado',
            'maa_estado',
            'mtr_estado',
            'estado',
            'situacion',
            'sit_descri',
        ]);

        if (is_string($statusValue) && trim($statusValue) !== '') {
            return $statusValue;
        }

        if ((int) $this->field($fila, ['cal_situac']) === 1) {
            return 'Aprobada';
        }

        return 'Pendiente';
    }

    public function subjectLabel(mixed $fila): string
    {
        return (string) ($this->field($fila, ['mat_descri', 'mtr_descri', 'asi_descri', 'materia']) ?? 'Materia sin descripcion');
    }

    public function trackLabel(mixed $fila): string
    {
        $parts = collect([
            $this->field($fila, ['niv_descri', 'sem_descri', 'cur_descri', 'cic_descri', 'nivel', 'curso']),
            $this->field($fila, ['are_descri', 'pla_descri', 'trayecto', 'componente']),
        ])->filter(fn (mixed $value): bool => filled($value));

        return $parts->isEmpty() ? 'Trayecto no especificado' : $parts->implode(' · ');
    }

    public function placeholder(): string
    {
        return <<<'HTML'
        <div class="card glass-card">
            <div class="card-body space-y-4">
                <div class="space-y-2">
                    <div class="skeleton h-4 w-36 rounded-full"></div>
                    <div class="skeleton h-6 w-64 rounded-full"></div>
                </div>
                <div class="space-y-3">
                    <div class="skeleton h-16 rounded-[1.25rem]"></div>
                    <div class="skeleton h-16 rounded-[1.25rem]"></div>
                    <div class="skeleton h-16 rounded-[1.25rem]"></div>
                </div>
            </div>
        </div>
        HTML;
    }
}; ?>

<div class="card glass-card">
    <div class="card-body">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="mb-1 text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Malla curricular</h2>
                <h3 class="card-title text-base text-base-content">Materias pendientes</h3>
            </div>
            <x-mary-badge value="{{ $materiasPendientes->count() }}" class="badge-primary badge-sm" />
        </div>

        @if($materiasPendientes->isEmpty())
            <x-mary-alert title="No hay materias pendientes registradas para esta carrera." icon="o-check-circle" class="alert-success mt-2" />
        @else
            @if($usesFallbackFilter)
                <x-mary-alert title="La malla no expone un estado reconocible; se muestran las materias disponibles para esta carrera." icon="o-information-circle" class="alert-info mt-2" />
            @endif

            <div class="mt-4 space-y-3">
                @foreach($materiasPendientes as $materiaPendiente)
                    <div class="rounded-[1.25rem] border border-base-300 bg-base-100/75 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="space-y-1">
                                <p class="font-medium text-base-content">{{ $this->subjectLabel($materiaPendiente) }}</p>
                                <p class="text-sm text-base-content/65">{{ $this->trackLabel($materiaPendiente) }}</p>
                            </div>
                            <x-mary-badge value="{{ $this->statusLabel($materiaPendiente) }}" class="badge-ghost badge-sm" />
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>