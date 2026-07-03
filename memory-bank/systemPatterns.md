# System Patterns — CONSULTOR UNESYS

## System Architecture

The application follows a **dual-database MVC architecture** within Laravel's conventions, with Livewire/Volt providing reactive frontend behavior without a separate JavaScript SPA.

### High-Level Architecture

```
┌────────────────────────────────────────────────────────────────┐
│                        BROWSER                                 │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────────────┐ │
│  │ Blade Views  │  │ Livewire 3   │  │ TailwindCSS 4        │ │
│  │ (Volt SFC)   │  │ (Reactivity) │  │ + DaisyUI 5          │ │
│  └──────┬───────┘  └──────┬───────┘  │ + MaryUI 2.8         │ │
│         │                 │           │ + Alpine.js           │ │
│         └────────┬────────┘           └──────────────────────┘ │
└──────────────────┼─────────────────────────────────────────────┘
                   │ HTTP / WebSocket
┌──────────────────┼─────────────────────────────────────────────┐
│           LARAVEL 13 APPLICATION (PHP 8.3+)                    │
│                                                                 │
│  ┌────────────┐  ┌──────────────────┐  ┌───────────────────┐  │
│  │  Routes    │  │  Middleware Stack │  │  Controllers      │  │
│  │  (web.php) │──│  auth, role,      │──│  (thin) + Volt    │  │
│  │            │  │  legacy.account   │  │  Components       │  │
│  └────────────┘  └──────────────────┘  └────────┬──────────┘  │
│                                                  │              │
│  ┌───────────────────────────────────────────────┼───────────┐ │
│  │              SERVICES LAYER                   ▼           │ │
│  │  ┌─────────────────────┐  ┌────────────────────────────┐ │ │
│  │  │ AlumnoExternoService│  │ DocentesElegiblesResolver  │ │ │
│  │  │ (external DB read)  │  │ (context matching logic)   │ │ │
│  │  └─────────┬───────────┘  └────────────────────────────┘ │ │
│  │  ┌─────────┴───────────┐  ┌────────────────────────────┐ │ │
│  │  │ GuardarEvaluacion   │  │ PuntajeCalculator          │ │ │
│  │  │ Docente (validation │  │ (weighted scoring)         │ │ │
│  │  │ + persistence)      │  │                            │ │ │
│  │  └─────────────────────┘  └────────────────────────────┘ │ │
│  │  ┌─────────────────────────────────────────────────────┐ │ │
│  │  │ LegacyAlumnoUserSyncService (batch user sync)       │ │ │
│  │  └─────────────────────────────────────────────────────┘ │ │
│  └──────────────────────────────────────────────────────────┘ │
│                                                  │              │
│  ┌───────────────────────────────────────────────┼───────────┐ │
│  │              MODELS / DATA ACCESS              ▼           │ │
│  │  ┌──────────────────┐    ┌──────────────────────────────┐ │ │
│  │  │ Eloquent Models   │    │ Raw Query Builder            │ │ │
│  │  │ (pgsql local)     │    │ DB::connection('pgsql_       │ │ │
│  │  │ User, Role,       │    │ externa')                    │ │ │
│  │  │ Permission,       │    │ ->table('sh_maestros.vw_*')  │ │ │
│  │  │ Docente,          │    └──────────────────────────────┘ │ │
│  │  │ Evaluacion, etc.  │                                     │ │
│  │  └──────────────────┘                                     │ │
│  └──────────────────────────────────────────────────────────┘ │
└────────────────────────────────────────────────────────────────┘
                   │                    │
          ┌────────┴────────┐  ┌───────┴──────────┐
          │ PostgreSQL      │  │ PostgreSQL        │
          │ (pgsql) LOCAL   │  │ (pgsql_externa)   │
          │ users, roles,   │  │ une_base          │
          │ evaluaciones,   │  │ READ-ONLY         │
          │ cache, sessions │  │ sh_maestros,      │
          │                 │  │ sh_academico,     │
          │                 │  │ sh_movimientos    │
          └─────────────────┘  └──────────────────┘
```

## Key Technical Decisions

### 1. Two Databases, Two Access Patterns
- **Local DB (`pgsql`):** Uses Eloquent ORM with proper models, migrations, and relationships
- **External DB (`pgsql_externa`):** Uses Query Builder via `DB::connection('pgsql_externa')` — no Eloquent models for performance and read-only constraint clarity

### 2. Service Layer as DB Access Facade
All external database queries flow through `AlumnoExternoService`. No controller or component executes `DB::connection('pgsql_externa')` directly. This centralizes:
- Connection management
- Cache strategies (database cache, 30-min TTL)
- Query parameterization
- Error handling for legacy system failures

