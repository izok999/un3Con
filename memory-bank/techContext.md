# Technical Context — CONSULTOR UNESYS

## Technologies Used

### Core Stack

| Technology | Version | Purpose |
|------------|---------|---------|
| **PHP** | ^8.3 (runtime 8.5) | Backend language |
| **Laravel** | 13.x | Web application framework |
| **Livewire** | 3.6.4+ | Reactive frontend components |
| **Volt** | 1.7.0+ | Single-file Livewire components |
| **MaryUI** | 2.8 | UI component library (DaisyUI/DaisyUI-based) |
| **TailwindCSS** | 4.x | Utility-first CSS framework |
| **DaisyUI** | 5.x | Component library on top of Tailwind |
| **PostgreSQL** | 18 (Alpine) | Primary database + external legacy |
| **Vite** | 7.x | Frontend build tool |
| **Laravel Sail** | 1.56+ | Docker development environment |

### Authentication & Authorization

| Package | Version | Purpose |
|---------|---------|---------|
| **Laravel Breeze** | 2.4+ | Authentication scaffolding |
| **Laravel Socialite** | 5.26+ | Google OAuth integration |
| **Spatie Laravel-Permission** | 7.3+ | Role-based access control |

### Development Tools

| Package | Version | Purpose |
|---------|---------|---------|
| **Laravel Boost** | 2.4+ | MCP tools for AI-assisted development |
| **Laravel Pint** | 1.27+ | PHP code formatter |
| **Laravel Pail** | 1.2.5+ | Log tailing |
| **PHPUnit** | 12.5.12 | Testing framework |
| **FakerPHP** | 1.23 | Test data generation |

### Node/JS Dependencies

| Package | Version | Purpose |
|---------|---------|---------|
| **@tailwindcss/vite** | ^4.0 | Tailwind Vite plugin |
| **daisyui** | ^5.0 | UI component styles |
| **tailwindcss** | ^4.0 | Utility CSS |
| **laravel-vite-plugin** | ^2.0 | Laravel Vite integration |
| **axios** | ^1.7 | HTTP client |
| **vite** | ^7.0 | Build tool |

## Development Setup

### Prerequisites
- Docker Desktop / Docker Engine + Compose v2
- Git 2.x
- No local PHP, Composer, or Node required (all run inside Sail containers)

### Environment Configuration

```env
# Database (local)
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password

# Database (external legacy - read only)
DB_EXTERNA_HOST=10.10.254.252
DB_EXTERNA_PORT=5432
DB_EXTERNA_DATABASE=une_base
DB_EXTERNA_USERNAME=usr_alu_web
DB_EXTERNA_PASSWORD="..."
DB_EXTERNA_SSLMODE=prefer
```

### Docker Services
| Service | Image | Port | Purpose |
|---------|-------|------|---------|
| `laravel.test` | sail-8.5/app | 80, 5173 | PHP 8.5 + Vite |
| `pgsql` | postgres:18-alpine | 5432 | Local PostgreSQL |
| `redis` | redis:alpine | 6379 | Cache & sessions |
| `mailpit` | axllent/mailpit:latest | 1025, 8025 | Email testing |
| `pgadmin` | dpage/pgadmin4:latest | 5050 | DB management UI |

### Key Commands

```bash
# Start services
vendor/bin/sail up -d

# Stop services
vendor/bin/sail stop

# Run artisan
vendor/bin/sail artisan [command]

# Run composer
vendor/bin/sail composer [command]

# Run npm
vendor/bin/sail npm run [script]

# Open in browser
vendor/bin/sail open

# Run tests
vendor/bin/sail artisan test --compact [--filter=TestName]

# Format PHP
vendor/bin/sail bin pint --dirty --format agent

# Build frontend
vendor/bin/sail npm run build

# Dev server
vendor/bin/sail composer run dev
```

### Environment Setup
```bash
# First time
vendor/bin/sail up -d
vendor/bin/sail artisan migrate
vendor/bin/sail artisan db:seed --class=FormularioEvaluacionSeeder
vendor/bin/sail artisan db:seed --class=RoleSeeder
vendor/bin/sail npm install
vendor/bin/sail npm run dev
```

## Technical Constraints

