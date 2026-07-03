<?php

use App\Models\Docente;
use App\Models\DocenteContexto;
use App\Models\EvaluacionDocente;
use App\Models\FormularioEvaluacion;
use App\Models\PeriodoEvaluacion;
use App\Services\AlumnoExternoService;
use App\Services\EvaluacionDocente\DocentesElegiblesResolver;
use App\Services\EvaluacionDocente\GuardarEvaluacionDocente;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public Docente $docente;

    public DocenteContexto $contexto;

    public ?PeriodoEvaluacion $periodo = null;

    public ?FormularioEvaluacion $formulario = null;

    public Collection $criterios;

    public array $respuestas = [];

    public array $contextoSnapshot = [];

    public string $error = '';

    public string $nombreCarrera = '';

    public string $materiaNombre = '';

    public string $materiaTurno = '';

    public bool $ready = false;

    public function boot(): void
    {
        $this->criterios = collect();
    }

    public function mount(Docente $docente, DocenteContexto $contexto, DocentesElegiblesResolver $resolver): void
    {
        $user = Auth::user();

        abort_unless($user, 403);
        abort_if($docente->id !== $contexto->docente_id, 403);
        abort_unless($docente->activo, 403);
        abort_unless($contexto->activo, 403);

        $this->docente = $docente;
        $this->contexto = $contexto;
        $this->periodo = PeriodoEvaluacion::query()
            ->where('activo', true)
            ->orderByDesc('fecha_inicio')
            ->first();

        $this->formulario = FormularioEvaluacion::query()
            ->where('tipo_evaluador', FormularioEvaluacion::TIPO_ALUMNO)
            ->where('activo', true)
            ->first();

        if (! $this->periodo) {
            $this->error = 'No existe un periodo de evaluación activo.';

            return;
        }

        if (! $this->formulario) {
            $this->error = 'No existe un formulario activo para alumnos.';

            return;
        }

        // El periodo debe estar activo Y dentro del rango de fechas. Se valida
        // acá para mostrar el aviso al abrir el formulario, en vez de dejar que
        // el alumno complete todo y recién falle silenciosamente al guardar.
        if ($this->periodo->fecha_inicio && now()->startOfDay()->lt($this->periodo->fecha_inicio->startOfDay())) {
            $this->error = 'El periodo de evaluación aún no ha comenzado.';

            return;
        }

        if ($this->periodo->fecha_fin && now()->gt($this->periodo->fecha_fin->endOfDay())) {
            $this->error = 'El periodo de evaluación ya ha finalizado.';

            return;
        }

        // El alumno solo puede evaluar contextos que correspondan a sus
        // propias inscripciones (evita evaluar por manipulación de URL).
        abort_unless($resolver->esElegibleParaAlumno($user, $contexto, $this->periodo), 403);

        if (EvaluacionDocente::query()
            ->where('periodo_evaluacion_id', $this->periodo->id)
            ->where('formulario_evaluacion_id', $this->formulario->id)
            ->where('docente_contexto_id', $contexto->id)
            ->where('docente_id', $docente->id)
            ->where('evaluador_user_id', $user->id)
            ->exists()) {
            session()->flash('status', 'Ya registraste una evaluación para este docente en esta materia en el periodo activo.');
            $this->redirectRoute('alumno.evaluacion-docente', navigate: true);

            return;
        }

        // Snapshot base (sin llamadas externas). Los nombres de catálogo y los
        // criterios se resuelven en cargarDatos() para mostrar el skeleton.
        $this->contextoSnapshot = array_filter([
            'car_id' => $contexto->car_id,
            'sed_id' => $contexto->sed_id,
            'ple_id' => $contexto->ple_id,
            'mi2_id' => $contexto->mi2_id,
            'tur_id' => $contexto->tur_id,
            'sec_id' => $contexto->sec_id,
            'materias' => $contexto->mi2_id !== null
                ? [['mi2_id' => $contexto->mi2_id, 'materia' => '', 'tur_id' => $contexto->tur_id ?? '', 'turno' => '']]
                : [],
        ], fn (mixed $value): bool => $value !== null && $value !== []);
    }

    /**
     * Resuelve los nombres de catálogo (externos, potencialmente lentos) y los
     * criterios del formulario. Se dispara con wire:init tras el primer render,
     * de modo que el alumno ve el skeleton mientras se cargan.
     */
    public function cargarDatos(): void
    {
        if ($this->error !== '' || ! $this->formulario) {
            return;
        }

        $contexto = $this->contexto;

        if ($contexto->car_id !== null) {
            try {
                $carreras = app(AlumnoExternoService::class)->catCarreras();
                $this->nombreCarrera = $carreras[(int) $contexto->car_id] ?? "Carrera #{$contexto->car_id}";
            } catch (Throwable) {
                $this->nombreCarrera = "Carrera #{$contexto->car_id}";
            }
        }

        if ($contexto->mi2_id !== null) {
            try {
                $materiasMap = app(AlumnoExternoService::class)->catMateriasPorIds([$contexto->mi2_id]);
                $this->materiaNombre = $materiasMap[(int) $contexto->mi2_id] ?? "Materia #{$contexto->mi2_id}";
            } catch (Throwable) {
                $this->materiaNombre = "Materia #{$contexto->mi2_id}";
            }
        }

        if ($contexto->tur_id !== null) {
            try {
                $turnos = app(AlumnoExternoService::class)->catTurnos();
                $this->materiaTurno = $turnos[(int) $contexto->tur_id] ?? '';
            } catch (Throwable) {
                $this->materiaTurno = '';
            }
        }

        $this->contextoSnapshot['materias'] = [
            [
                'mi2_id' => $contexto->mi2_id,
                'materia' => $this->materiaNombre,
                'tur_id' => $contexto->tur_id ?? '',
                'turno' => $this->materiaTurno,
            ],
        ];

        $this->criterios = $this->formulario->criterios()
            ->where('activo', true)
            ->orderBy('orden')
            ->get();

        foreach ($this->criterios as $criterio) {
            $this->respuestas[$criterio->id] = [
                'valor_numerico' => '',
                'valor_texto' => '',
                'observacion' => '',
            ];
        }

        $this->ready = true;
    }

    public function submit(GuardarEvaluacionDocente $service): void
    {
        $user = Auth::user();

        abort_unless($user, 403);

        if (! $this->periodo || ! $this->formulario) {
            return;
        }

        $payload = collect($this->respuestas)
            ->map(fn (array $respuesta, int|string $criterioId): array => [
                'formulario_criterio_id' => (int) $criterioId,
                'valor_numerico' => $respuesta['valor_numerico'] ?? null,
                'valor_texto' => $respuesta['valor_texto'] ?? null,
                'observacion' => $respuesta['observacion'] ?? null,
            ])
            ->values()
            ->all();

        try {
            $service->guardar(
                $this->periodo,
                $this->formulario,
                $this->docente,
                $user,
                FormularioEvaluacion::TIPO_ALUMNO,
                $payload,
                $this->contextoSnapshot,
                $this->contexto,
            );
        } catch (ValidationException $exception) {
            throw $exception;
        }

        session()->flash('status', 'Tu evaluación fue enviada correctamente.');
        $this->redirectRoute('alumno.evaluacion-docente', navigate: true);
    }
}; ?>

