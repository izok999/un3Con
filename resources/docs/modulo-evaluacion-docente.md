# Módulo de Evaluación Docente

Documentación de estado actual — Junio 2026

---

## Qué hace el módulo

El módulo permite que los alumnos de la UNE evalúen a sus docentes durante un período académico activo. Cada evaluación registra respuestas por criterio, calcula un puntaje ponderado y garantiza que un alumno no pueda evaluar al mismo docente más de una vez por período.

---

## Arquitectura

### Modelos y relaciones

```
PeriodoEvaluacion ──has many──▶ EvaluacionDocente
FormularioEvaluacion ──has many──▶ FormularioCriterio (ordenados por 'orden')
FormularioEvaluacion ──has many──▶ EvaluacionDocente
Docente ──has many──▶ DocenteContexto
Docente ──has many──▶ EvaluacionDocente
EvaluacionDocente ──has many──▶ EvaluacionRespuesta
EvaluacionRespuesta ──belongs to──▶ FormularioCriterio
EvaluacionDocente ──belongs to──▶ User (evaluador_user_id)
```

### Tablas (todas en la conexión `pgsql` local)

| Tabla | Propósito |
|---|---|
| `periodos_evaluacion` | Períodos de evaluación (uno activo a la vez) |
| `docentes` | Registro de docentes (puede estar vinculado a un User local) |
| `docente_contextos` | Restricciones de contexto académico por docente (carrera, sede, período, materia, turno, sección) |
| `formularios_evaluacion` | Formularios parametrizables por tipo de evaluador |
| `formulario_criterios` | Criterios/preguntas de cada formulario con peso y tipo |
| `evaluaciones_docentes` | Cabecera de una evaluación (quién, a quién, en qué período, con qué formulario) |
| `evaluacion_respuestas` | Respuestas por criterio de cada evaluación |

### Servicios

| Servicio | Responsabilidad |
|---|---|
| `DocentesElegiblesResolver` | Determina qué docentes puede evaluar un alumno según su contexto académico (carrera, materia, período, etc.) consultando la BD externa |
| `GuardarEvaluacionDocente` | Valida y persiste una evaluación completa con sus respuestas; bloquea duplicados y tipo de evaluador incorrecto |
| `PuntajeCalculator` | Calcula el puntaje ponderado: `∑(valor × peso) / ∑(peso)` sobre criterios de tipo `escala` o `mixto` |
| `AlumnoExternoService::contextosDocentePorDocumento()` | Devuelve los contextos completos de un docente cruzando `vw_anexo_items_profesores_questions` (asignaciones) con `vw_alumnos_inscriptos_materias_14` (inscripciones) para obtener `car_id`, `ple_id` y `tur_id` que la vista de profesores no expone directamente |

---

## Flujo completo del alumno

```
1. GET /evaluacion-docente
   └── index.blade.php
       ├── Muestra el período activo y sus fechas
       ├── Lista docentes elegibles (según contexto del alumno en la BD externa)
       ├── Marca como "Ya evaluado" los docentes ya evaluados en el período activo
       └── Historial de evaluaciones anteriores del alumno (por período seleccionable)

2. GET /evaluacion-docente/{docente}
   └── form.blade.php
       ├── Verifica que existe período activo y formulario de tipo 'alumno' activo
       ├── Verifica que el alumno tiene contexto coincidente con el docente (403 si no)
       ├── Verifica que no existe evaluación previa (redirige al index si ya evaluó)
       ├── Muestra una card por criterio con select (escala) o textarea (texto)
       └── Al enviar: llama a GuardarEvaluacionDocente → flash + redirect al index
```

### Coincidencia de contexto (DocentesElegiblesResolver)

Un docente es elegible para un alumno si al menos uno de sus `DocenteContexto` activos coincide con el contexto académico del alumno. Los campos `NULL` en el contexto del docente actúan como **comodín** (matchean cualquier valor del alumno). Los datos del alumno se obtienen de la BD externa (`pgsql_externa`) mediante `AlumnoExternoService`.

