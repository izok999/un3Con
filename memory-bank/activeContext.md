# Active Context — CONSULTOR UNESYS

## Current Work Focus

As of **June 2026**, the project is in active development with the following completed and pending areas:

    ### Completed (✅)
- Schema: 7 tables + migrations for teacher evaluation module
- Admin configuration: periods, forms, criteria management
- Teacher & context management (admin + scoped ADMIN_UNIDAD_ACADEMICA)
- Context sync from legacy system (UI action + artisan command `evaluacion:sincronizar-contextos`)
- Student flow: index + form + submission for teacher evaluation
- Weighted score calculation (`PuntajeCalculator`)
- **Results/reports screen** — Admin view of teacher scores per period, per-criterion breakdown, evaluator counts, materia/carrera badges
- Materia & carrera name resolution from legacy DB (`catCarreras()`, `catMateriasPorIds()`) displayed in student index, form, and admin results
- Dashboard evaluation pending toast ("Tienes evaluaciones pendientes") with link to `/evaluacion-docente`
- Schema guard (friendly message if migrations missing)
- Form seeders (2 forms: student + funcionario)
- Tests: 25 PHPUnit tests covering admin config, teacher management, sync command, student evaluation flow, results display
- Student academic dashboard (carreras, materias, deudas, extracto académico)
- OAuth (Google Socialite) authentication
- Role system with Spatie (`ADMIN`, `ADMIN_UNIDAD_ACADEMICA`, `FUNCIONARIO`, `ALUMNO`)
- Legacy student sync service
- UNE visual theme (glass-morphism, dark mode, responsive)

### In Progress / Pending (❌)
1. **Funcionario evaluation flow** — Model, table, and seeder exist for `tipo=funcionario`, but no UI route/component
2. **Draft state** — `ESTADO_BORRADOR` constant defined but save-and-continue-later not implemented
3. **Model factories** — No `DocenteFactory`, `DocenteContextoFactory`, `EvaluacionDocenteFactory` for tests
4. **Period date validation** — `GuardarEvaluacionDocente` checks `activo === true` but not `fecha_inicio/fecha_fin` range

## Recent Changes

    ### June 16, 2026 — Admin Academic Unit Assignments UX + Traceability
- **AcademicUnitSeeder executed**: 6 faculties populated (Agronomía, Económicas, Filosofía, Politécnica, Derecho, Salud)
- **Test user added**: `admin.politecnica@une.edu.py` (Admin Politécnica, doc 0000003) assigned to Facultad Politécnica (sede 5) via `createPolytechnicAdmin()` in RoleSeeder
- **Sede visible in badges**: each badge now shows `Facultad (Sede: X)` instead of just the faculty name
- **Sede names from legacy DB**: dropdowns and sede text use `AlumnoExternoService::catSedes()` resolved via `$sedesMap` — shows "Unidad Académica — Nombre Sede" instead of raw numbers
- **Sede selector for multi-sede faculties**: when a faculty has multiple `legacy_sede_ids`, a `<select>` appears next to the checkbox to choose the specific sede
- **Filter by faculty**: new dropdown to filter admins by their assigned faculty (or "Sin facultades asignadas"), with "Limpiar filtros" button (via `clearFilters()` method)
- **Unsaved changes indicator**: cards get `border-warning` + badge "Cambios sin guardar" when checkboxes or sede selectors differ from persisted state (`hasUnsavedChanges()` method)
- **"Quitar todas" button**: removes all scopes from an admin with Alpine.js `confirm()` dialog (using `@js()` + JS concatenation for safe name escaping)
- **Traceability (`assigned_by`, `assigned_at`)**: migration adds FK to `users` + timestamp; `saveScopes()` records `auth()->id()` and `now()`; UI shows "Asignado por X el dd/mm/yyyy" below each badge
- **Model `UserAcademicUnitScope`**: added `assignedBy()` BelongsTo relation, casts for `assigned_by` (int) and `assigned_at` (datetime), extended `#[Fillable]`
- **Checkboxes refactored to MaryUI `<x-checkbox>`**: `selectedAcademicUnitsByUser` changed from array of strings to boolean map `[facultyId => true/false]` for compatibility with `<x-checkbox wire:model="...">`
- **Bug fixes**: `wire:model.live` → `wire:model` to prevent unnecessary roundtrips; removed `boot()` that was resetting collections; `$set is not defined` fixed with `clearFilters()` method; JS syntax error in "Quitar todas" fixed with `@js()` + string concatenation
- **Tests**: 11 tests (46 assertions) in `AdminAcademicUnitAdminsTest`; 30 total (130 assertions) across all related suites

    ### June 15, 2026 — Materia & Carrera Display in Evaluation Views
