# Alcance de ADMIN_UNIDAD_ACADEMICA y próximos pasos

Fecha: 2026-06-01

## Propósito

Este documento deja contexto sobre la línea de trabajo que se abrió para `ADMIN_UNIDAD_ACADEMICA`.

El objetivo no fue solo crear un rol nuevo, sino empezar a convertirlo en un rol realmente acotado por unidad académica, sin compartir de forma irrestricta el alcance del `ADMIN` general.

En términos funcionales, la idea que ya quedó implementada es esta:

- existe un `ADMIN` general con control global,
- existe un `ADMIN_UNIDAD_ACADEMICA` con acceso administrativo parcial,
- y ese admin parcial solo debe operar sobre alumnos y superficies administrativas vinculadas a sus facultades asignadas.

## Qué cambió

### 1. Catálogo local de unidades académicas

Se consolidó un catálogo local de facultades en `academic_units`.

Ese catálogo guarda, entre otros datos:

- `slug`
- `name`
- datos de contacto y sitio
- `legacy_sede_ids`

La clave práctica de este diseño es que el sistema no depende de una única `sed_id` para representar una facultad, sino que puede mapear una unidad académica local a varias sedes legacy.

Pieza central:

- `app/Models/AcademicUnit.php`

El modelo además expone `primarySedeId()` como helper para elegir una sede de referencia al persistir asignaciones iniciales.

### 2. Scope local por usuario

Se incorporó persistencia local del alcance administrativo en `user_academic_unit_scopes`.

Cada registro vincula:

- un `user_id`
- un `academic_unit_id`
- un `sed_id` de anclaje

Pieza central:

- `app/Models/UserAcademicUnitScope.php`

La decisión importante acá es que el `sed_id` guardado no es el alcance completo. El alcance efectivo se expande usando `legacy_sede_ids` de la facultad.

Eso hoy se resuelve en:

- `UserAcademicUnitScope::resolvedSedeIds()`
- `User::managedSedeIds()` en `app/Models/User.php`

### 3. Corte de acceso para admins sin facultades asignadas

Se agregó un middleware para evitar que un `ADMIN_UNIDAD_ACADEMICA` entre a los módulos administrativos compartidos si todavía no tiene ninguna asignación.

Pieza central:

- `app/Http/Middleware/EnsureAcademicUnitScope.php`

Comportamiento actual:

- si el usuario es `ADMIN`, no se bloquea por este middleware,
- si el usuario es `ADMIN_UNIDAD_ACADEMICA` y no tiene scopes, recibe `403`.

Esto evita un estado ambiguo donde el rol existe pero todavía no tiene cobertura operativa.

### 4. Pantalla para asignar facultades a admins de unidad

Ya existe una pantalla administrativa para que el `ADMIN` general asigne facultades a usuarios con rol `ADMIN_UNIDAD_ACADEMICA`.

Superficie actual:

- ruta: `admin.academic-unit-admins`
- vista Volt: `resources/views/livewire/admin/administradores-unidades.blade.php`

Qué hace hoy:

- lista admins de unidad académica,
- permite buscarlos por nombre, email o documento,
- muestra las facultades ya asignadas,
- permite seleccionar múltiples facultades,
- y reemplaza los scopes actuales del usuario al guardar.

Decisión de implementación importante:

- al guardar, se elimina el conjunto anterior de scopes del usuario y se reconstruye desde la selección actual.

Eso simplifica consistencia, aunque todavía no conserva historial de cambios ni auditoría.

### 5. Consulta de alumnos ahora comparte acceso, pero con restricción real

Antes `admin.consulta-alumno` estaba reservada al `ADMIN` general.

Ahora la ruta quedó dentro del grupo administrativo compartido entre:

- `ADMIN`
- `ADMIN_UNIDAD_ACADEMICA`

Pieza central:

- `routes/web.php`
- `resources/views/livewire/admin/consulta-alumno.blade.php`

Comportamiento actual:

- un `ADMIN` general mantiene acceso total,
- un `ADMIN_UNIDAD_ACADEMICA` con facultades asignadas puede entrar,
- pero la búsqueda valida primero si el alumno pertenece a una sede alcanzable por ese admin,
- y si no pertenece, la consulta se corta con mensaje explícito.

