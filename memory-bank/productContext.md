# Product Context — CONSULTOR UNESYS

## Why This Project Exists

The Universidad Nacional del Este (UNE) operates a legacy academic management system (the "consultor viejo") with a PostgreSQL database (`une_base`). Students and staff need access to academic data, but the legacy system has limitations: it lacks a modern UI, doesn't support OAuth-based authentication, and has no teacher evaluation functionality.

CONSULTOR UNESYS bridges this gap by providing a **modern Laravel portal** that reads real-time data from the legacy system while offering new capabilities (teacher evaluation, role-based dashboards) on top of a fresh local database—all without touching or modifying the legacy system.

## Problems It Solves

1. **Student Self-Service Access** — Students can view their academic records (grades, subjects, debts, attendance, curriculum progress) from any device with a responsive, modern interface.

2. **Teacher Evaluation Gap** — The legacy system has no built-in evaluation mechanism. This portal introduces a complete teacher evaluation module with parameterizable forms, weighted scoring, and anti-duplicate safeguards.

3. **Role-Based Administration** — Different staff roles (ADMIN, ADMIN_UNIDAD_ACADEMICA) have scoped access to management features without exposing the entire system to everyone.

4. **Google OAuth Integration** — Students and staff authenticate with their Google accounts rather than managing separate credentials, lowering the barrier to access.

5. **Zero Legacy Impact** — All new data (users, roles, evaluations) lives in a separate local database. The legacy system remains untouched and operational.

## How It Should Work

### Authentication Flow
1. User clicks "Login with Google"
2. OAuth callback resolves the user and checks `users.documento` against the legacy DB
3. Post-login, the dashboard dispatches the user to their role-appropriate view

### Student Portal
1. Student logs in → `dashboard` → sees role-specific welcome cards
2. Navigates to "Mis Carreras" → sees active career enrollments
3. Navigates to "Mis Materias" → sees enrolled subjects for current period
4. Navigates to "Evaluación Docente" → sees eligible teachers + evaluation form
5. Navigates to "Mis Deudas" → sees outstanding payments

### Teacher Evaluation
1. Admin creates an active evaluation period and form(s)
2. Student sees eligible teachers (matched via `DocentesElegiblesResolver`)
3. Student fills form with scale/text responses → submits
4. System calculates weighted score and prevents duplicates
5. Admin can view sync teacher contexts from legacy system

### Admin Panel
1. ADMIN configures periods, forms, criteria
2. ADMIN and ADMIN_UNIDAD_ACADEMICA manage teachers and contexts
3. Admin can look up any student's academic data
4. ADMIN_UNIDAD_ACADEMICA is scoped to their assigned academic units

## User Experience Goals

- **Accessible & Fast** — Real-time queries to external DB with caching for profile/career data (30-min TTL)
- **Mobile-First** — Responsive design with thumb-friendly navigation, bottom nav on mobile
- **Consistent Visual System** — UNE theme (light/dark) via DaisyUI themes, glass-morphism surfaces, `bg-app-pattern`
- **Skeletal Loading** — Layout-preserving skeletons instead of spinners during data fetches
- **Clear Information Hierarchy** — Lead with primary action/metric, provide supporting context below
- **Intuitive Navigation** — Persistent sidebar (desktop), bottom nav (mobile), direct actions over hidden dropdowns
- **Role-Appropriate Views** — Each role sees only what's relevant to them; no leaked admin features to students

## Target Users

| Role | Description | Primary Needs |
|------|-------------|---------------|
| ALUMNO | UNE student | View grades, subjects, debts, attendance; evaluate teachers |
| FUNCIONARIO | Institutional staff | Evaluate teachers (pending implementation) |
| ADMIN_UNIDAD_ACADEMICA | Academic unit administrator | Manage teachers/contexts for their unit; look up students |
| ADMIN | System administrator | Full config: periods, forms, criteria; manage all teachers |