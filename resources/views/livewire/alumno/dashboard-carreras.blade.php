<?php

use App\Models\EvaluacionDocente;
use App\Models\PeriodoEvaluacion;
use App\Services\AlumnoExternoService;
use App\Services\EvaluacionDocente\DocentesElegiblesResolver;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Attributes\Lazy;
use Livewire\Volt\Component;

new #[Lazy] class extends Component
{
    public array $activeCareer = [];
    public array $recentPayments = [];
    public array $calendarDays = [];
    public array $eventAlerts = [];
    public array $eventSummary = [];
    public bool $showWelcomeToast = false;
    public bool $showPendingToast = false;
    public int $pendingCount = 0;
    public string $error = '';
    public string $currentMonthLabel = '';
    public int $totalEvents = 0;

    public function boot(): void
    {
        $this->eventSummary = [
            ['label' => 'Parciales', 'count' => 0, 'tone' => 'badge-info'],
            ['label' => 'Finales', 'count' => 0, 'tone' => 'badge-warning'],
            ['label' => 'Académicos', 'count' => 0, 'tone' => 'badge-secondary'],
        ];
    }

    public function mount(AlumnoExternoService $service, DocentesElegiblesResolver $resolver): void
    {
        $user = request()->user();

        if ($user !== null && ! session()->has('dashboard-welcome-toast-shown')) {
            $this->showWelcomeToast = true;
            session()->put('dashboard-welcome-toast-shown', true);
        }

        if ($user !== null && ! session()->has('eval-pending-toast-shown')) {
            $periodoActivo = PeriodoEvaluacion::query()->where('activo', true)->first();

            if ($periodoActivo) {
                // Los docentes elegibles son solo los que dictan materias en las
                // que el alumno está efectivamente inscripto (ver DocentesElegiblesResolver),
                // no todos los docentes activos de la unidad académica.
                $contextoIdsElegibles = $resolver->paraAlumno($user, $periodoActivo)
                    ->pluck('contexto.id')
                    ->filter()
                    ->unique();

                $contextoIdsEvaluados = EvaluacionDocente::query()
                    ->where('periodo_evaluacion_id', $periodoActivo->id)
                    ->where('evaluador_user_id', $user->id)
                    ->whereNotNull('docente_contexto_id')
                    ->pluck('docente_contexto_id')
                    ->map(fn (mixed $id): int => (int) $id)
                    ->unique();

                $this->pendingCount = $contextoIdsElegibles->diff($contextoIdsEvaluados)->count();

                if ($this->pendingCount > 0) {
                    $this->showPendingToast = true;
                    session()->put('eval-pending-toast-shown', true);
                }
            }
        }

        if (! filled($user->documento)) {
            $this->error = 'Tu cuenta no tiene documento asociado. Contactá al administrador.';

            return;
        }

        $alumno = $service->resolverAlumno($user->documento);

        if (! $alumno) {
            $this->error = 'No se encontró un alumno con el documento registrado en tu cuenta.';

            return;
        }

        $carreras = $service->carreras($alumno->alu_id);

        if ($carreras->isEmpty()) {
            $this->error = 'No se encontraron carreras activas o históricas vinculadas a tu documento.';

            return;
        }

        $activeCareer = $this->resolveActiveCareer($carreras);

        if ($activeCareer !== null) {
            $this->activeCareer = $this->normalizeCareer($activeCareer);
        }

        $dashboardPayload = Cache::remember(
            $this->dashboardCacheKey((int) $alumno->alu_id),
            now()->addMinutes(5),
            fn (): array => $this->buildDashboardPayload($service, (int) $alumno->alu_id),
        );

        $this->recentPayments = $dashboardPayload['recent_payments'];
        $this->eventSummary = $dashboardPayload['event_summary'];
        $this->eventAlerts = $dashboardPayload['event_alerts'];
        $this->calendarDays = $dashboardPayload['calendar_days'];
        $this->currentMonthLabel = $dashboardPayload['current_month_label'];
        $this->totalEvents = $dashboardPayload['total_events'];
    }

    protected function dashboardCacheKey(int $aluId): string
    {
        $halId = (int) ($this->activeCareer['hal_id'] ?? 0);
        $sedId = (int) ($this->activeCareer['sed_id'] ?? 0);

        return sprintf('alumno_dashboard_%d_%d_%d_%s', $aluId, $halId, $sedId, app()->getLocale());
    }

    /**
     * @return array{
     *     recent_payments: array<int, array<string, mixed>>,
     *     event_summary: array<int, array<string, mixed>>,
     *     event_alerts: array<int, array<string, mixed>>,
     *     calendar_days: array<int, array<string, mixed>>,
     *     current_month_label: string,
     *     total_events: int
     * }
     */
    protected function buildDashboardPayload(AlumnoExternoService $service, int $aluId): array
    {
        $recentPayments = $this->buildRecentPayments($service->pagosAlumno($aluId));
        $events = $this->buildEvents(
            $this->resolveCareerEvaluations($service),
            $service->avisos($this->activeCareer['sed_id'] ?? null),
        );
        $calendarData = $this->buildCalendarData($events);

        return [
            'recent_payments' => $recentPayments,
            'event_summary' => $this->buildEventSummary($events),
            'event_alerts' => $this->buildAlertFeed($events),
            'calendar_days' => $calendarData['days'],
            'current_month_label' => $calendarData['label'],
            'total_events' => count($events),
        ];
    }

    protected function resolveActiveCareer(Collection $carreras): ?object
    {
        $vigente = $carreras->first(fn (object $carrera): bool => (bool) ($carrera->hal_vigent ?? false));

        if ($vigente instanceof \stdClass) {
            return $vigente;
        }

        $first = $carreras->first();

        return $first instanceof \stdClass ? $first : null;
    }

    protected function normalizeCareer(object $carrera): array
    {
        return [
            'hal_id' => (int) ($carrera->hal_id ?? 0),
            'sed_id' => filled($carrera->sed_id ?? null) ? (int) $carrera->sed_id : null,
            'uac_descri' => (string) ($carrera->uac_descri ?? 'Unidad académica no disponible'),
            'pac_descri' => (string) ($carrera->pac_descri ?? 'Carrera no disponible'),
            'ciu_descri' => (string) ($carrera->ciu_descri ?? 'Sede no disponible'),
            'ple_codigo' => (string) ($carrera->ple_codigo ?? '—'),
            'ple_descri' => (string) ($carrera->ple_descri ?? 'Periodo no disponible'),
            'hal_vigent' => (bool) ($carrera->hal_vigent ?? false),
        ];
    }

    protected function resolveCareerEvaluations(AlumnoExternoService $service): Collection
    {
        $halId = (int) ($this->activeCareer['hal_id'] ?? 0);

        if ($halId === 0) {
            return collect();
        }

        return $service->evaluaciones($halId);
    }

    protected function buildRecentPayments(Collection $payments): array
    {
        return $payments
            ->map(function (object $payment): ?array {
                $date = $this->parseDate($payment->cob_fecha ?? null);
                $reference = filled($payment->cob_numero ?? null)
                    ? 'Recibo #'.$payment->cob_numero
                    : 'Pago registrado';

                if (filled($payment->cob_perceptor ?? null)) {
                    $reference .= ' · '.$payment->cob_perceptor;
                }

                return [
                    'concept' => trim((string) ($payment->cob_arancel ?? $payment->mat_descri ?? 'Pago registrado')),
                    'unit' => (string) ($payment->uac_descri ?? '—'),
                    'date' => $date?->format('d/m/Y') ?? '—',
                    'timestamp' => $date?->timestamp ?? 0,
                    'reference' => $reference,
                    'amount' => (float) ($payment->cob_monto ?? 0),
                ];
            })
            ->filter()
            ->sortByDesc('timestamp')
            ->take(10)
            ->values()
            ->all();
    }

    protected function buildEvents(Collection $evaluations, Collection $notices): array
    {
        return $evaluations
            ->map(fn (object $evaluation): ?array => $this->normalizeEvaluationEvent($evaluation))
            ->merge($notices->map(fn (object $notice): ?array => $this->normalizeNoticeEvent($notice)))
            ->filter()
            ->sortBy('timestamp')
            ->values()
            ->all();
    }

    protected function normalizeEvaluationEvent(object $evaluation): ?array
    {
        $date = $this->parseDate($evaluation->evp_fecha ?? null);

        if ($date === null) {
            return null;
        }

        $type = $this->classifyEventType((string) ($evaluation->tev_descri ?? ''));
        $score = filled($evaluation->epi_puntaj ?? null) && filled($evaluation->evp_ptotal ?? null)
            ? $evaluation->epi_puntaj.' / '.$evaluation->evp_ptotal
            : null;

        return [
            'type' => $type,
            'type_label' => $this->resolveEventTypeLabel($type),
            'tone' => $this->resolveEventTone($type),
            'title' => trim(collect([
                $evaluation->tev_descri ?? 'Evaluación',
                $evaluation->mat_descri ?? null,
            ])->filter(fn (mixed $value): bool => filled($value))->implode(' · ')),
            'date' => $date->format('d/m/Y'),
            'timestamp' => $date->timestamp,
            'day_key' => $date->format('Y-m-d'),
            'context' => $score !== null ? 'Puntaje registrado: '.$score : 'Evento académico registrado desde evaluaciones.',
        ];
    }

    protected function normalizeNoticeEvent(object $notice): ?array
    {
        $date = $this->parseDate($this->pickFirstFilled($notice, ['avi_fecha', 'fecha', 'event_date']));

        if ($date === null) {
            return null;
        }

        $title = $this->pickFirstFilled($notice, [
            'avi_titulo',
            'avi_descri',
            'avi_detall',
            'avi_asunto',
            'avi_nombre',
        ]);

        $context = $this->pickFirstFilled($notice, [
            'avi_resumen',
            'avi_obs',
            'avi_detall',
            'avi_descri',
        ]);

        return [
            'type' => 'academic',
            'type_label' => 'Académico',
            'tone' => 'badge-secondary',
            'title' => (string) ($title ?: 'Aviso académico de participación'),
            'date' => $date->format('d/m/Y'),
            'timestamp' => $date->timestamp,
            'day_key' => $date->format('Y-m-d'),
            'context' => (string) ($context ?: 'Aviso activo de la sede o de la comunidad académica.'),
        ];
    }

    protected function buildEventSummary(array $events): array
    {
        $groupedEvents = collect($events)->countBy('type');

        return [
            ['label' => 'Parciales', 'count' => (int) ($groupedEvents->get('partial') ?? 0), 'tone' => 'badge-info'],
            ['label' => 'Finales', 'count' => (int) ($groupedEvents->get('final') ?? 0), 'tone' => 'badge-warning'],
            ['label' => 'Académicos', 'count' => (int) ($groupedEvents->get('academic') ?? 0), 'tone' => 'badge-secondary'],
        ];
    }

    protected function buildAlertFeed(array $events): array
    {
        $today = CarbonImmutable::today();

        $upcoming = collect($events)
            ->filter(fn (array $event): bool => $event['timestamp'] >= $today->startOfDay()->timestamp)
            ->take(6);

        $feed = $upcoming->isNotEmpty()
            ? $upcoming
            : collect($events)->sortByDesc('timestamp')->take(6);

        return $feed
            ->map(function (array $event) use ($today): array {
                $eventDate = CarbonImmutable::createFromTimestamp($event['timestamp']);
                $daysUntil = $today->diffInDays($eventDate, false);

                $event['alert_state'] = match (true) {
                    $daysUntil < 0 => 'Reciente',
                    $daysUntil <= 7 => 'Próximo',
                    default => 'Programado',
                };

                return $event;
            })
            ->values()
            ->all();
    }

    protected function buildCalendarData(array $events): array
    {
        $today = CarbonImmutable::today();
        $focusMonth = $this->resolveCalendarFocusMonth($events, $today);
        $eventsByDay = collect($events)->groupBy('day_key');
        $gridStart = $focusMonth->startOfMonth()->startOfWeek(CarbonInterface::MONDAY);

        $currentMonthLabel = Str::title($focusMonth->locale(app()->getLocale())->translatedFormat('F Y'));

        return [
            'label' => $currentMonthLabel,
            'days' => collect(range(0, 41))
            ->map(function (int $offset) use ($gridStart, $focusMonth, $today, $eventsByDay): array {
                $day = $gridStart->addDays($offset);
                $dayEvents = $eventsByDay->get($day->format('Y-m-d'), collect());

                return [
                    'label' => $day->format('j'),
                    'day_name' => Str::upper($day->locale(app()->getLocale())->translatedFormat('D')),
                    'in_month' => $day->month === $focusMonth->month,
                    'is_today' => $day->isSameDay($today),
                    'events_count' => $dayEvents instanceof Collection ? $dayEvents->count() : count($dayEvents),
                    'highlight' => collect($dayEvents)->contains(fn (array $event): bool => $event['type'] === 'final') ? 'final' : (collect($dayEvents)->isNotEmpty() ? 'active' : 'idle'),
                ];
            })
            ->all(),
        ];
    }

    protected function resolveCalendarFocusMonth(array $events, CarbonImmutable $today): CarbonImmutable
    {
        $upcomingEvent = collect($events)->first(fn (array $event): bool => $event['timestamp'] >= $today->startOfDay()->timestamp);

        if (is_array($upcomingEvent)) {
            return CarbonImmutable::createFromTimestamp($upcomingEvent['timestamp'])->startOfMonth();
        }

        return $today->startOfMonth();
    }

    protected function classifyEventType(string $description): string
    {
        $normalized = Str::lower($description);

        return match (true) {
            Str::contains($normalized, 'final') => 'final',
            Str::contains($normalized, ['parcial', 'midterm']) => 'partial',
            default => 'academic',
        };
    }

    protected function resolveEventTypeLabel(string $type): string
    {
        return match ($type) {
            'partial' => 'Parcial',
            'final' => 'Final',
            default => 'Académico',
        };
    }

    protected function resolveEventTone(string $type): string
    {
        return match ($type) {
            'partial' => 'badge-info',
            'final' => 'badge-warning',
            default => 'badge-secondary',
        };
    }

    protected function pickFirstFilled(object $payload, array $fields): mixed
    {
        $attributes = (array) $payload;

        foreach ($fields as $field) {
            if (filled($attributes[$field] ?? null)) {
                return $attributes[$field];
            }
        }

        return null;
    }

    protected function parseDate(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::instance($value);
        }

        if (! filled($value)) {
            return null;
        }

        foreach (['d/m/Y', 'Y-m-d', 'Y-m-d H:i:s', 'd-m-Y'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat($format, (string) $value);

                if ($date !== false) {
                    return $date;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return CarbonImmutable::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function formatCurrency(float $amount): string
    {
        return 'Gs '.number_format($amount, 0, ',', '.');
    }

    public function placeholder(): string
    {
        return <<<'HTML'
        <section class="space-y-6">
            <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
                <div class="card glass-card">
                    <div class="card-body gap-4">
                        <div class="space-y-2">
                            <div class="skeleton h-3 w-28"></div>
                            <div class="skeleton h-8 w-72"></div>
                            <div class="skeleton h-4 w-56"></div>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div class="skeleton h-18 rounded-3xl"></div>
                            <div class="skeleton h-18 rounded-3xl"></div>
                            <div class="skeleton h-18 rounded-3xl"></div>
                        </div>
                        <div class="skeleton h-10 w-44 rounded-[1.15rem]"></div>
                    </div>
                </div>
                <div class="card glass-card">
                    <div class="card-body gap-4">
                        <div class="space-y-2">
                            <div class="skeleton h-3 w-24"></div>
                            <div class="skeleton h-7 w-40"></div>
                        </div>
                        <div class="space-y-3">
                            <div class="skeleton h-16 rounded-[1.35rem]"></div>
                            <div class="skeleton h-16 rounded-[1.35rem]"></div>
                            <div class="skeleton h-16 rounded-[1.35rem]"></div>
                            <div class="skeleton h-16 rounded-[1.35rem]"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1.35fr)_minmax(0,1fr)]">
                <div class="card glass-card">
                    <div class="card-body gap-4">
                        <div class="space-y-2">
                            <div class="skeleton h-3 w-24"></div>
                            <div class="skeleton h-7 w-44"></div>
                        </div>
                        <div class="grid grid-cols-7 gap-2">
                            <div class="skeleton h-16 rounded-2xl"></div>
                            <div class="skeleton h-16 rounded-2xl"></div>
                            <div class="skeleton h-16 rounded-2xl"></div>
                            <div class="skeleton h-16 rounded-2xl"></div>
                            <div class="skeleton h-16 rounded-2xl"></div>
                            <div class="skeleton h-16 rounded-2xl"></div>
                            <div class="skeleton h-16 rounded-2xl"></div>
                            <div class="skeleton h-16 rounded-2xl"></div>
                            <div class="skeleton h-16 rounded-2xl"></div>
                            <div class="skeleton h-16 rounded-2xl"></div>
                            <div class="skeleton h-16 rounded-2xl"></div>
                            <div class="skeleton h-16 rounded-2xl"></div>
                            <div class="skeleton h-16 rounded-2xl"></div>
                            <div class="skeleton h-16 rounded-2xl"></div>
                        </div>
                    </div>
                </div>
                <div class="card glass-card">
                    <div class="card-body gap-4">
                        <div class="space-y-2">
                            <div class="skeleton h-3 w-28"></div>
                            <div class="skeleton h-7 w-52"></div>
                        </div>
                        <div class="space-y-3">
                            <div class="skeleton h-20 rounded-[1.35rem]"></div>
                            <div class="skeleton h-20 rounded-[1.35rem]"></div>
                            <div class="skeleton h-20 rounded-[1.35rem]"></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        HTML;
    }
}; ?>

<section class="space-y-6">
    @if($showWelcomeToast)
        <div
            data-testid="dashboard-welcome-toast"
            data-toast-title="Bienvenido/a de nuevo"
            data-toast-description="Ya podés consultar tu carrera vigente, tus últimos pagos y la agenda académica."
            x-data="{}"
            x-init="$nextTick(() => window.toast({
                toast: {
                    title: @js('Bienvenido/a de nuevo'),
                    description: @js('Ya podés consultar tu carrera vigente, tus últimos pagos y la agenda académica.'),
                    css: 'alert-success',
                    timeout: 5000,
                    progressClass: 'progress-success'
                }
            }))"
            class="hidden"
        ></div>
    @endif

    @if($showPendingToast)
        @php
            $evalLink = route('alumno.evaluacion-docente');
        @endphp
        <div
            data-testid="dashboard-eval-pending-toast"
            x-data="{}"
            x-init="$nextTick(() => window.toast({
                toast: {
                    title: @js('Tienes evaluaciones pendientes'),
                    description: {!! Js::from("Tenés {$pendingCount} docente(s) por evaluar en el período activo.<br><a href='{$evalLink}'>Completá tus evaluaciones →</a>") !!},
                    css: 'alert-warning',
                    timeout: 8000,
                    progressClass: 'progress-warning'
                }
            }))"
            class="hidden"
        ></div>
    @endif

    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-base-content/45">Panel del alumno</p>
            <h2 class="text-2xl font-semibold text-primary">Tu carrera vigente, pagos recientes y agenda académica</h2>
        </div>

        <a href="{{ route('alumno.carreras') }}" wire:navigate class="btn btn-outline btn-primary btn-sm w-full sm:w-auto">Ver listado completo</a>
    </div>

    @if($error !== '')
        <div class="alert alert-warning shadow-sm">
            <span>{{ $error }}</span>
        </div>
    @else
        @php($halId = (int) ($activeCareer['hal_id'] ?? 0))
        @php($activeCareerCard = (object) $activeCareer)

        <div class="space-y-4">
            <div class="space-y-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.28em] text-base-content/45">Carrera vigente</p>
                    <h3 class="text-xl font-semibold text-base-content">Tomamos la última habilitación activa para mantener el foco del dashboard</h3>
                </div>

                @include('partials.alumno.carrera-card', ['carrera' => $activeCareerCard])
            </div>

            <article class="card glass-card">
                <div class="card-body gap-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-base-content/45">Estado financiero</p>
                            <h3 class="card-title text-base text-base-content">Últimos 10 pagos</h3>
                        </div>

                        <span class="badge badge-outline badge-primary">{{ count($recentPayments) }}</span>
                    </div>

                    @if($recentPayments === [])
                        <x-mary-alert title="Todavía no hay pagos históricos visibles en legacy." icon="o-information-circle" class="alert-info" />
                    @else
                        <div class="overflow-x-auto">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Concepto</th>
                                        <th>Fecha</th>
                                        <th>Referencia</th>
                                        <th class="text-right">Importe</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentPayments as $payment)
                                        <tr class="hover">
                                            <td>
                                                <div class="font-medium">{{ $payment['concept'] }}</div>
                                                @if(filled($payment['unit'] ?? null))
                                                    <div class="text-xs text-base-content/55">{{ $payment['unit'] }}</div>
                                                @endif
                                            </td>
                                            <td>{{ $payment['date'] }}</td>
                                            <td>{{ $payment['reference'] }}</td>
                                            <td class="text-right font-bold text-success">
                                                Gs {{ number_format($payment['amount'], 0, ',', '.') }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </article>
        </div>

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1.35fr)_minmax(0,1fr)]">
            <article class="card glass-card">
                <div class="card-body gap-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-base-content/45">Calendario académico</p>
                            <h3 class="text-xl font-semibold text-base-content">{{ $currentMonthLabel }}</h3>
                        </div>

                        <div class="flex flex-wrap gap-2 text-xs text-base-content/60">
                            <span class="badge badge-ghost badge-sm">Hoy</span>
                            <span class="badge badge-info badge-sm">Parciales</span>
                            <span class="badge badge-warning badge-sm">Finales</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-7 gap-2 text-center text-xs font-semibold uppercase tracking-[0.2em] text-base-content/40">
                        <span>Lun</span>
                        <span>Mar</span>
                        <span>Mié</span>
                        <span>Jue</span>
                        <span>Vie</span>
                        <span>Sáb</span>
                        <span>Dom</span>
                    </div>

                    <div class="grid grid-cols-7 gap-2">
                        @foreach($calendarDays as $day)
                            <div @class([
                                'min-h-20 rounded-[1.15rem] border p-2 text-left transition',
                                'border-base-300 bg-base-100/70 text-base-content/35' => ! $day['in_month'],
                                'border-base-300 bg-base-100/85 text-base-content' => $day['in_month'],
                                'border-primary/60 ring-1 ring-primary/25' => $day['is_today'],
                                'border-info/35 bg-info/8' => $day['highlight'] === 'active',
                                'border-warning/45 bg-warning/10' => $day['highlight'] === 'final',
                            ])>
                                <div class="flex items-start justify-between gap-2">
                                    <span class="text-sm font-semibold">{{ $day['label'] }}</span>
                                    @if($day['events_count'] > 0)
                                        <span class="badge badge-neutral badge-xs">{{ $day['events_count'] }}</span>
                                    @endif
                                </div>
                                <p class="mt-5 text-[0.65rem] font-semibold uppercase tracking-[0.18em] text-base-content/45">{{ $day['day_name'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </article>

            <article class="card glass-card">
                <div class="card-body gap-4">
                    <div class="space-y-2">
                        <p class="text-xs font-semibold uppercase tracking-[0.28em] text-base-content/45">Sistema inicial de alertas</p>
                        <h3 class="text-xl font-semibold text-base-content">Eventos monitoreados para parciales, finales y participación académica</h3>
                        <p class="text-sm text-base-content/65">Empezamos a consolidar evaluaciones y avisos activos para construir alertas por fecha y tipo de evento.</p>
                    </div>

                    <div class="grid gap-2 sm:grid-cols-3">
                        @foreach($eventSummary as $summary)
                            <div class="rounded-[1.25rem] bg-base-200/70 p-3">
                                <p class="text-xs uppercase tracking-[0.18em] text-base-content/45">{{ $summary['label'] }}</p>
                                <div class="mt-2 flex items-center justify-between gap-2">
                                    <span class="text-xl font-semibold text-base-content">{{ $summary['count'] }}</span>
                                    <span class="badge {{ $summary['tone'] }} badge-sm">{{ $summary['label'] }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if($eventAlerts === [])
                        <x-mary-alert title="Todavía no hay eventos con fecha visible para esta carrera o sede." icon="o-calendar-days" class="alert-info" />
                    @else
                        <div class="space-y-3">
                            @foreach($eventAlerts as $event)
                                <div class="rounded-[1.35rem] border border-base-300 bg-base-100/80 p-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="space-y-1">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="badge {{ $event['tone'] }} badge-sm">{{ $event['type_label'] }}</span>
                                                <span class="badge badge-ghost badge-sm">{{ $event['alert_state'] }}</span>
                                            </div>
                                            <p class="font-medium text-base-content">{{ $event['title'] }}</p>
                                            <p class="text-sm text-base-content/60">{{ $event['context'] }}</p>
                                        </div>

                                        <div class="text-right text-sm font-semibold text-base-content">
                                            {{ $event['date'] }}
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="rounded-[1.35rem] border border-dashed border-base-300 bg-base-100/70 p-4 text-sm text-base-content/60">
                        <span class="font-medium text-base-content">Base del sistema:</span>
                        eventos por evaluaciones de la habilitación vigente y avisos académicos activos de la sede.
                        @if($totalEvents > 0)
                            <span class="block mt-2">Eventos consolidados actualmente: {{ $totalEvents }}.</span>
                        @endif
                    </div>
                </div>
            </article>
        </div>
    @endif
</section>