La validación actual se apoya en dos fuentes del servicio externo:

- `carreras()` usando `sed_id`
- `materiasInscriptas()` usando `sed_id`, `rsc_idsed` o `imi_idsed`

Si no aparece ninguna coincidencia con las sedes permitidas, el componente responde:

> Solo podés consultar alumnos vinculados a las facultades que tenés asignadas.

Además, cuando el acceso sí corresponde, el componente filtra al menos:

- `carreras`
- `materias`

Y muestra una alerta visible indicando que la consulta está restringida por facultad.

## Qué decisiones ya quedaron tomadas

### El alcance se modela localmente, no con equipos de Spatie

Para este proyecto, el scope por facultad no se está resolviendo con `teams` de Spatie Permission.

La decisión vigente es mantenerlo con tablas y relaciones locales del dominio:

- `academic_units`
- `user_academic_unit_scopes`

Eso encaja mejor con la necesidad de mapear una facultad local a varias sedes legacy.

### La facultad es el concepto de negocio; la sede legacy es el puente técnico

La UI y la asignación administrativa están pensadas en términos de facultades.

La `sed_id` sigue existiendo, pero hoy funciona más como identificador técnico de interoperabilidad con el sistema externo.

### El primer punto fuerte de restricción quedó en `consulta-alumno`

Esto es importante porque `consulta-alumno` es la primera superficie donde el alcance dejó de ser solo decorativo y pasó a condicionar el dato que el usuario puede consultar.

No es solo una diferencia de menú o de rutas.

## Estado actual validado

Las pruebas que ya cubren esta línea son:

- `tests/Feature/Admin/AdminAcademicUnitAdminsTest.php`
- `tests/Feature/Admin/AdminConsultaAlumnoScopeTest.php`
- `tests/Feature/Roles/RoleAccessTest.php`
- `tests/Feature/Admin/AdminEvaluacionDocenteManagementTest.php`

Estas pruebas hoy validan, entre otras cosas:

- que el `ADMIN` general puede abrir la pantalla de asignación,
- que puede asignar múltiples facultades a un admin de unidad,
- que el admin de unidad puede abrir `consulta-alumno` si tiene scope,
- que la consulta se permite para alumnos dentro de sus sedes,
- que se bloquea para alumnos fuera de su alcance,
- y que el menú/ruteo sigue separando lo global de lo acotado.

## Limitaciones del estado actual

### 1. La restricción fuerte está concentrada en carreras y materias

La decisión de autorización en `consulta-alumno` hoy usa `carreras()` y `materiasInscriptas()` como señales de pertenencia a la unidad académica.

Eso es razonable como primer corte, pero deja abierta esta pregunta:

- una vez habilitado el acceso al alumno, ¿qué nivel de filtrado debería aplicarse a `extracto`, `deudas`, `asistencia`, `malla` y `certificados`?

Hoy esos bloques se cargan completos una vez que el alumno pasó la validación inicial.

### 2. No hay auditoría de cambios de asignación

La pantalla actual resuelve bien la operación de asignar facultades, pero no deja trazabilidad de:

- quién cambió el alcance,
- cuándo lo cambió,
- qué facultades tenía antes,
- cuáles quedaron después.

### 3. No hay una capa centralizada de políticas para este alcance

Parte del comportamiento hoy está distribuido entre:

- rutas,
- middleware,
- helpers del modelo `User`,
- lógica interna de componentes Volt.

Funciona, pero a medida que aparezcan más pantallas administrativas con alcance parcial, conviene que la autorización empiece a vivir en una capa más explícita y reutilizable.

### 4. El alcance sigue siendo facultad -> sedes, pero no todavía facultad -> operaciones completas

Hoy ya existe el mapa de pertenencia.

Lo que todavía falta consolidar es una matriz más completa de operaciones del tipo:

- qué puede ver,
- qué puede editar,
- qué puede exportar,
- qué puede configurar,
- y sobre qué entidades aplica cada permiso.