### Database Architecture
- **Two separate PostgreSQL connections** — `pgsql` (local) and `pgsql_externa` (read-only legacy)
- **No JOINs across connections** — must query each separately and combine in PHP
- **External DB is strictly read-only** — no migrations, no writes, no DDL
- **External DB uses schema-qualified view names:** `sh_maestros.vw_*`, `sh_movimientos.vw_*`, `sh_academico.vw_*`

### External Database Schemas
| Schema | Contents | Access Pattern |
|--------|----------|----------------|
| `sh_maestros` | Persons, students, careers, curricula, subjects, institutions, lecture periods | Query Builder read-only |
| `sh_academico` | Students, grade records, academic transcripts | Query Builder read-only |
| `sh_movimientos` | Enrollments, registrations, grades, debts, payments, attendance, evaluations | Query Builder read-only |
| `sh_rrhh` | Teachers, staff | Query Builder read-only |
| `sh_sistemas` | Users, profiles, system logs | Query Builder read-only |

### User Identity Bridge
- `users.documento` (local) → `vw_alumnos_00.alu_perdoc` (external) → `alu_id`
- A user without `documento` cannot access academic data
- `alu_id` resolved via `AlumnoExternoService::resolverAlumno()` which caches for 30 min

### Code Standards
- PHP 8.3+ type hints and return types required on all methods
- No `declare(strict_types=1)` — project doesn't use it
- Curly braces required for all control structures
- TitleCase for enum keys (e.g., `AdminUnidadAcademica`)
- Volt SFC components use class-based style (`new class extends Component`)
- Run Pint before committing: `vendor/bin/sail bin pint --dirty --format agent`

### Testing Standards
- PHPUnit 12 (not Pest)
- Feature tests for UI and integration, unit tests for services
- Mock `AlumnoExternoService` in tests to avoid external DB dependency
- Run minimal tests with filters: `--filter=TestName`
- Tests cover happy path, failure paths, and edge cases

### Frontend Constraints
- TailwindCSS 4 configuration is in `resources/css/app.css` only — no `tailwind.config.js`
- Alpine.js is bundled with Livewire 3 — do not include separately
- MaryUI prefix varies: `x-mary-*` in some views, unprefixed in others — match surrounding file convention
- Theme: `uneTheme` and `uneThemeDark` via `data-theme` attribute; persisted in `localStorage.une-theme`
- Vite manifest error means `vendor/bin/sail npm run build` or `vendor/bin/sail npm run dev` is needed

### Tool Usage Patterns
- **Laravel Boost MCP:** `search-docs`, `database-schema`, `database-query`, `browser-logs`, `get-absolute-url`
- Use Boost tools over raw SQL or manual shell commands when possible
- `search-docs` with broad topic queries, avoid adding package names to queries

## Dependencies Map

### PHP Packages (require)
```
laravel/framework: ^13.0
laravel/socialite: ^5.26
laravel/tinker: ^3.0
livewire/livewire: ^3.6.4
livewire/volt: ^1.7.0
robsontenorio/mary: ^2.8
spatie/laravel-permission: ^7.3
```

### PHP Packages (require-dev)
```
laravel/boost: ^2.4
laravel/breeze: ^2.4
laravel/pail: ^1.2.5
laravel/pint: ^1.27
laravel/sail: ^1.56
phpunit/phpunit: ^12.5.12
fakerphp/faker: ^1.23
mockery/mockery: ^1.6
nunomaduro/collision: ^8.6
```

### NPM Packages (devDependencies)
```
@tailwindcss/vite: ^4.0
axios: ^1.7
daisyui: ^5.0
laravel-vite-plugin: ^2.0
tailwindcss: ^4.0
vite: ^7.0
```

## File Structure Reference

