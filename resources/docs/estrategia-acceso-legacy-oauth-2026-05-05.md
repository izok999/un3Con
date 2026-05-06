# Estrategia de acceso legacy + cuenta local + OAuth

Fecha: 2026-05-05

## Propósito de este documento

Este documento complementa a:

- `resources/docs/propuesta-sync-usuarios-legacy-2026-04-22.md`
- `resources/docs/cambios-profile-2026-04-22.md`

La propuesta de sincronización legacy define cómo preparar la tabla `users` con identidad local mínima.
Los cambios de `/profile` corrigen problemas de UI y testing.

Ninguno de esos documentos describe de punta a punta el objetivo funcional que hoy se busca para autenticación.

## Objetivo final

La aplicación debe soportar este recorrido de transición:

- el usuario entra con su acceso legacy usando `documento + PIN`,
- mantiene `documento` como identificador principal e inmutable,
- puede reclamar o completar su cuenta local con email real y contraseña local,
- luego agrega o confirma su cuenta de Google para OAuth,
- y finalmente puede usar la misma identidad local tanto con login tradicional como con Google.

En términos prácticos, el objetivo final es:

> entra con su usuario y contraseña legado, y luego agrega o confirma su correo de Google para OAuth.

Con la decisión actual del proyecto, el puente operativo inmediato no es migrar el `PIN` viejo a `password` local, sino:

- mantener el login legacy por `documento + PIN`,
- usar el registro/claim actual para que el usuario fije su contraseña local,
- y después vincular Google sobre esa misma cuenta.

## Relación con la sincronización legacy

La sincronización masiva sigue siendo válida y necesaria.

Su responsabilidad es dejar preparada la identidad local mínima:

- crear o actualizar `users`,
- asignar rol `ALUMNO`,
- conservar `documento` como clave de vínculo,
- generar email técnico si todavía no existe uno real,
- no copiar el `PIN` viejo a `password` local.

Eso deja la base lista para la transición posterior de acceso.

## Principios de diseño

### 1. `documento` es la llave principal

Para este proyecto, `documento` se considera estable e inmutable.

Por lo tanto:

- toda exportación o sincronización debe vincular por `documento`,
- todo claim de cuenta debe resolver por `documento`,
- toda vinculación OAuth debe terminar asociada al mismo `documento`,
- nunca se debe crear una segunda cuenta local para la misma persona si ya existe una con ese `documento`.

### 2. La base externa sigue siendo la fuente de verdad académica

La nueva app no replica carreras, extracto, materias o deudas al PostgreSQL local.

La base local guarda identidad de acceso.
La base externa sigue resolviendo los datos académicos.

### 3. El acceso legacy y el acceso local son etapas distintas

No se mezcla el `PIN` legacy con el `password` local de Laravel.

El `PIN` sirve como puente de transición.
La contraseña local se fija después, cuando el usuario reclama o completa su cuenta.

### 4. Google OAuth se vincula a una cuenta existente, no a una identidad paralela

Google no debe crear una segunda identidad funcional para el mismo alumno.

Si ya existe un usuario local por `documento`, el flujo correcto es vincular Google a esa cuenta.

## Estado actual del proyecto

A la fecha de este documento, el sistema ya tiene piezas importantes implementadas.

### Ya implementado

#### A. Sincronización/exportación de identidad local

Servicio principal:

- `app/Services/LegacyAlumnoUserSyncService.php`

Comportamiento:

- crea o actualiza usuarios por `documento`,
- asigna rol `ALUMNO`,
- conserva email existente si ya lo había,
- genera email técnico `alumno-{documento}@consultor.invalid` cuando hace falta,
- deja contraseña local aleatoria para cuentas creadas por exportación.

#### B. Login local por correo o documento

Pantalla y lógica:

- `resources/views/livewire/pages/auth/login.blade.php`
- `app/Livewire/Forms/LoginForm.php`

Comportamiento:

- el mismo campo acepta `correo o documento`,
- el usuario puede entrar con contraseña local usando cualquiera de esos dos identificadores.

#### C. Login legacy por `documento + PIN`

Lógica:

- `app/Livewire/Forms/LegacyAlumnoLoginForm.php`

Comportamiento:

- valida contra la fuente legacy,
- crea o reutiliza la cuenta local por `documento`,
- asigna rol `ALUMNO`,
- redirige a un paso explícito de completar cuenta cuando el email sigue siendo técnico,
- permite seguir operando como puente de acceso mientras la cuenta se termina de activar.

#### D. Flujo explícito de completar cuenta después del login legacy

Pantalla y protección:

- `resources/views/livewire/pages/auth/complete-legacy-account.blade.php`
- `app/Http/Middleware/EnsureLegacyUserHasCompletedAccount.php`

Comportamiento:

- si el alumno entra con `documento + PIN` y todavía tiene email técnico `@consultor.invalid`, no entra directo al portal,
- se le muestra una pantalla autenticada para completar su cuenta,
- en ese paso confirma nombre, informa correo real y fija su contraseña local,
- mientras la cuenta siga incompleta, el middleware lo redirige a ese paso antes de entrar al perfil o al portal del alumno.

#### E. Claim o activación de cuenta exportada

Pantalla y lógica:

- `resources/views/livewire/pages/auth/register.blade.php`

Comportamiento:

