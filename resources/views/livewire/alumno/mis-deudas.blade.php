<?php

use App\Services\AlumnoExternoService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public ?object $alumno = null;
    public int $aluId = 0;
    public string $error = '';
    public string $tab = 'timeline';

    public function mount(AlumnoExternoService $service): void
    {
        $documento = auth()->user()?->documento;

        if (! $documento) {
            $this->error = 'Tu cuenta no tiene documento asociado. Contactá al administrador.';
            return;
        }

        $this->alumno = $service->resolverAlumno($documento);

        if (! $this->alumno) {
            $this->error = 'No se encontró un alumno con el documento registrado en tu cuenta.';
            return;
        }

        $this->aluId = (int) $this->alumno->alu_id;
    }

    #[Computed]
    public function deudas(): Collection
    {
        if ($this->aluId === 0) {
            return collect();
        }

        return $this->prepareDeudas(app(AlumnoExternoService::class)->deudas($this->aluId));
    }

    #[Computed]
    public function pagos(): Collection
    {
        if ($this->aluId === 0) {
            return collect();
        }

        return $this->preparePagos(app(AlumnoExternoService::class)->pagosAlumno($this->aluId));
    }

    #[Computed]
    public function timeline(): Collection
    {
        return $this->buildTimeline($this->deudas, $this->pagos);
    }

    #[Computed]
    public function total(): float|int
    {
        return $this->deudas->sum('dit_saldo');
    }

    #[Computed]
    public function totalPagado(): float|int
    {
        return $this->pagos->sum('cob_monto');
    }

    protected function prepareDeudas(Collection $deudas): Collection
    {
        return $deudas
            ->map(function (object $deuda): object {
                $deuda->timeline_timestamp = $this->resolveDateTimestamp($deuda->dit_vencim ?? null);
                $deuda->timeline_date = $this->formatDate($deuda->dit_vencim ?? null);

                return $deuda;
            })
            ->sortBy('timeline_timestamp')
            ->values();
    }

    protected function preparePagos(Collection $pagos): Collection
    {
        return $pagos
            ->map(function (object $pago): object {
                $pago->timeline_timestamp = $this->resolveDateTimestamp($pago->cob_fecha ?? null);
                $pago->timeline_date = $this->formatDate($pago->cob_fecha ?? null);

                return $pago;
            })
            ->sortBy('timeline_timestamp')
            ->values();
    }

    protected function buildTimeline(Collection $deudas, Collection $pagos): Collection
    {
        $timelineDeudas = $deudas->map(function (object $deuda): object {
            $periodo = collect([$deuda->ple_codigo ?? null, $deuda->ple_descri ?? null])
                ->filter(fn (mixed $value): bool => filled($value))
                ->implode(' · ');

            return (object) [
                'type' => 'deuda',
                'concept' => $deuda->aca_descri ?? 'Deuda pendiente',
                'date' => $deuda->timeline_date ?? '—',
                'sort_timestamp' => (int) ($deuda->timeline_timestamp ?? PHP_INT_MAX),
                'reference' => $periodo !== '' ? $periodo : 'Saldo pendiente',
                'amount' => (float) ($deuda->dit_saldo ?? 0),
                'secondary' => $deuda->uac_descri ?? null,
                'status' => $deuda->deu_situac ?? null,
            ];
        });

        $timelinePagos = $pagos->map(function (object $pago): object {
            $referencia = filled($pago->cob_numero ?? null)
                ? 'Recibo #'.$pago->cob_numero
                : 'Pago registrado';

            if (filled($pago->cob_perceptor ?? null)) {
                $referencia .= ' · '.$pago->cob_perceptor;
            }

            return (object) [
                'type' => 'pago',
                'concept' => trim((string) ($pago->cob_arancel ?? $pago->mat_descri ?? 'Pago registrado')),
                'date' => $pago->timeline_date ?? '—',
                'sort_timestamp' => (int) ($pago->timeline_timestamp ?? PHP_INT_MAX),
                'reference' => $referencia,
                'amount' => (float) ($pago->cob_monto ?? 0),
                'secondary' => $pago->uac_descri ?? null,
                'status' => 'PAGADO',
            ];
        });

        return $timelineDeudas
            ->merge($timelinePagos)
            ->sortBy('sort_timestamp')
            ->values();
    }

    protected function resolveDateTimestamp(mixed $value): int
    {
        $date = $this->parseDate($value);

        return $date?->timestamp ?? PHP_INT_MAX;
    }

    protected function formatDate(mixed $value): string
    {
        $date = $this->parseDate($value);

        return $date?->format('d/m/Y') ?? '—';
    }

    protected function parseDate(mixed $value): ?Carbon
    {
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        $dateValue = trim((string) $value);

        if ($dateValue === '') {
            return null;
        }

        foreach (['d/m/Y', 'Y-m-d', 'd/m/Y H:i:s', 'Y-m-d H:i:s'] as $format) {
            try {
                return Carbon::createFromFormat($format, $dateValue);
            } catch (\Throwable) {
                // Try the next known legacy format.
            }
        }

        try {
            return Carbon::parse($dateValue);
        } catch (\Throwable) {
            return null;
        }
    }
};
?>