- Results table now shows columns for Carrera and Materia, resolved from `contexto_snapshot` via `AlumnoExternoService::catCarreras()` and `catMateriasPorIds()`
- Student evaluation index (`/evaluacion-docente`): each teacher card shows carrera badges and materia·turno badges from docente contextos
- Student evaluation form (`/evaluacion-docente/{docente}`): shows carrera name and materias from `$contextoSnapshot`

### June 15, 2026 — Dashboard Evaluation Pending Toast
- Added toast notification on student dashboard showing pending evaluation count
- Uses MaryUI's `window.toast()` with `alert-warning` styling (matching welcome toast pattern)
- Click navigates to `/evaluacion-docente` via inline `<a>` link in description (MaryUI renders `description` with `x-html`)
- **Performance optimization:** Uses lightweight `Docente::where('activo', true)->count()` instead of heavy `DocentesElegiblesResolver::paraAlumno()` to avoid external DB queries on every dashboard load
- Shows only once per session (`session('eval-pending-toast-shown')`)

### June 15, 2026 — Results/Reports Screen
- Created Volt SFC `admin.evaluacion-docente.resultados` with period selector, aggregation by teacher + form, per-criterion averages, weighted score calculation
- Added route under ADMIN + ADMIN_UNIDAD_ACADEMICA middleware group
- Added "Resultados" nav link in admin sidebar under "Administración"
- Created `AdminEvaluacionDocenteResultadosTest` (7 tests)
- **All 25 admin tests passing**

### June 15, 2026 — Teacher Management Performance Refactoring

**Problem:** The `/admin/evaluacion-docente/docentes` page was a monolithic 1490-line Volt SFC where every interaction (row expand, select change, context import) forced a full server roundtrip with DOM reload.

**Solution:** Split into two components with Alpine.js-driven UI:

| Action | Before | After |
|--------|--------|-------|
| Expand teacher row | Server roundtrip | Instant (Alpine.js toggle) |
| Change Sede select | Server roundtrip + cascade reset | Alpine clears downstream fields client-side |
| Import 5 external contexts | 6 HTTP requests | 1 batch call |

**Files changed:**
- `resources/views/livewire/admin/evaluacion-docente/docentes.blade.php` — Reduced to ~450 lines. Handles teacher CRUD, search, stats, teacher list with Alpine.js row expansion. Delegates context management to child component.
- `resources/views/livewire/admin/evaluacion-docente/docente-contextos.blade.php` — **New file** (~1075 lines). Isolated context management: manual form with Alpine.js cascade clearing, external assignment import (lazy), sync, deletion. Dispatches `contextos-updated` event upstream.
- `tests/Feature/Admin/AdminEvaluacionDocenteManagementTest.php` — Updated to test the child component directly with `docente-contextos` name + passed `selectedDocenteId` and `allowedSedeIds` params.

**Key design decisions:**
- External data (`contextosDocentePorDocumento()`) is lazy — only fetched when operator clicks "Importar todos"
- `resolveMiMaterias()` called early in `loadDocente()` to pre-fill materia names from existing local contextos
- Materia dropdown shows `<select disabled>` with placeholder text when no sede + carrera selected, instead of raw numeric input
- All 17 tests pass (9 management + 8 configuration/sync)

### Previous activity (May–June 2026)
- Teacher evaluation module (admin + student flows)
- Livewire/Volt components for admin panels
- Database seeders and migrations
- Testing infrastructure with PHPUnit

## Next Steps (Priority Order)