### 3. Livewire/Volt with Class-Based SFC
Components use `new class extends Component` within Blade files under `resources/views/livewire/`. This is the established pattern (not `Livewire\Volt\Component`).

### 4. Form Parameterization Over Hardcoding
The teacher evaluation module uses a header-detail model where forms and criteria are database records, not hardcoded arrays. This allows:
- Adding new forms without code changes
- Different criteria per evaluator type (alumno vs funcionario)
- Flexible weighting configuration

### 5. Context Matching with NULL-as-Wildcard
`DocentesElegiblesResolver` matches teachers to students by comparing `DocenteContexto` records against the student's academic context. `NULL` fields in the teacher's context act as wildcards, allowing broad or specific matching.

### 6. Role-Based Access with Spatie + Enum
Four roles defined as an `App\Enums\RoleName` enum, enforced via Spatie's `role:` middleware. The `RoleName::middleware()` helper concatenates multiple roles for compound middleware strings.

### 7. UNE Visual System
A cohesive CSS design system in `resources/css/app.css` using:
- `uneTheme` and `uneThemeDark` DaisyUI themes
- Glass-morphism utility classes (`glass-card`, `glass-surface`, `glass-sidebar`, `glass-navbar`)
- `bg-app-pattern` background texture
- Theme persisted in `localStorage` under key `une-theme`

## Design Patterns In Use

| Pattern | Usage | Location |
|---------|-------|----------|
| **Service Layer** | Business logic isolation from controllers | `app/Services/` |
| **Repository (implicit)** | Data access via `AlumnoExternoService` | `app/Services/AlumnoExternoService.php` |
| **Strategy** | Weighted vs simple scoring, type-based evaluator forms | `PuntajeCalculator`, form types |
| **Header-Detail** | Evaluation (cabecera) + Responses (detalle) | `EvaluacionDocente` model + `EvaluacionRespuesta` |
| **Middleware Pipeline** | Auth, role, legacy account, OAuth document checks | `routes/web.php`, `app/Http/Middleware/` |
| **Enum + Guard** | Role constants used by Spatie middleware | `app/Enums/RoleName.php` |
| **NULL-as-Wildcard** | Context matching for teacher eligibility | `DocentesElegiblesResolver` |
| **Schema Guard** | Graceful handling of missing migrations | Component `mount()` checks `Schema::hasTable()` |
| **Singleton Service** | `AlumnoExternoService` injected via DI, never `new`'d | All Livewire/Volt components |

## Component Relationships

### Authentication & Identity
```
User ──belongs to many──▶ Role (Spatie)
User ──has──▶ documento (bridge field)
documento ──resolved via──▶ AlumnoExternoService::resolverAlumno()
                            → vw_alumnos_00.alu_perdoc → alu_id
```

### Teacher Evaluation Module
```
PeriodoEvaluacion ──has many──▶ EvaluacionDocente
PeriodoEvaluacion ──has many──▶ DocenteContexto (via periodo_evaluacion_id)
FormularioEvaluacion ──has many──▶ FormularioCriterio
FormularioEvaluacion ──has many──▶ EvaluacionDocente
Docente ──has many──▶ DocenteContexto
Docente ──has many──▶ EvaluacionDocente
DocenteContexto ──has many──▶ EvaluacionDocente (via docente_contexto_id — one evaluation per evaluator × docente × periodo × formulario × contexto)
EvaluacionDocente ──has many──▶ EvaluacionRespuesta
EvaluacionRespuesta ──belongs to──▶ FormularioCriterio
EvaluacionDocente ──belongs to──▶ User (as evaluador_user_id)
Docente ──uses SoftDeletes──▶ deleted_at (historical evaluaciones preserved after deletion)
```

### Service Dependencies
All evaluation-module services live under `App\Services\EvaluacionDocente\`.
```
AlumnoExternoService (App\Services\)
  ├── Used by: alumno Volt components, DocentesElegiblesResolver
  ├── Uses: DB::connection('pgsql_externa'), Cache
  └── Cached methods: resolverAlumno(), carreras(), catCarreras(), catMateriasPorIds(), catSedes()

GuardarEvaluacionDocente
  ├── Used by: evaluacion-docente.form Volt component
  ├── Uses: EvaluacionDocente model, EvaluacionRespuesta model, PuntajeCalculator
  └── Validates: type match, no duplicates (scoped by docente_contexto_id), required criteria, periodo activo AND fecha_inicio/fecha_fin range

PuntajeCalculator
  ├── Used by: GuardarEvaluacionDocente
  └── Formula: Σ(valor_i × peso_i) / Σ(peso_i)

DocentesElegiblesResolver
  ├── Used by: evaluacion-docente.index Volt component
  ├── Uses: AlumnoExternoService, Docente model, DocenteContexto
  ├── Returns: ['docente' => Docente, 'contexto' => DocenteContexto][] pairs — a docente appears once per matching subject/context
  └── Logic: NULL-as-wildcard context matching (except mi2_id/materia, which requires strict match)