Ejemplo: un contexto `{car_id: 14, sed_id: null, ple_id: 68, mi2_id: null, tur_id: null, sec_id: null}` matchea a cualquier alumno de la carrera 14 en el período 68, sin importar sede, materia, turno o sección.

---

## Flujo del administrador

### `/admin/evaluacion-docente/configuracion` (solo ADMIN)

- **Períodos:** crear/editar períodos con fecha inicio y fin. Activar uno desactiva todos los demás (sin restricción de DB — lógica en el componente).
- **Formularios:** crear formularios de tipo `alumno` o `funcionario` con escala numérica configurable. Activar uno desactiva el otro activo del mismo tipo.
- **Criterios:** agregar preguntas a un formulario con peso, orden, tipo (`escala` / `texto` / `mixto`) y flag de obligatoriedad.

### `/admin/evaluacion-docente/docentes` (ADMIN o ADMIN_UNIDAD_ACADEMICA)

- Crear y editar docentes (nombre, documento, id externo, vínculo con User).
- Buscar docentes con filtro en tiempo real.
- Agregar y eliminar contextos académicos con selectores que muestran nombres reales desde la BD externa.
- **Sincronizar contextos desde el sistema externo** con el botón "Sincronizar" por docente: consulta `vw_anexo_items_profesores_questions` + `vw_alumnos_inscriptos_materias_14` y crea los `DocenteContexto` faltantes automáticamente (idempotente).
- Los `ADMIN_UNIDAD_ACADEMICA` tienen scope restringido a sus sedes asignadas y no pueden configurar formularios ni períodos.

---

## Cálculo del puntaje

Para criterios de tipo `escala` o `mixto` con `valor_numerico` no nulo y `peso > 0`:

$$\text{puntaje} = \frac{\sum(\text{valor}_i \times \text{peso}_i)}{\sum \text{peso}_i}$$

Los criterios de tipo `texto` y los opcionales sin respuesta no afectan el puntaje. El resultado se redondea a 2 decimales.

**Ejemplo con el formulario de alumnos por defecto** (escala 1–5, pesos: 20, 20, 20, 15, 15, 10):

Respuestas `[5, 4, 5, 4, 3, 5]` → `(100 + 80 + 100 + 60 + 45 + 50) / 100` = **4.35**

---

## Seeders

`FormularioEvaluacionSeeder` (idempotente vía `updateOrCreate`) crea:

**Formulario alumno** (escala 1–5):
| Criterio | Peso | Tipo |
|---|---|---|
| Explica los contenidos con claridad | 20 | escala |
| Demuestra dominio del contenido | 20 | escala |
| Utiliza metodologías que favorecen el aprendizaje | 20 | escala |
| Cumple con puntualidad y carga horaria | 15 | escala |
| Evalúa coherentemente con lo desarrollado | 15 | escala |
| Comunicación respetuosa y efectiva | 10 | escala |
| Observaciones generales | 0 | texto (opcional) |

**Formulario funcionario** (escala 1–5): estructura equivalente con criterios de foco institucional/supervisorio.

Para seedear: `vendor/bin/sail artisan db:seed --class=FormularioEvaluacionSeeder`

---

## Tests existentes

| Archivo | Cobertura |
|---|---|
| `tests/Feature/Admin/AdminEvaluacionDocenteConfigurationTest.php` | Página de config, crear período y desactivar el anterior, crear formulario y agregar criterio |
| `tests/Feature/Admin/AdminEvaluacionDocenteManagementTest.php` | Página de docentes, crear docente, agregar contexto, scope de ADMIN_UNIDAD_ACADEMICA, sincronización desde sistema externo (acción individual e idempotencia) |
| `tests/Feature/Console/SincronizarContextosDocentesCommandTest.php` | Comando `evaluacion:sincronizar-contextos`: creación masiva, skip de inactivos/sin documento, idempotencia en duplicados, opción `--periodo` |
| `tests/Feature/Alumno/AlumnoEvaluacionDocenteFlowTest.php` | Puntaje ponderado correcto, bloqueo de duplicado, criterios obligatorios, tipo de evaluador incorrecto, pantalla index y form, guard de schema |

Ejecutar todos: `vendor/bin/sail artisan test --compact`

---

## Estado actual: qué falta para el 100%