## Caminos recomendados para avanzar desde este frente

### Camino 1: consolidar el alcance como servicio/policy reutilizable

Recomendación:

- extraer una capa como `AcademicUnitScopeResolver` o política equivalente,
- centralizar ahí la respuesta a preguntas como `puede ver alumno`, `puede gestionar sede`, `puede tocar docente`, `puede usar pantalla`.

Ventaja:

- evita duplicar lógica entre Volt, middleware y controladores futuros,
- y reduce el riesgo de que una nueva pantalla olvide aplicar el filtro.

### Camino 2: endurecer `consulta-alumno` por bloques de información

Recomendación:

- revisar qué datasets del servicio externo exponen `sed_id` o algún vínculo confiable con unidad académica,
- y filtrar también tabs adicionales cuando esa relación exista.

Orden sugerido:

1. `extractoAcademico`
2. `asistencia`
3. `deudas`
4. `certificados`

Objetivo:

- que el alcance no solo habilite o bloquee la consulta del alumno, sino que también limite la porción de información visible cuando el alumno tiene actividad en varias sedes o facultades.

### Camino 3: definir mejor el rol `FUNCIONARIO`

La conversación original abrió esta necesidad:

- existe un rol `FUNCIONARIO`,
- y ese funcionario puede o no ser docente.

Ese frente todavía necesita definición operativa.

Preguntas que conviene cerrar:

- ¿el `FUNCIONARIO` tendrá también alcance por unidad académica?
- ¿puede coexistir con `ADMIN_UNIDAD_ACADEMICA`?
- ¿qué módulos comparte con docentes y cuáles no?
- ¿necesita el mismo esquema de facultades o uno distinto?

Mi recomendación es no mezclar todavía `FUNCIONARIO` dentro del mismo alcance hasta cerrar su matriz funcional.

### Camino 4: mejorar UX de la asignación de facultades

La pantalla actual es suficiente para operar, pero todavía se puede mejorar bastante.

Mejoras concretas:

- mostrar cantidad de sedes efectivas por facultad,
- mostrar un resumen de alcance total por admin,
- resaltar admins sin asignaciones como estado de atención,
- agregar confirmación o diff visual antes de guardar,
- agregar filtros por facultad asignada.

Esto no cambia seguridad, pero sí hace más robusta la operación diaria.

### Camino 5: sumar auditoría y trazabilidad

Si este esquema va a usarse en producción con varios operadores, conviene registrar cambios de alcance.

Opciones razonables:

- tabla propia de auditoría,
- activity log,
- o al menos eventos internos con actor y payload de cambios.

Eso simplifica soporte, revisiones internas y explicación de incidentes de acceso.

### Camino 6: ampliar batería de pruebas hacia escenarios mixtos

Hoy la cobertura base está bien.

Los siguientes casos que más valor sumarían son:

- admin de unidad con más de una facultad asignada,
- alumno con carreras en sedes mixtas,
- alumno visible por una carrera pero con datos secundarios fuera de alcance,
- admin sin facultades luego de una reasignación,
- convivencia de roles múltiples en un mismo usuario.

## Recomendación práctica de prioridad

Si hubiera que seguir mañana por este mismo frente, el orden que más sentido tiene es:

1. centralizar la resolución de alcance en una capa reutilizable,
2. endurecer el filtrado de `consulta-alumno` para más bloques de datos,
3. definir la matriz funcional de `FUNCIONARIO`,
4. agregar auditoría de asignaciones,
5. recién después iterar UX fina de la pantalla administrativa.

## Resumen ejecutivo

Lo importante de este cambio no es solo que ahora exista una pantalla para asignar facultades.

Lo importante es que el proyecto ya dio el primer paso real para que `ADMIN_UNIDAD_ACADEMICA` deje de ser un admin casi global y pase a ser un actor con alcance explícito, persistido localmente y aplicado sobre datos reales.

La base ya está puesta.

Lo que sigue ahora no es rehacer el modelo, sino profundizarlo y volverlo más uniforme en todos los módulos administrativos que todavía comparten lógica con el `ADMIN` general.