```

## Critical Implementation Paths

### Path 1: Student Evaluates Teacher
```
1. GET /evaluacion-docente
   → evaluacion-docente.index (Volt), lazy-loaded via wire:init
   → DocentesElegiblesResolver::paraAlumno($user) → Collection of ['docente' => Docente, 'contexto' => DocenteContexto] pairs
   → Renders one card per docente×contexto (subject), with "Ya evaluado" tags

2. GET /evaluacion-docente/{docente}/{contexto}
   → evaluacion-docente.form (Volt)
   → Validates: period activo AND within fecha_inicio/fecha_fin, form active, context match, no prior evaluation for this docente_contexto_id
   → Renders form with FormularioCriterio items

3. POST (form submission) via Livewire action
   → GuardarEvaluacionDocente::guardar($periodo, $formulario, $docente, $evaluador, $tipo, $respuestas, $contextoSnapshot, $docenteContexto)
   → Validates: type match, required criteria filled, no duplicate (scoped to docente_contexto_id)
   → PuntajeCalculator::calcular($insumosCalculo)
   → Persists EvaluacionDocente (with docente_contexto_id) + EvaluacionRespuesta records
   → Flash message + redirect to index
```

### Path 2: Admin Syncs Teacher Contexts
```
1. Admin navigates to /admin/evaluacion-docente/docentes
   → docentes.blade.php (parent Volt) + docente-contextos.blade.php (child Volt sub-component)
   → Parent: teacher CRUD, search, stats cards, teacher list
   → Child: context management panel (manual form, external import, sync, deletion)

2. Clicks a teacher row arrow
   → Alpine.js toggles expandedDocenteId client-side (no server roundtrip)
   → Parent sets selectedDocenteId → child component mounts lazily
   → loadDocente() pre-fills materia names from existing contextos via resolveMiMaterias()

3. Clicks "Importar todos" in the context panel
   → Child component calls AlumnoExternoService::contextosDocentePorDocumento($doc)
   → External data fetched once, stored in $contextosExternos, persisted via firstOrCreate
   → Dispatches 'contextos-updated' event → parent refreshes teacher stats

4. Cascade selects (Sede → Carrera → Materia → Período → Turno → Sección)
   → Alpine.js x-on:change handlers clear downstream fields client-side
   → wire:model.live fetches filtered catalog options from external DB on each parent change
   → Single server roundtrip only when clicking "Agregar contexto"

5. Alternatively: artisan evaluacion:sincronizar-contextos [--periodo=YYYY]
   → Batch processes all active teachers with documento
```

### Path 3: Component Architecture for Teacher Management

```
docentes.blade.php (parent, ~820 lines — grown from the original ~450 as faculty/sede filtering and admin-scope UI were added)
  ├── Teacher CRUD (create, edit, save)
  ├── Stats cards (teacher count, active count, context count)
  ├── Search bar with debounce
  ├── Teacher list with Alpine.js row expansion
  └── <livewire:docente-contextos> child

docente-contextos.blade.php (child, ~1110 lines)
  ├── Manual context form (6 cascade selects)
  ├── External assignment table (lazy-loaded via "Importar todos")
  ├── Batch import (checkbox selection + single server call)
  ├── Synced contexts list (read-only table with delete)
  └── Events: 'contextos-updated' → parent refreshes teacher list
```

### Key Refactoring Decisions (June 2026)

| Decision | Rationale |
|----------|-----------|
| **Split into parent + child components** | Isolates context panel from teacher list — context operations re-render only the child, not the entire page |
| **Alpine.js row expansion** | Clicking a teacher row arrow now toggles `expandedDocenteId` client-side — no server roundtrip for UI toggle |
| **Alpine.js cascade clearing** | When operator changes "Sede", downstream fields clear instantly via `x-on:change` — no server roundtrip per field |
| **Lazy external data** | `contextosDocentePorDocumento()` only called when operator clicks "Importar todos" — eliminates eager external DB query on page load |
| **`resolveMiMaterias()` in `loadDocente()`** | Pre-fills materia names from existing local contextos before the form renders — avoids raw numeric input for mi2_id |

## Middleware Stack
```
Global (all web routes, registered in bootstrap/app.php): SetLocale
Route-scoped: auth, legacy.account.complete, verified, oauth.documento
  ├── Role: ALUMNO → student routes
  ├── Role: ADMIN|ADMIN_UNIDAD_ACADEMICA + academic.unit.scope
  │   → admin panel (scoped: consulta-alumno, docentes, resultados)
  └── Role: ADMIN only → global admin (configuración, administradores-unidades)