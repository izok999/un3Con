# Estrategia de acceso legacy + cuenta local + OAuth

Fecha original: 2026-05-05
Última actualización: 2026-07-27

## Propósito de este documento

Este documento complementa a:

- `resources/docs/propuesta-sync-usuarios-legacy-2026-04-22.md`
- `resources/docs/cambios-profile-2026-04-22.md`

La propuesta de sincronización legacy define cómo preparar la tabla `users` con identidad local mínima.
Los cambios de `/profile` corrigen problemas de UI y testing.

Ninguno de esos documentos describe de punta a punta el objetivo funcional que hoy se busca para autenticación.

## Cambios desde la versión original

La versión del 2026-05-05 quedó desactualizada en varios puntos. Lo que cambió:

- el login legacy dejó de ser una pantalla aparte y pasó a ser un fallback del formulario único de login,
- vincular Google ahora exige `documento + PIN` legacy, no solo declarar el documento,
- se agregó el middleware `oauth.documento`, que fuerza el enlace en todas las rutas autenticadas,
- apareció un segundo email placeholder, `@pending.invalid`, que la fusión de cuentas usa para liberar el correo del usuario OAuth temporal,
- el registro público quedó deshabilitado: permitía declarar una cédula ajena sin probar su pertenencia,
- el callback de Google y el post-enlace terminan en `dashboard`, no en el perfil.

## Objetivo final

La aplicación debe soportar este recorrido de transición:

- el usuario entra con su acceso legacy usando `documento + PIN`,
- mantiene `documento` como identificador principal e inmutable,
- completa su cuenta local con email real y contraseña local,
- luego agrega o confirma su cuenta de Google para OAuth,
- y finalmente puede usar la misma identidad local tanto con login tradicional como con Google.

En términos prácticos, el objetivo final es:

> entra con su usuario y contraseña legado, y luego agrega o confirma su correo de Google para OAuth.

Con la decisión actual del proyecto, el puente operativo inmediato no es migrar el `PIN` viejo a `password` local, sino:

- mantener el login legacy por `documento + PIN`,
- usar el paso de completar cuenta para que el usuario fije su contraseña local,
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
- toda vinculación OAuth debe terminar asociada al mismo `documento`,
- nunca se debe crear una segunda cuenta local para la misma persona si ya existe una con ese `documento`.

En la base local, `documento` es `unique` y `nullable`:

- `database/migrations/2026_04_17_145201_add_documento_and_oauth_to_users_table.php`
- `database/migrations/2026_04_28_120000_make_documento_nullable_on_users_table.php`

Es nullable porque una cuenta que entra primero por Google todavía no tiene documento resuelto.

### 2. Nadie declara un documento sin probar que le pertenece

Este principio es el que faltaba en la versión original y el que ordena los cambios recientes.

La única prueba de pertenencia disponible es el `PIN` del consultor anterior, verificado contra
`sh_movimientos.fn_consultor_verificacion_pin_web2` más la existencia del alumno en la base académica.

Por lo tanto, todo camino que asocie un `documento` a una cuenta pasa por esa verificación:

- login legacy por `documento + PIN`,
- vinculación de documento después de entrar con Google.

No hay un tercer camino. El registro público, que era ese tercer camino sin verificación, quedó deshabilitado.

### 3. La base externa sigue siendo la fuente de verdad académica

La nueva app no replica carreras, extracto, materias o deudas al PostgreSQL local.

La base local guarda identidad de acceso.
La base externa sigue resolviendo los datos académicos.

Esto es también lo que hace crítico al principio anterior: quien ocupa un `documento` ve los datos
académicos del titular, porque toda consulta del portal se resuelve por documento contra la base externa.

### 4. El acceso legacy y el acceso local son etapas distintas

No se mezcla el `PIN` legacy con el `password` local de Laravel.

El `PIN` sirve como puente de transición y como prueba de pertenencia del documento.
La contraseña local se fija después, cuando el usuario completa su cuenta.

### 5. Google OAuth se vincula a una cuenta existente, no a una identidad paralela

Google no debe crear una segunda identidad funcional para el mismo alumno.

Si ya existe un usuario local por `documento`, el flujo correcto es fusionar Google en esa cuenta.

## Emails placeholder

Hay dos placeholders, y ambos significan "esta cuenta todavía no tiene correo real recuperable":