1. **Implement FUNCIONARIO evaluation flow** — Route, Volt component, tests
2. **Add model factories** — To expand test coverage
3. **Implement draft state** — Save-and-continue-later for evaluations
4. **Add period date validation** — Enforce `fecha_inicio/fecha_fin` in submission
5. **Scope ADMIN_UNIDAD_ACADEMICA** to not access config screens (currently guarded by middleware only)

## Active Decisions & Considerations

- **Component convention:** Using class-based SFC (`new class extends Component`) for Volt components, not `Livewire\Volt\Component`
- **External DB queries:** All centralized in `AlumnoExternoService`; never inline in controllers or components
- **Cache strategy:** Profile and career data cached for 30 min in database cache; academic details queried real-time
- **Theme persistence:** `localStorage` key `une-theme`; toggle in `resources/js/app.js`
- **MaryUI prefix convention:** Some views use `x-mary-*`, others use unprefixed components — check surrounding file before using
- **Middleware chain:** `auth` → `legacy.account.complete` → `verified` → `oauth.documento` for general routes; + `role:` for role-guarded routes
- **Testing strategy:** Mock `AlumnoExternoService` with `$this->mock()` to avoid external DB in tests
- **Form submission:** Always validates, blocks duplicates, checks type_evaluador matches form type
- **Results calculation:** `Σ(per-criterion average × peso) / Σ(peso)` — averages responses first per criterion across evaluators, then applies weights
- **MaryUI Toast compatibility:**
  - No soporta parámetro `link` — el `@click` nativo solo cierra el toast (`show = false`)
  - `description` se renderiza con `x-html`, por lo que se puede inyectar un `<a href="...">` inline
  - Para navegación desde toast, usar HTML en `description`: `Js::from("texto <a href='$link'>click</a>")`
  - El toast vive dentro de `@persist('mary-toaster')` en el layout, fuera del scope del componente
  - Evitar `Livewire.navigate()` en event listeners del toast — usar `window.location.href` o `<a>` nativo
- **Dashboard performance:** El conteo de evaluaciones pendientes en el dashboard usa `Docente::count()` simple en vez de `DocentesElegiblesResolver::paraAlumno()` para no consultar la BD externa en cada carga

## Important Patterns & Preferences

- **Controllers should be thin** → business logic in Services
- **No JOINs between pgsql and pgsql_externa** — separate connections, separate queries
- **Type hints + return types** on all method signatures (no `declare(strict_types=1)`)
- **Volt SFC directory:** `resources/views/livewire/{alumno,admin}`
- **Layouts:** `app.blade.php` (authenticated) and `guest.blade.php` (unauthenticated)
- **Run Pint after PHP changes:** `vendor/bin/sail bin pint --dirty --format agent`
- **Run minimal tests after changes:** `vendor/bin/sail artisan test --compact --filter=TestName`

## Learnings & Project Insights

1. **The legacy external DB is fragile.** Views like `fn_busca_alumnos_habilitacion_extracto()` and `fn_consultor_alumnos_deudas()` fail due to broken dependencies on legacy views. Stick to the verified working views: `vw_alumnos_00`, `vw_alumnos_habilitacion_21`, `vw_extracto_academico_01`, `vw_alumnos_inscriptos_materias_14`, `vw_alumnos_deudas_saldos_12`.

2. **`resolverAlumno()` returns `?stdClass`**, not an array. Access with `->` notation. Do not cast before passing to components.

3. **The `search_path` for `pgsql_externa`** must include `sh_maestros`, `sh_academico`, `sh_movimientos`, `sh_rrhh`, and `public`.

4. **Context matching uses `NULL` as wildcard** — A `DocenteContexto` field with `NULL` matches any value from the student's context. This allows flexible teacher-to-student scoping.

5. **`x-modal` conflicts with Breeze's anonymous modal.** Use `x-mary-modal` or a local wrapper.

6. **MaryUI prefix varies by context** — Some files use `x-mary-table`, `x-mary-stat`, `x-mary-badge`; others use unprefixed. Check sibling components.

7. **Schema guard pattern** — Components check for table existence before rendering; show friendly "run migrations" message if schema missing.

8. **`FormularioEvaluacion` uses `nombre` field** — not `titulo`. Eager loads must select `nombre`, not `titulo`.