- si la cuenta no existe, crea un usuario nuevo,
- si ya existe una cuenta exportada con email técnico y mismo `documento`, la reclama,
- guarda email real,
- fija contraseña local,
- evita duplicar identidad.

#### F. Recuperación de contraseña por correo o documento

Pantallas:

- `resources/views/livewire/pages/auth/forgot-password.blade.php`
- `resources/views/livewire/pages/auth/reset-password.blade.php`

Comportamiento:

- acepta `correo o documento`,
- resuelve la cuenta por `documento` cuando corresponde,
- solo permite recuperación si la cuenta ya tiene un correo real recuperable,
- bloquea el reset si la cuenta sigue con email técnico o temporal.

#### G. Vinculación con Google OAuth

Lógica principal:

- `app/Http/Controllers/Auth/OAuthController.php`
- `resources/views/livewire/pages/auth/link-documento.blade.php`
- `resources/views/profile.blade.php`

Comportamiento:

- permite ingresar con Google,
- permite vincular una cuenta existente con Google de forma explícita,
- obliga a enlazar `documento` si Google autentica primero,
- fusiona correctamente la cuenta OAuth con la cuenta local del alumno cuando corresponde,
- muestra un CTA explícito `Vincular Google` en el perfil cuando la cuenta todavía no está vinculada,
- muestra un estado visible de `Cuenta vinculada con Google` en el perfil cuando la vinculación ya existe,
- después de completar la cuenta legacy, redirige al perfil para que el usuario pueda vincular Google sin volver manualmente al login.

Estado del frente:

- el frente OAuth ya quedó estabilizado,
- el merge entre cuenta OAuth temporal y cuenta local existente conserva correctamente `auth_provider` y `auth_provider_id`,
- la batería `vendor/bin/sail artisan test --compact --filter=OAuth` ya pasa completa.

## Recorrido objetivo del usuario

### Escenario principal recomendado

1. La exportación crea la cuenta local base por `documento`.
2. El alumno entra con `documento + PIN` del consultor viejo.
3. Si la cuenta sigue incompleta, la aplicación lo redirige al paso explícito de completar cuenta.
4. Luego el alumno reclama o completa su cuenta local:
   - confirma su `documento`,
   - informa su email real,
   - fija su contraseña local.
5. Después de guardar, la aplicación lo lleva al perfil y le muestra el CTA para vincular Google.
6. Desde ese momento puede entrar con:
   - `documento + contraseña local`, o
   - `correo + contraseña local`.
7. Luego vincula Google sobre esa misma cuenta.
8. A partir de ahí puede entrar también con Google OAuth sin perder la relación con su `documento`.

## Política funcional deseada

### Qué debe pasar

- una cuenta exportada puede transformarse en cuenta local completa sin duplicarse,
- el usuario puede identificar su cuenta por `documento` en login y recuperación,
- el acceso legacy por `documento + PIN` sigue disponible como puente,
- después del login legacy, el sistema obliga a completar cuenta cuando todavía falta email real y contraseña local,
- Google OAuth se vincula a la identidad local ya existente,
- el perfil expone un CTA directo para vincular Google cuando todavía no está asociado,
- el perfil muestra de forma visible cuando la cuenta ya quedó vinculada con Google,
- el email real reemplaza al email técnico cuando el usuario completa su cuenta.

### Qué no debe pasar

- no se debe copiar el `PIN` legacy al campo `password`,
- no se debe crear otra cuenta local para un `documento` ya existente,
- no se debe permitir recuperación de contraseña sobre emails técnicos `@consultor.invalid`,
- no se debe dejar a Google crear una identidad paralela sin resolver el `documento` del alumno.

## Decisión explícita de transición

La transición elegida para este proyecto es:

- mantener `documento + PIN` como puente de continuidad con el sistema viejo,
- usar la exportación para preparar identidad local,
- usar el registro/claim para transformar esa identidad en cuenta local completa,
- usar OAuth de Google como método adicional de acceso sobre esa misma cuenta.

Esta decisión evita una migración riesgosa de credenciales legacy al hash local de Laravel y mantiene la trazabilidad de la identidad por `documento`.

## Tareas que este documento deja habilitadas

A partir de esta estrategia, las siguientes tareas tienen sentido y quedan alineadas:

- endurecer la exportación para marcar cuentas `pendientes de activación`,
- mejorar mensajes y onboarding para alumnos que llegan desde el consultor viejo,
- reforzar la vinculación de Google para cuentas exportadas o reclamadas,
- definir en qué momento el acceso exclusivamente legacy podrá dejar de ser obligatorio.

## Resumen

La sincronización legacy prepara la identidad.

El login por `documento + PIN` mantiene la continuidad operativa.

El flujo explícito de completar cuenta ya quedó implementado para transformar el acceso legacy en una cuenta local real.

El registro/claim convierte una cuenta exportada en una cuenta local real.

El login local por correo o documento da acceso moderno sin romper la llave principal `documento`.

La vinculación con Google OAuth se monta encima de esa misma identidad y no como una cuenta separada.

El perfil ya muestra un CTA explícito para vincular Google y el redirect después de completar cuenta ya lleva al usuario a ese punto de continuación.

Además, cuando la cuenta ya quedó vinculada, el perfil muestra ese estado de forma visible y el frente OAuth ya quedó estabilizado con sus pruebas en verde.
