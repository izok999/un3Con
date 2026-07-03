# Progress — CONSULTOR UNESYS

## Current Status: Active Development (last commit June 25, 2026 — `7ee071d`)

The application is functional with the core student portal, admin panel, and teacher evaluation module operational. Several features are in various stages of completion.

---

## What Works

### Authentication & Authorization
- [x] Google OAuth login via Laravel Socialite
- [x] Role system: ADMIN, ADMIN_UNIDAD_ACADEMICA, FUNCIONARIO, ALUMNO
- [x] Spatie Laravel-Permission integration with middleware guards
- [x] Legacy account completion middleware
- [x] OAuth documento check middleware
- [x] Role-appropriate dashboard routing

### Student Portal (ALUMNO role)
- [x] Mis Carreras — Active career enrollments with detail view
- [x] Mis Materias — Enrolled subjects for current period
- [x] Mis Deudas — Outstanding debts and payment status
- [x] Profile page with theme support
- [x] Multi-language support (es, en, pt, gn)

### Teacher Evaluation Module
| Feature | Status | Notes |
|---------|--------|-------|
| Schema (7 tables) | ✅ | Complete migrations |
| Admin config (periods) | ✅ | Create/edit, activate (deactivates others) |
| Admin config (forms) | ✅ | By evaluator type, activate one per type |
| Admin config (criteria) | ✅ | Weight, order, type, required flag |
| Teacher management | ✅ | CRUD, context management (parent + child components, fully clickable row) |
| Context sync (UI) | ✅ | Per-teacher "Importar todos" in context panel (lazy external data) |
| Context sync (CLI) | ✅ | `evaluacion:sincronizar-contextos [--periodo=YYYY]` |
| Student index view | ✅ | Lazy loading via `wire:init` + `<x-loading class="loading-dots" />`, materia/carrera badges, history, "Ya evaluado" tags |
| Student form view | ✅ | Scale + text criteria, validation, materia/carrera display from contexto_snapshot |
| Evaluation submission | ✅ | GuardarEvaluacionDocente service |
| Weighted score calc | ✅ | PuntajeCalculator: Σ(valor × peso) / Σ(peso) |
| Anti-duplicate guard | ✅ | Unique per evaluator × teacher × period × form |
| Schema guard | ✅ | Friendly message if migrations missing |
| Form seeders | ✅ | 2 forms (alumno + funcionario) with criteria |
| Results/reports | ✅ | Per-period teacher scores, per-criterion breakdown, evaluator counts, materia/carrera display, Chart.js visualizations |
| Evaluation-per-context | ✅ | `docente_contexto_id` FK links each evaluation to a specific subject/context; docente can be evaluated once per subject |
| Docente soft delete | ✅ | `SoftDeletes` on `Docente`; historical evaluations preserved |
| Period date validation | ✅ | `GuardarEvaluacionDocente::ensurePeriodoActivo()` checks `fecha_inicio <= now <= fecha_fin` in addition to `activo` |
| **Funcionario flow** | ❌ | Model exists, no UI/route/component |
| **Draft state** | ❌ | ESTADO_BORRADOR defined but unused |

### Admin Panel
- [x] Consulta Alumno — Look up any student's academic data
- [x] Gestión de Docentes — CRUD + context management (ADMIN + ADMIN_UNIDAD_ACADEMICA)
- [x] Configuración de Evaluación — Periods, forms, criteria (ADMIN only)
- [x] Resultados de Evaluación — Per-period teacher scores with per-criterion breakdown (ADMIN + ADMIN_UNIDAD_ACADEMICA)
- [x] Administradores de Unidades — Manage academic unit admins (ADMIN only) with MaryUI `<x-checkbox>`, faculty-sede assignment with names from legacy DB (`catSedes()`), filter by faculty, "Limpiar filtros", unsaved changes indicator, "Quitar todas", traceability (assigned_by/assigned_at)
- [x] Scope enforcement for ADMIN_UNIDAD_ACADEMICA

### UI/UX
- [x] UNE visual theme (uneTheme / uneThemeDark)
- [x] Glass-morphism utilities (glass-card, glass-surface, glass-sidebar, glass-navbar)
- [x] Responsive layout (sidebar desktop + bottom nav mobile)
- [x] Theme toggle with hybrid persistence — cookie (`une-theme`, 1yr) + `localStorage` fallback, set in `resources/js/app.js`
- [x] Skeletal loading patterns
- [x] bg-app-pattern background texture
- [x] i18n — locale switcher (`es`, `en`, `pt`, `gn`), persisted to `users.locale` + session via `SetLocale` middleware
- [x] Normativas page (`/normativas`) — institutional/legal documents from `config/normativas.php`

### Testing
| Test File | Coverage | Status |
|-----------|----------|--------|
| AdminEvaluacionDocenteConfigurationTest | Config page, create period, period deactivation, create form, add criteria | ✅ |
| AdminEvaluacionDocenteManagementTest | Teacher page, create teacher, add context, scope, sync action, idempotency | ✅ |
| SincronizarContextosDocentesCommandTest | Batch sync, skip inactive, idempotency, --periodo option | ✅ |
| AlumnoEvaluacionDocenteFlowTest | Weighted score, duplicate block, required criteria, type guard, index, form, schema guard | ✅ |
| AdminEvaluacionDocenteResultadosTest | Admin access, non-admin 403, period selector, empty states, score display, materias/carreras, draft exclusion | ✅ |
| AdminAcademicUnitAdminsTest | Admin access, assign faculties, custom sede, badges with sede, clear scopes, filter by faculty, unsaved indicator, 403 for unit admin, traceability (assigned_by/assigned_at + UI) | ✅ |
| AdminConsultaAlumnoScopeTest | Unit admin scope enforcement, student inside/outside scope | ✅ |
| RoleAccessTest | Role-based route access, menu visibility per role | ✅ |
| LocaleSwitcherTest | Guarani + other locale switching | ✅ |
| PuntajeCalculatorTest (Unit) | Weighted score formula | ✅ |
| Auth suite (Authentication, EmailVerification, PasswordConfirmation/Reset/Update, Registration, OAuth) | Standard Breeze + OAuth flows | ✅ |
| SyncLegacyAlumnoUsersCommandTest | Legacy user batch sync command | ✅ |