<div
    class="space-y-6"
    @if ($error === '')
        wire:init="cargarDatos"
    @endif
>
    <div>
        <a href="{{ route('alumno.evaluacion-docente') }}" class="inline-flex items-center gap-1.5 text-sm text-base-content/55 transition hover:text-primary" wire:navigate>
            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
            </svg>
            Volver a evaluación docente
        </a>
    </div>

    <x-mary-header title="Formulario de evaluación" subtitle="{{ $docente->nombre }}" separator />

    @if ($error !== '')
        <x-mary-alert title="{{ $error }}" icon="o-exclamation-triangle" class="alert-warning" />
    @else
        @php
            $errorGeneral = collect(['periodo', 'formulario', 'tipo_evaluador', 'evaluacion', 'respuestas'])
                ->map(fn (string $clave) => $errors->first($clave))
                ->first(fn (?string $mensaje) => filled($mensaje));
        @endphp

        @if ($errorGeneral)
            <x-mary-alert title="{{ $errorGeneral }}" icon="o-exclamation-triangle" class="alert-error" />
        @endif

        @if (! $ready)
            <div wire:key="form-skeleton">
                <section class="glass-card card">
                    <div class="card-body gap-4">
                        <div class="space-y-2">
                            <div class="h-3 w-32 animate-pulse rounded-full bg-base-300/60"></div>
                            <div class="h-6 w-64 animate-pulse rounded-lg bg-base-300/70"></div>
                            <div class="h-4 w-44 animate-pulse rounded-full bg-base-300/50"></div>
                            <div class="mt-2 h-16 w-full animate-pulse rounded-2xl bg-base-300/40"></div>
                        </div>
                        <div class="h-16 w-full animate-pulse rounded-2xl bg-base-300/30"></div>
                    </div>
                </section>

                <div class="mt-4 space-y-4">
                    @for ($i = 0; $i < 4; $i++)
                        <section class="glass-card card">
                            <div class="card-body gap-4">
                                <div class="space-y-2">
                                    <div class="h-3 w-24 animate-pulse rounded-full bg-base-300/60"></div>
                                    <div class="h-5 w-3/4 animate-pulse rounded-lg bg-base-300/70"></div>
                                </div>
                                <div class="h-11 w-full max-w-xs animate-pulse rounded-lg bg-base-300/50"></div>
                            </div>
                        </section>
                    @endfor

                    <div class="flex justify-end">
                        <div class="h-11 w-48 animate-pulse rounded-lg bg-base-300/60"></div>
                    </div>
                </div>
            </div>
        @else
        <section class="glass-card card">
            <div class="card-body gap-4">
                <div class="space-y-1">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Docente seleccionado</p>
                    <h2 class="card-title text-xl text-primary">{{ $docente->nombre }}</h2>
                    <p class="text-sm text-base-content/70">Periodo activo: {{ $periodo?->nombre }}</p>
                    @if ($nombreCarrera !== '')
                        <p class="text-sm text-base-content/70">{{ $nombreCarrera }}</p>
                    @endif
                    @if ($materiaNombre !== '')
                        <div class="rounded-2xl border border-base-300 bg-base-200/40 p-3 mt-2">
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/50">Materia a evaluar</p>
                            <p class="font-medium text-base-content mt-1">
                                {{ $materiaNombre }}
                                @if ($materiaTurno !== '')
                                    <span class="text-base-content/60">· {{ $materiaTurno }}</span>
                                @endif
                            </p>
                        </div>
                    @endif
                </div>

                <div class="rounded-2xl border border-base-300 bg-base-200/40 p-4 text-sm text-base-content/80">
                    Utilizá la escala de {{ $formulario?->escala_min }} a {{ $formulario?->escala_max }} para los criterios numéricos. Las observaciones generales no afectan el puntaje total.
                </div>
            </div>
        </section>

        <form wire:submit="submit" class="space-y-4">
            @foreach ($criterios as $criterio)
                <section class="glass-card card">
                    <div class="card-body gap-4">
                        <div class="space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-base-content/45">Criterio {{ $criterio->orden }}</p>
                                @if ($criterio->obligatoria)
                                    <span class="badge badge-outline badge-sm">Obligatorio</span>
                                @endif
                            </div>
                            <h3 class="text-base font-semibold text-base-content">{{ $criterio->pregunta }}</h3>
                        </div>

                        @if ($criterio->tipo_respuesta === 'texto')
                            <label class="form-control w-full">
                                <span class="label-text text-sm font-medium">Respuesta</span>
                                <textarea
                                    wire:model="respuestas.{{ $criterio->id }}.valor_texto"
                                    rows="4"
                                    class="textarea textarea-bordered w-full"
                                    placeholder="Escribí tus observaciones"
                                ></textarea>
                            </label>
                        @else
                            <label class="form-control w-full md:max-w-xs">
                                <span class="label-text text-sm font-medium">Puntaje</span>
                                <select wire:model="respuestas.{{ $criterio->id }}.valor_numerico" class="select select-bordered w-full">
                                    <option value="">Seleccionar</option>
                                    @for ($valor = $formulario->escala_min; $valor <= $formulario->escala_max; $valor++)
                                        <option value="{{ $valor }}">{{ $valor }}</option>
                                    @endfor
                                </select>
                            </label>
                        @endif

                        @error('respuestas.'.$criterio->id)
                            <p class="text-sm font-medium text-error">{{ $message }}</p>
                        @enderror
                    </div>
                </section>
            @endforeach

            <div class="flex justify-end">
                <button type="submit" class="btn btn-primary min-w-48">
                    <span wire:loading.remove wire:target="submit">Enviar evaluación</span>
                    <span wire:loading wire:target="submit" class="loading loading-spinner loading-sm"></span>
                </button>
            </div>
        </form>
        @endif
    @endif
</div>