### 1. Comando de sincronización masiva — **Disponible** ✅

`vendor/bin/sail artisan evaluacion:sincronizar-contextos [--periodo=YYYY]`

Itera todos los docentes activos con documento y llama a `AlumnoExternoService::contextosDocentePorDocumento()` para importar sus contextos. Acepta `--periodo=2026` para restringir el año. Idempotente (usa `firstOrCreate`).

### 2. Flujo del funcionario (evaluador institucional) — **No implementado**

El modelo, la tabla y el seeder ya contemplan el tipo `funcionario`, pero falta toda la interfaz:

- Volt component `alumno/evaluacion-docente/form.blade.php` es solo para `ALUMNO`; no hay ruta ni componente para que un funcionario abra el formulario de su tipo
- No hay middleware/rol que habilite a un `FUNCIONARIO` a navegar al módulo 
- No hay tests de este flujo

**Estimado:** una ruta `GET /evaluacion-docente/funcionario/{docente}`, un componente Volt análogo al del alumno pero con `TIPO_FUNCIONARIO`, y los tests correspondientes.

### 2. Pantalla de resultados / reportes — **No implementada**

No existe ninguna vista que muestre:

- Puntajes por docente y período
- Distribución de respuestas por criterio
- Comparativa entre evaluaciones de alumnos y funcionarios
- Posibilidad de exportar (PDF / Excel)

Solo los datos están en la BD; no hay UI para consultarlos.

### 3. Estado borrador (`ESTADO_BORRADOR`) — **No usado**

La constante existe en `EvaluacionDocente::ESTADO_BORRADOR` pero `GuardarEvaluacionDocente` siempre guarda como `enviada`. No hay flujo de "guardar y continuar después". Si se quiere implementar, se necesita:

- Lógica en el servicio para distinguir guardar vs. enviar
- UI con botón "Guardar borrador" y botón "Enviar"
- Que el index muestre borradores pendientes como acción rápida

### 4. Factories para modelos de evaluación — **Ausentes**

No existen `DocenteFactory`, `DocenteContextoFactory`, `EvaluacionDocenteFactory`, etc. Los tests crean los modelos directamente. Esto dificulta ampliar la cobertura de tests.

### 5. Restricción de fechas del período — **No forzada en el formulario**

`PeriodoEvaluacion` tiene `fecha_inicio` y `fecha_fin`, pero `GuardarEvaluacionDocente` solo verifica que `$periodo->activo === true`. No valida si la fecha actual cae dentro del rango. Un período podría estar marcado como activo pero fuera de sus fechas y el alumno podría igual enviar una evaluación.

### 6. Scope de ADMIN_UNIDAD_ACADEMICA en configuración — **No aplicado**

El componente de configuración (`/admin/evaluacion-docente/configuracion`) no está disponible para `ADMIN_UNIDAD_ACADEMICA`, pero tampoco hay nada que les impida ver/editar períodos o formularios si modifican la URL directamente (más allá del middleware de rol). No es un bug grave hoy porque el middleware `role:ADMIN` ya bloquea la ruta, pero sí sería relevante si en el futuro se les quiere dar acceso parcial.

---

## Resumen de estado

| Área | Estado |
|---|---|
| Schema (7 tablas + migraciones) | ✅ Completo |
| Configuración admin (períodos, formularios, criterios) | ✅ Completo |
| Gestión de docentes y contextos (admin + scope) | ✅ Completo |
| Sincronización de contextos desde sistema externo (UI + comando) | ✅ Completo |
| Flujo alumno: index + formulario + envío | ✅ Completo |
| Cálculo de puntaje ponderado | ✅ Completo |
| Guard de schema (sin migrations → aviso amigable) | ✅ Completo |
| Seeder de formularios | ✅ Completo |
| Tests (18 tests) | ✅ Completo |
| Flujo funcionario | ❌ Pendiente |
| Pantalla de resultados / reportes | ❌ Pendiente |
| Estado borrador | ❌ Pendiente (diseñado, no implementado) |
| Factories de modelos | ❌ Pendiente |
| Validación de fechas del período al enviar | ❌ Pendiente (minor) |