| Placeholder | Quién lo genera | Qué implica |
| --- | --- | --- |
| `alumno-{documento}@consultor.invalid` | exportación masiva y login legacy | falta completar cuenta; el middleware `legacy.account.complete` bloquea el resto del sitio |
| `oauth-link-{id}@pending.invalid` | fusión en `link-documento`, al liberar el correo del usuario OAuth temporal | fila residual de una fusión; no habilita recuperación de contraseña |

Los dos están bloqueados para recuperación de contraseña en `forgot-password` y `reset-password`.

## Estado actual del proyecto

### A. Sincronización/exportación de identidad local

Servicio principal:

- `app/Services/LegacyAlumnoUserSyncService.php`

Comportamiento:

- `updateOrCreate` por `documento`,
- asigna rol `ALUMNO`,
- conserva el email existente si ya lo había,
- genera email técnico `alumno-{documento}@consultor.invalid` cuando hace falta,
- deja contraseña local aleatoria solo en las cuentas que crea,
- cuenta como conflicto y saltea los documentos con `duplicate_count > 1`,
- cuenta como conflicto cuando el email calculado ya pertenece a otro documento,
- soporta `dry_run`, `solo_faltantes`, `chunk` y filtros por carrera, sede, unidad y periodo.

### B. Login unificado: correo o documento, contraseña o PIN

Pantalla y lógica:

- `resources/views/livewire/pages/auth/login.blade.php`
- `app/Livewire/Forms/LoginForm.php`
- `app/Livewire/Forms/LegacyAlumnoLoginForm.php`

Hay un solo formulario, con dos campos: `Correo o documento` y `Contraseña o PIN`.
El orden de resolución es:

1. intenta autenticación local con password, aceptando correo o documento como identificador,
2. si el identificador es un correo y falló, corta con error, sin tocar el legacy,
3. si el identificador no es un correo, intenta el legacy con `documento + PIN`.

El intento legacy valida contra la fuente legacy, hace `firstOrCreate` por `documento`, asigna rol `ALUMNO`
y tiene rate limit propio de 5 intentos por `documento + IP`. Los errores del formulario legacy se remapean
a los campos visibles del formulario único.

Después del login legacy:

- si el email sigue siendo técnico, redirige a completar cuenta,
- si no, redirige a `dashboard`.

### C. Completar cuenta después del login legacy

Pantalla y protección:

- `resources/views/livewire/pages/auth/complete-legacy-account.blade.php`
- `app/Http/Middleware/EnsureLegacyUserHasCompletedAccount.php`, alias `legacy.account.complete`

Comportamiento:

- el middleware actúa sobre cualquier usuario con `documento` presente y email `@consultor.invalid`,
- lo redirige a completar cuenta antes de dejarlo entrar a cualquier otra ruta protegida,
- en ese paso confirma nombre, informa correo real y fija su contraseña local, con el documento en solo lectura,
- deja `email_verified_at` en `null` a propósito,
- al guardar, redirige a la ruta previa o, por defecto, al perfil, con un aviso de que ya puede vincular Google.

### D. Registro público deshabilitado

Estado desde 2026-07-27.

Antes existía `resources/views/livewire/pages/auth/register.blade.php`, que pedía nombre, documento, email y
contraseña. No verificaba nada contra la base legacy, y eso abría dos caminos de abuso:

- si el documento ya existía en `users` con email técnico, el registro lo reclamaba: bastaba conocer una
  cédula para fijar correo y contraseña propios sobre la cuenta del alumno real,
- si el documento no existía todavía, creaba una cuenta con esa cédula y rol `ALUMNO`, con acceso a los datos
  académicos del titular; y cuando el alumno real entraba con su PIN, el `firstOrCreate` del login legacy lo
  metía dentro de la cuenta del ocupante.

Hoy:

- la vista fue eliminada,
- la ruta `register` se conserva pero solo redirige al login con la explicación de primer ingreso, para que los
  enlaces viejos no den 404 (`routes/auth.php`),
- los CTA de registro salieron de `welcome.blade.php` y del login,
- el alta de alumnos ocurre en el login con `documento + PIN`,
- las cuentas de `ADMIN`, `ADMIN_UNIDAD_ACADEMICA` y `FUNCIONARIO` se crean por `database/seeders/RoleSeeder.php`,
- `tests/Feature/Auth/RegistrationDisabledTest.php` cubre la redirección y actúa como guarda para que la
  pantalla sin verificación no vuelva a introducirse.