**~128 test methods across Feature/Unit suites** (exact pass count not re-verified in this pass — Sail was not running; last known-good run was 30 tests/130 assertions for the evaluation+admin subset before the June 19–25 context-linking and i18n additions grew the suite)

### Infrastructure
- [x] Docker/Sail development environment
- [x] PostgreSQL 18 (local) + external legacy DB connection
- [x] Redis for cache and sessions
- [x] Mailpit for email testing
- [x] pgAdmin for DB management
- [x] Vite 7 with TailwindCSS 4 and HMR
- [x] Laravel Boost MCP tools

---

## What's Left to Build

### High Priority
1. **Funcionario Evaluation Flow**
   - Route: `GET /evaluacion-docente/funcionario/{docente}`
   - Volt component for funcionario form (analogous to student form)
   - Middleware to allow FUNCIONARIO role to access
   - Tests for the new flow
   - **Estimated effort:** 1 route + 1 component + tests

### Medium Priority
2. **Model Factories**
   - DocenteFactory, DocenteContextoFactory, EvaluacionDocenteFactory, etc.
   - Expand test coverage using factories instead of inline model creation
   - **Estimated effort:** 4-5 factory classes

3. **Draft State for Evaluations**
   - "Guardar borrador" button in evaluation form
   - Distinguish guardar vs enviar in GuardarEvaluacionDocente
   - Show pending drafts in evaluation index
   - **Estimated effort:** UI changes + service logic update

### Done since last review (kept here for traceability, remove once confirmed stable)
- ~~Period Date Validation~~ — implemented in `GuardarEvaluacionDocente::ensurePeriodoActivo()` (commit `97e4824`, June 16, 2026)
- ~~ADMIN_UNIDAD_ACADEMICA Scope Hardening for config screens~~ — `/admin/evaluacion-docente/configuracion` and `/admin/administradores-unidades` are now in a route group gated to ADMIN only, separate from the shared ADMIN + ADMIN_UNIDAD_ACADEMICA group (`routes/web.php`)

---

## Known Issues

1. **Legacy DB Fragile Functions:** `fn_busca_alumnos_habilitacion_extracto()` and `fn_consultor_alumnos_deudas()` fail due to broken legacy view dependencies. The application uses verified working views instead.

2. **Vite Manifest Error:** "Unable to locate file in Vite manifest" — resolved by running `vendor/bin/sail npm run build` or `vendor/bin/sail npm run dev`.

3. **MaryUI Modal Conflict:** `x-modal` conflicts with Breeze's anonymous modal component. Use `x-mary-modal` or local wrappers.

4. **No Model Factories for Evaluation Module:** Current tests create models directly with `new Model()` or `Model::create()`, making test data setup verbose. Factories would simplify this.

---

## Evolution of Project Decisions

### Date: April 2026
- Project initialized with Laravel, Sail, PostgreSQL
- Dual-database architecture established
- Google OAuth selected as primary authentication
- MaryUI + DaisyUI + TailwindCSS 4 chosen for frontend

### Date: May 2026
- Student portal features built (carreras, materias, deudas)
- AlumnoExternoService created as centralized external DB facade
- Role system expanded to include ADMIN_UNIDAD_ACADEMICA
- LegacyAlumnoUserSyncService for batch user sync

### Date: June 2026
- Teacher evaluation module implemented
- Context sync mechanism (UI + CLI) delivered
- Schema guard pattern adopted for graceful migration handling
- Volt class-based SFC convention solidified
- UNE visual theme system finalized
- Results/reports screen with per-criterion breakdown, materia/carrera display, and Chart.js visualizations delivered (chart library question resolved: Chart.js chosen)
- Materia and carrera names resolved from legacy DB (AlumnoExternoService) and displayed in student index, form, and admin results views
- Admin academic unit assignments enhanced: sede selector, faculty filter, unsaved changes indicator, "Quitar todas", traceability with assigned_by/assigned_at
- Evaluation services moved into `App\Services\EvaluacionDocente\` namespace
- Period date validation and Docente soft delete added (June 16)
- ADMIN_UNIDAD_ACADEMICA scoped out of config/admin-management screens via route grouping (June 16)
- Evaluations linked to specific docente context (`docente_contexto_id`), allowing per-subject evaluation of the same teacher (June 19–25)
- i18n locale switching (es/en/pt/gn) and hybrid theme persistence (cookie + localStorage) delivered
- Normativas page added for institutional/legal documents
- "Extracto Académico" removed from navigation (underlying feature retained, just unlinked)

### Future Decisions Needed
- Export format for evaluation results (PDF vs Excel vs both)
- Whether to implement the draft state or keep evaluations as single-submit
- Whether FUNCIONARIO evaluation needs same context-matching logic as ALUMNO