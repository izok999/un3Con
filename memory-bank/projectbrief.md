# Project Brief — CONSULTOR UNESYS

## Project Summary

CONSULTOR UNESYS is a student-facing academic portal and administrative management system for the **Universidad Nacional del Este (UNE)**. The application provides authenticated students with real-time access to their academic records, enables teacher evaluations, and provides administrative dashboards for institutional staff.

## Core Requirements

1. **Student Academic Dashboard** — Real-time consultation of:
   - Active career enrollments (carreras/habilitaciones)
   - Enrolled subjects for the current period (materias inscriptas)
   - Historical academic transcript (extracto académico/calificaciones)
   - Outstanding debts and payment status (deudas/saldos)
   - Class attendance records (asistencia)
   - Curriculum progress (malla curricular)
   - Evaluation scores (puntajes de evaluaciones)
   - Issued certificates (certificados de estudios)

2. **Teacher Evaluation Module** — Allows students and coordinators/funcionarios to evaluate teachers:
   - Students evaluate their assigned teachers during an active evaluation period
   - Coordinators evaluate teachers within their academic unit scope
   - Parameterizable forms with weighted criteria
   - Weighted score calculation
   - Anti-duplicate enforcement (one evaluation per evaluator × teacher × period × form)

3. **Administrative Panel** — Institution staff can:
   - Look up any student's academic data (consulta-alumno)
   - Manage teacher records and academic contexts
   - Sync teacher contexts from the external legacy system
   - Configure evaluation periods, forms, and criteria

4. **Authentication & Authorization** — Secure access via:
   - Google OAuth (Laravel Socialite)
   - Role-based access control (Spatie Laravel-Permission)
   - Four roles: ADMIN, ADMIN_UNIDAD_ACADEMICA, FUNCIONARIO, ALUMNO

5. **Dual Database Architecture** — Seamless integration between:
   - Local PostgreSQL (users, roles, evaluations data)
   - External read-only legacy PostgreSQL (academic records, student data)

## Project Scope Boundaries

- **In scope:** Student academic queries, teacher evaluation, admin management, OAuth login, role-based access
- **Out of scope:** Writing to the legacy database, course registration, payment processing, grade submission

## Key Technical Constraints

- The external legacy database (`pgsql_externa`) is **strictly read-only** — no migrations, no writes
- Two databases on separate connections — **no JOINs** between them
- Bridge between systems: `users.documento` (cédula) ↔ `vw_alumnos_00.alu_perdoc`
- All external queries centralized through `AlumnoExternoService`
- Containerized development via Laravel Sail (Docker)