# Persistencia de tema y redirección post-login

Fecha: 2026-05-19

## Propósito

Este documento resume las características usadas para resolver dos comportamientos del portal:

- que el tema elegido se mantenga entre todas las vistas,
- y que el flujo post-login tenga un destino consistente, respetando los pasos obligatorios de activación cuando todavía faltan datos de cuenta.

Complementa a `resources/docs/tema-une.md`.

## Problemas que se resolvieron

### 1. Persistencia de tema entre vistas

Antes, el tema dependía solamente de `localStorage`.

Eso tenía una limitación clara:

- el navegador recordaba la preferencia,
- pero el servidor seguía renderizando el HTML inicial con `data-theme="uneTheme"`,
- y recién después el JavaScript corregía el tema real.

En la práctica, eso provocaba que la preferencia no naciera desde la request y no quedara realmente centralizada para todas las vistas del sistema.

### 2. Redirección inconsistente después del login

Los flujos de autenticación tenían redirects distintos según el origen:

- login tradicional,
- login legacy por `documento + PIN`,
- callback de Google OAuth,
- vinculación de `documento` después de OAuth.

La decisión funcional adoptada fue simplificar esos redirects y usar `dashboard` como destino normal cuando el acceso ya está listo.

Sin embargo, se mantienen excepciones obligatorias cuando el usuario todavía no completó información mínima de su cuenta:

- primer login legacy con email técnico `@consultor.invalid`: debe pasar por `auth.legacy.complete-account`,
- login con Google sin `documento`: debe pasar por `auth.oauth.link-documento`.

## Características usadas

## 1. Persistencia híbrida del tema

La implementación usa dos mecanismos al mismo tiempo:

- cookie `une-theme`,
- `localStorage` con la misma clave lógica.

### Por qué se usan ambos

La cookie resuelve el render del lado del servidor.
Gracias a eso, los layouts pueden imprimir `<html data-theme="...">` con el tema correcto desde la respuesta inicial.

`localStorage` sigue siendo útil para:

- conservar la preferencia del lado del cliente,
- reaccionar inmediatamente al toggle,
- y rehidratar el estado visual sin recargar lógica adicional del backend.

## 2. Validación explícita de temas permitidos

Solo se aceptan estos valores:

- `uneTheme`
- `uneThemeDark`

La validación se aplica tanto en el JavaScript como en Blade antes de usar el valor de la cookie.

Esto evita que un valor inválido termine escrito en `data-theme` y rompa la consistencia visual.

## 3. Render del tema desde los layouts base

Los layouts leen la cookie en la request y deciden el tema inicial del documento.

Layouts cubiertos:

- `resources/views/layouts/app.blade.php`
- `resources/views/layouts/guest.blade.php`
- `resources/views/welcome.blade.php`

Con esto, la preferencia aplica tanto a:

- vistas autenticadas,
- vistas guest,
- y la pantalla de bienvenida.

## 4. Script temprano en `<head>` para evitar desajuste visual

Además del render del lado del servidor, se mantiene un script inline temprano en el `<head>`.

Su objetivo es:

- leer cookie o `localStorage`,
- aplicar el tema antes del primer render visual,
- y reescribir la cookie si el valor válido existe del lado del cliente.

Esto reduce el flash del tema incorrecto al navegar o recargar la página.

## 5. Delegación de eventos para el toggle del tema

El cambio de tema se detecta con delegación de eventos sobre `document` usando `id="theme-toggle"`.

Eso permite que el comportamiento sobreviva a:

- re-renders,
- navegación Livewire,
- cambios de layout parciales.

No hace falta rebind manual del listener cada vez que cambia el DOM.

## 6. Sincronización del checkbox después de navegación Livewire

Se usa una función de sincronización que vuelve a marcar o desmarcar el toggle según el `data-theme` actual.

Se ejecuta en:

- `DOMContentLoaded`
- `livewire:navigated`

Así el control visual no queda desalineado respecto al tema activo.

## 7. Redirección normalizada con pasos obligatorios previos

Se normalizó el destino final de autenticación para que el usuario vaya a `dashboard` cuando el acceso ya está resuelto.

Se aplicó a estos flujos:

- login estándar,
- login legacy cuando la cuenta ya está completa,
- callback de Google OAuth cuando el usuario ya tiene `documento`,
- vinculación de `documento` posterior al login con Google.

### Excepciones funcionales deliberadas

Si el usuario entra por legacy y todavía tiene el email técnico `@consultor.invalid`, primero se mantiene el paso obligatorio de creación o completado de cuenta:

- ruta: `auth.legacy.complete-account`

Solo después de crear su cuenta local y definir su correo real y contraseña puede seguir al portal.

Si el usuario entra con Google y todavía no tiene `documento`, primero se mantiene el paso obligatorio de vinculación:

- ruta: `auth.oauth.link-documento`

Solo después de completar esa vinculación se lo envía a `dashboard`.

## Archivos principales involucrados

### Tema

- `resources/js/app.js`
- `resources/views/layouts/app.blade.php`
- `resources/views/layouts/guest.blade.php`
- `resources/views/welcome.blade.php`

### Login y OAuth

- `resources/views/livewire/pages/auth/login.blade.php`
- `resources/views/livewire/pages/auth/link-documento.blade.php`
- `app/Http/Controllers/Auth/OAuthController.php`

### Tests

- `tests/Feature/ThemePreferenceTest.php`
- `tests/Feature/Auth/AuthenticationTest.php`
- `tests/Feature/Auth/OAuthAuthenticationTest.php`

## Comportamiento final esperado

### Tema

- el usuario cambia el toggle una sola vez,
- la preferencia queda persistida,
- cualquier layout base arranca con el tema correcto,
- el toggle conserva su estado al navegar por Livewire o recargar.

### Login

- login tradicional exitoso: `dashboard`
- primer login legacy o login legacy con cuenta todavía incompleta: `auth.legacy.complete-account`
- login legacy con cuenta completa: `dashboard`
- Google OAuth con `documento` ya resuelto: `dashboard`
- Google OAuth sin `documento`: `auth.oauth.link-documento`, luego `dashboard`

## Notas de mantenimiento

- Si se agrega un tercer tema, hay que actualizar la lista de temas permitidos tanto en Blade como en JavaScript.
- Si se crea un layout nuevo fuera de los layouts base, debe heredar esta misma lógica o reutilizarla explícitamente.
- Si en algún punto se quiere persistir el tema en base de datos por usuario, la cookie puede seguir existiendo como caché de presentación, pero la fuente de verdad debería definirse con claridad.