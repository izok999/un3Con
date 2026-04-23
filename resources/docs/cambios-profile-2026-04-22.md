# Cambios aplicados en `/profile`

Fecha: 2026-04-22

## Problema reportado

Al entrar directamente a `http://localhost/profile`, la vista quedaba trabada en la opción de borrar perfil.

La causa principal era un choque entre componentes Blade:

- El formulario de borrado usaba `x-modal`.
- En este proyecto, `x-modal` resolvía al componente de MaryUI, no al modal local estilo Breeze.
- Ese componente de MaryUI espera `wire:model`.
- Como el formulario no lo enviaba, se generaba una expresión Alpine inválida: `$wire. = false`.

## Cambios realizados

### 1. Modal de borrado de cuenta

Se reemplazó el uso del modal conflictivo por un modal local con nombre propio para evitar colisiones con MaryUI.

Archivos involucrados:

- `resources/views/livewire/profile/delete-user-form.blade.php`
- `resources/views/components/dialog-modal.blade.php`

Resultado:

- La pantalla de perfil ya no entra en un estado roto al abrirse directamente.
- El modal de eliminación queda controlado por el componente local esperado.

### 2. Cobertura de prueba para el perfil

Se agregó una verificación en la prueba de perfil para asegurar que la página no renderice la cadena inválida `$wire. = false`.

Archivo involucrado:

- `tests/Feature/ProfileTest.php`

### 3. Ajuste en la factory de usuarios

Durante la validación apareció un problema independiente: la columna `documento` es obligatoria en `users`, pero la factory no la estaba completando.

Se corrigió la factory para generar un `documento` único.

Archivo involucrado:

- `database/factories/UserFactory.php`

### 4. Alias Blade para componentes Mary usados por el proyecto

Durante los tests también apareció otra incompatibilidad del proyecto: varias vistas usan aliases `x-mary-*`, pero la configuración actual de Mary trabaja sin ese prefijo.

Para evitar romper esas vistas, se agregaron wrappers Blade compatibles para los componentes que estaban faltando.

Archivos involucrados:

- `resources/views/components/mary-alert.blade.php`
- `resources/views/components/mary-badge.blade.php`
- `resources/views/components/mary-stat.blade.php`

## Validación realizada

Se ejecutó:

```bash
vendor/bin/sail artisan test --compact tests/Feature/ProfileTest.php
vendor/bin/sail bin pint --dirty --format agent
```

Resultado:

- `5` tests aprobados
- `23` assertions
- Pint sin cambios pendientes

## Resumen

El problema original de `/profile` quedó corregido evitando la colisión entre el modal de Breeze y el modal de MaryUI. Además, se dejaron resueltos dos bloqueos laterales del proyecto que impedían validar correctamente la pantalla: la factory de usuarios sin `documento` y los aliases `x-mary-*` no resueltos.