### E. Recuperación de contraseña por correo o documento

Pantallas:

- `resources/views/livewire/pages/auth/forgot-password.blade.php`
- `resources/views/livewire/pages/auth/reset-password.blade.php`

Comportamiento:

- aceptan `correo o documento`,
- resuelven la cuenta por `documento` cuando corresponde,
- solo permiten recuperación si la cuenta ya tiene un correo real,
- bloquean el reset cuando el email es `@consultor.invalid` o `@pending.invalid`, y sugieren entrar con
  documento y PIN o vincular Google.

### F. Google OAuth

Lógica principal:

- `app/Http/Controllers/Auth/OAuthController.php`
- `resources/views/livewire/pages/auth/link-documento.blade.php`
- `app/Http/Middleware/EnsureOAuthUserHasDocumento.php`, alias `oauth.documento`
- `resources/views/profile.blade.php`

Hay dos puntos de entrada:

- `auth.google.redirect`, para invitados, desde el login,
- `auth.google.link-existing`, para usuarios autenticados, desde el CTA del perfil, que marca la intención en sesión.

El callback:

- busca al usuario por `auth_provider` + `auth_provider_id`,
- si otro usuario ya tiene ese correo, resuelve el conflicto: fusiona cuando el correo pertenece a una cuenta
  sin proveedor o con el mismo proveedor e id, y rechaza en cualquier otro caso, indicando entrar primero con
  la cuenta local y vincular Google desde el perfil,
- si no encuentra nada, crea un usuario con `password` en `null`,
- asigna rol `ALUMNO` cuando el usuario no tiene ningún rol,
- si el usuario no tiene `documento`, redirige a `link-documento`; si lo tiene, va a `dashboard`.

`link-documento` es el único punto que asocia un documento a una cuenta OAuth, y **exige `documento + PIN`**:

- verifica el PIN contra el consultor anterior y la existencia del alumno en la base académica,
- tiene rate limit propio, con clave `link-documento|documento|IP`,
- si el documento no pertenece a ninguna cuenta, lo escribe en la cuenta actual,
- si el documento ya pertenece a otra cuenta local, fusiona hacia esa cuenta: le pasa `auth_provider`,
  `auth_provider_id` y avatar, y adopta el correo de Google si el de la cuenta existente era técnico,
- para liberar los índices únicos, renombra el correo del usuario OAuth temporal a `oauth-link-{id}@pending.invalid`
  y le borra el proveedor; después de reautenticar, elimina esa fila,
- rechaza cuando el documento ya está vinculado a otra cuenta con otro proveedor, y cuando el correo de Google
  ya está en uso por un tercero,
- al terminar, redirige a `dashboard`.

El middleware `oauth.documento` sostiene la regla en todo el sitio: cualquier usuario con proveedor y sin
documento vuelve a `link-documento`. Está aplicado en `dashboard`, `profile`, `normativas` y todo el portal del
alumno (`routes/web.php`).

En el perfil:

- si la cuenta no tiene proveedor, muestra el CTA `Vincular Google`,
- si ya está vinculada, muestra el estado `Cuenta vinculada con Google` con el correo enlazado,
- no hay opción de desvincular.

El registro con Google nunca se habilitó: el botón quedó comentado en la vista de registro y esa vista ya no existe.

## Recorrido objetivo del usuario

### Alumno que viene del consultor anterior

1. La exportación crea la cuenta local base por `documento`, con email técnico.
2. El alumno entra en el login con su `documento` y su `PIN`.
3. Como el email sigue siendo técnico, el sistema lo lleva a completar cuenta.
4. Ahí confirma nombre, informa su correo real y fija su contraseña local.
5. Al guardar, cae en el perfil, donde ve el CTA para vincular Google.
6. Desde ese momento puede entrar con `documento + contraseña local` o `correo + contraseña local`.
7. Si vincula Google, puede entrar también por OAuth sobre la misma cuenta.

Si la cuenta todavía no fue exportada, el paso 2 la crea igual: el login legacy hace `firstOrCreate` por documento.

### Alumno que entra primero con Google