```
app/
├── Console/Commands/          # Artisan commands (e.g., sync contexts)
├── Enums/RoleName.php         # Role enum with middleware helper
├── Http/Controllers/          # Thin controllers
├── Livewire/Forms/LoginForm.php
├── Models/                    # Eloquent models (pgsql local)
│   ├── User.php
│   ├── Docente.php
│   ├── DocenteContexto.php
│   ├── PeriodoEvaluacion.php
│   ├── FormularioEvaluacion.php
│   ├── FormularioCriterio.php
│   ├── EvaluacionDocente.php
│   └── EvaluacionRespuesta.php
├── Providers/
├── Services/
│   ├── AlumnoExternoService.php        # All external DB queries
│   ├── LegacyAlumnoUserSyncService.php # Batch user sync
│   └── EvaluacionDocente/              # Evaluation module services (namespaced)
│       ├── DocentesElegiblesResolver.php   # Teacher-student context matching, returns docente+contexto pairs
│       ├── GuardarEvaluacionDocente.php    # Evaluation validation + persistence (period dates, duplicates, criteria)
│       └── PuntajeCalculator.php           # Weighted score calculation
└── View/

resources/
├── css/app.css                # Tailwind 4 + DaisyUI themes + glass utilities
├── js/app.js                  # Theme toggle (cookie + localStorage hybrid persistence) + navbar scroll
├── views/
│   ├── layouts/
│   │   ├── app.blade.php      # Authenticated shell (sidebar + topbar + mobile nav)
│   │   └── guest.blade.php    # Unauthenticated shell
│   ├── dashboard.blade.php    # Post-login dashboard
│   ├── profile.blade.php
│   ├── pages/
│   │   └── normativas/index.blade.php   # Institutional/legal documents page
│   ├── livewire/
│   │   ├── alumno/            # Student portal Volt SFCs
│   │   │   ├── mis-carreras.blade.php
│   │   │   ├── detalle-carrera.blade.php
│   │   │   ├── mis-materias.blade.php
│   │   │   ├── mis-deudas.blade.php
│   │   │   └── evaluacion-docente/
│   │   │       ├── index.blade.php
│   │   │       └── form.blade.php   # route: /evaluacion-docente/{docente}/{contexto}
│   │   ├── admin/             # Admin panel Volt SFCs
│   │   │   ├── consulta-alumno.blade.php
│   │   │   ├── administradores-unidades.blade.php
│   │   │   └── evaluacion-docente/
│   │   │       ├── configuracion.blade.php    # ADMIN-only route group
│   │   │       ├── docentes.blade.php         # parent component (~820 lines)
│   │   │       ├── docente-contextos.blade.php # child component (~1100 lines)
│   │   │       └── resultados.blade.php
│   │   └── pages/auth/        # Login, register, OAuth
│   └── components/            # Local MaryUI wrappers
├── docs/                      # Project documentation (design notes, not user-facing)
│   ├── PROMPT_MAESTRO.md      # Master LLM prompt
│   ├── Evalaución.md          # Teacher evaluation spec (portable)
│   ├── modulo-evaluacion-docente.md  # Module status doc
│   ├── Guia.md                # Complete setup guide
│   ├── marco_legal.md         # Legal framework backing the Normativas page
│   ├── legacy-queries.md
│   ├── TRANSLATIONS.md        # i18n system documentation
│   ├── persistencia-tema-y-redireccion-login-2026-05-19.md
│   ├── alcance-admin-unidad-academica-2026-06-01.md
│   └── ...                    # Additional dated design docs
└── (no resources/lang — see root-level lang/ below)

config/normativas.php          # Institutional/legal document list backing /normativas

lang/                          # Translations — es.json, en.json, pt.json, gn.json (root-level, NOT resources/lang)

config/
├── database.php               # pgsql + pgsql_externa connections
└── ...

routes/
├── web.php                    # Role-guarded routes
└── auth.php                   # Breeze auth routes

database/
├── migrations/
├── factories/
└── seeders/
    ├── RoleSeeder.php
    └── FormularioEvaluacionSeeder.php

tests/
├── Feature/
│   ├── Admin/
│   │   ├── AdminEvaluacionDocenteConfigurationTest.php
│   │   └── AdminEvaluacionDocenteManagementTest.php
│   ├── Alumno/
│   │   └── AlumnoEvaluacionDocenteFlowTest.php
│   └── Console/
│       └── SincronizarContextosDocentesCommandTest.php
└── Unit/

.agents/skills/               # Domain-specific AI agent skills
├── alumno-dashboard.agent.md
├── maryui-development/SKILL.md         # MaryUI prefix rules, anti-patterns from real bugs
├── laravel-best-practices/SKILL.md
├── laravel-permission-development/SKILL.md
├── livewire-development/SKILL.md
├── socialite-development/SKILL.md
├── tailwindcss-development/SKILL.md
└── volt-development/SKILL.md

memory-bank/                   # Cline Memory Bank (this directory)
├── projectbrief.md
├── productContext.md
├── activeContext.md
├── systemPatterns.md
├── techContext.md
└── progress.md