<div>
    <x-mary-header title="Mis Deudas" subtitle="Estado financiero con deudas activas, pagos legacy e historial cronológico" separator />

    @if($error !== '')
        <x-mary-alert title="{{ $error }}" icon="o-exclamation-triangle" class="alert-warning" />
    @else
        <div class="mb-4 grid gap-4 md:grid-cols-3">
            <x-mary-stat
                title="Total pendiente"
                value="Gs {{ number_format($this->total, 0, ',', '.') }}"
                icon="o-banknotes"
                class="bg-error/10 text-error"
            />

            <x-mary-stat
                title="Pagos registrados"
                value="Gs {{ number_format($this->totalPagado, 0, ',', '.') }}"
                icon="o-check-badge"
                class="bg-success/10 text-success"
            />

            <x-mary-stat
                title="Movimientos visibles"
                value="{{ $this->timeline->count() }}"
                icon="o-arrows-right-left"
                class="bg-info/10 text-info"
            />
        </div>

        <x-mary-card shadow>
            <div role="tablist" class="tabs tabs-bordered mb-4">
                <button wire:click="$set('tab', 'timeline')" role="tab" class="tab {{ $tab === 'timeline' ? 'tab-active' : '' }}">
                    Timeline ({{ $this->timeline->count() }})
                </button>
                <button wire:click="$set('tab', 'deudas')" role="tab" class="tab {{ $tab === 'deudas' ? 'tab-active' : '' }}">
                    Deudas ({{ $this->deudas->count() }})
                </button>
                <button wire:click="$set('tab', 'pagos')" role="tab" class="tab {{ $tab === 'pagos' ? 'tab-active' : '' }}">
                    Pagos legacy ({{ $this->pagos->count() }})
                </button>
            </div>

            @if($tab === 'timeline')
                @if($this->timeline->isEmpty())
                    <x-mary-alert title="No hay movimientos financieros registrados todavía." icon="o-information-circle" class="alert-info" />
                @else
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Tipo</th>
                                    <th>Concepto</th>
                                    <th>Fecha</th>
                                    <th>Referencia</th>
                                    <th class="text-right">Importe</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($this->timeline as $movimiento)
                                    <tr class="hover">
                                        <td>
                                            @if($movimiento->type === 'pago')
                                                <x-mary-badge value="Pago" class="badge-success badge-sm" />
                                            @else
                                                <x-mary-badge value="Deuda" class="badge-warning badge-sm" />
                                            @endif
                                        </td>
                                        <td>
                                            <div class="font-medium">{{ $movimiento->concept }}</div>
                                            @if(filled($movimiento->secondary ?? null))
                                                <div class="text-xs text-base-content/55">{{ $movimiento->secondary }}</div>
                                            @endif
                                        </td>
                                        <td>{{ $movimiento->date }}</td>
                                        <td>{{ $movimiento->reference }}</td>
                                        <td class="text-right font-bold {{ $movimiento->type === 'pago' ? 'text-success' : 'text-error' }}">
                                            Gs {{ number_format($movimiento->amount, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endif

            @if($tab === 'deudas')
                @if($this->deudas->isEmpty())
                    <x-mary-alert title="No tenés deudas pendientes." icon="o-check-circle" class="alert-success" />
                @else
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Arancel</th>
                                    <th>Carrera</th>
                                    <th>Periodo</th>
                                    <th>Vencimiento</th>
                                    <th class="text-right">Saldo</th>
                                    <th class="text-center">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($this->deudas as $d)
                                    <tr class="hover">
                                        <td>{{ $d->aca_descri }}</td>
                                        <td>{{ $d->uac_descri }}</td>
                                        <td>{{ $d->ple_codigo }}: {{ $d->ple_descri }}</td>
                                        <td>{{ $d->timeline_date }}</td>
                                        <td class="text-right font-bold">
                                            Gs {{ number_format($d->dit_saldo ?? 0, 0, ',', '.') }}
                                        </td>
                                        <td class="text-center">
                                            @if($d->deu_situac === 'P')
                                                <x-mary-badge value="Pendiente" class="badge-warning badge-sm" />
                                            @elseif($d->deu_situac === 'C')
                                                <x-mary-badge value="Cancelado" class="badge-success badge-sm" />
                                            @else
                                                <x-mary-badge value="{{ $d->deu_situac }}" class="badge-neutral badge-sm" />
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endif

            @if($tab === 'pagos')
                @if($this->pagos->isEmpty())
                    <x-mary-alert title="Todavía no hay pagos históricos visibles en legacy." icon="o-information-circle" class="alert-info" />
                @else
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Arancel</th>
                                    <th>Facultad</th>
                                    <th>Fecha</th>
                                    <th>Recibo</th>
                                    <th>Perceptor</th>
                                    <th class="text-right">Monto</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($this->pagos as $pago)
                                    <tr class="hover">
                                        <td>{{ $pago->cob_arancel ?? $pago->mat_descri ?? 'Pago registrado' }}</td>
                                        <td>{{ $pago->uac_descri ?? '—' }}</td>
                                        <td>{{ $pago->timeline_date }}</td>
                                        <td>{{ $pago->cob_numero ?? '—' }}</td>
                                        <td>{{ $pago->cob_perceptor ?? '—' }}</td>
                                        <td class="text-right font-bold text-success">
                                            Gs {{ number_format($pago->cob_monto ?? 0, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endif
        </x-mary-card>
    @endif
</div>