1. Entra con Google desde el login.
2. Como no tiene `documento`, el callback lo lleva a `link-documento`.
3. Ahí prueba la pertenencia del documento con su `PIN` del consultor anterior.
4. Si ya existía una cuenta local para ese documento, las dos se fusionan en una sola.
5. Termina en `dashboard`, con una única identidad por documento.

## Política funcional

### Qué debe pasar

- ningún camino asocia un `documento` a una cuenta sin verificar el `PIN` legacy,
- una cuenta exportada puede transformarse en cuenta local completa sin duplicarse,
- el usuario puede identificar su cuenta por `documento` en login y recuperación,
- el acceso legacy por `documento + PIN` sigue disponible como puente y como alta,
- después del login legacy, el sistema obliga a completar cuenta cuando falta email real y contraseña local,
- Google OAuth se vincula a la identidad local ya existente,
- el perfil expone el estado de vinculación y el CTA cuando todavía no está asociado,
- el email real reemplaza al placeholder cuando el usuario completa su cuenta.

### Qué no debe pasar

- no se debe copiar el `PIN` legacy al campo `password`,
- no se debe crear otra cuenta local para un `documento` ya existente,
- no se debe permitir que alguien declare un `documento` sin probar que le pertenece,
- no se debe permitir recuperación de contraseña sobre emails `@consultor.invalid` ni `@pending.invalid`,
- no se debe dejar a Google crear una identidad paralela sin resolver el `documento` del alumno.

## Brechas abiertas

1. **Cuentas creadas por el registro viejo, sin auditar.** Cerrar el registro no revisa lo que entró antes. Si
   en producción hay cuentas creadas por esa vía, el `firstOrCreate` de `LegacyAlumnoLoginForm` todavía puede
   meter al alumno real dentro de la cuenta de un ocupante. Una cuenta registrada y una que completó el flujo
   legacy son casi indistinguibles en `users`; lo más detectable es cruzar los `documento` locales contra
   `resolverAlumno()` y revisar los que no existen en la base académica.
2. **El middleware `verified` del dashboard no hace nada.** `User` no implementa `MustVerifyEmail` (está
   comentado en `app/Models/User.php`), así que `EnsureEmailIsVerified` pasa de largo. Y completar cuenta deja
   `email_verified_at` en `null` a propósito. Hay que decidir si la verificación de correo se activa o si el
   middleware se saca para no dar una garantía falsa.
3. **La fusión de `link-documento` no es atómica de punta a punta.** La liberación del correo ocurre dentro de
   la transacción, pero el borrado del usuario OAuth temporal ocurre después de reautenticar. Un fallo en medio
   deja una fila con email `@pending.invalid` y sin proveedor.
4. **No hay desvinculación de Google.** El perfil muestra el estado pero no permite revertirlo.
5. **Alumno sin PIN activo en el consultor anterior no tiene vía de alta.** Es la consecuencia aceptada de
   deshabilitar el registro; hoy tampoco podría usar el portal, porque todo se resuelve por documento.
6. **`PasswordResetTest::test_reset_password_link_screen_can_be_rendered` falla.** Espera el literal
   `Correo o documento` pero el locale de tests es `en` y la clave está traducida. Es una falla del test, no del flujo.

## Tareas que este documento deja habilitadas

- auditar los documentos locales contra la base académica (brecha 1),
- resolver la verificación de correo, activándola o quitando el middleware (brecha 2),
- decidir en qué momento el acceso legacy podrá dejar de ser obligatorio, y qué pasa con las cuentas que nunca
  completaron el paso,
- endurecer la exportación para marcar cuentas `pendientes de activación`,
- mejorar mensajes y onboarding para alumnos que llegan desde el consultor viejo.

## Resumen

La sincronización legacy prepara la identidad local por `documento`.

El `PIN` del consultor anterior es la única prueba de pertenencia del documento, y hoy los dos únicos caminos
que asocian un documento a una cuenta lo exigen: el login legacy y `link-documento`.

El login es un formulario único que acepta correo o documento, con contraseña local o PIN, y cae al legacy solo
cuando el identificador no es un correo.

El paso de completar cuenta convierte el acceso legacy en una cuenta local real, con correo y contraseña propios.

El registro público quedó deshabilitado porque era el camino que permitía declarar una cédula ajena.

Google OAuth se monta encima de esa misma identidad: el callback resuelve la cuenta, el middleware
`oauth.documento` obliga a enlazar el documento, y la fusión conserva `auth_provider` y `auth_provider_id`.
