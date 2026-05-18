# Sistema de Traducciones — un3Con

Documentación del sistema de internacionalización (i18n) implementado en este proyecto.

---

## Índice

1. [Enfoque general](#enfoque-general)
2. [Archivos de traducción](#archivos-de-traducción)
3. [Cómo agregar textos traducibles](#cómo-agregar-textos-traducibles)
4. [Cómo agregar un nuevo idioma](#cómo-agregar-un-nuevo-idioma)
5. [Selector de idioma en la UI](#selector-de-idioma-en-la-ui)
6. [Persistencia del idioma](#persistencia-del-idioma)
7. [Middleware SetLocale](#middleware-setlocale)
8. [Ruta de cambio de idioma](#ruta-de-cambio-de-idioma)
9. [Idiomas soportados](#idiomas-soportados)
10. [Flujo completo](#flujo-completo)

---

## Enfoque general

El sistema usa **JSON translation files** de Laravel (`lang/*.json`).  
Cada clave es el texto en español (idioma base), y el valor es su traducción.  
En las vistas Blade se usa el helper `__()` para envolver cada texto visible.

```blade
{{-- Antes --}}
<h1>Iniciar sesión</h1>

{{-- Después --}}
<h1>{{ __('Iniciar sesión') }}</h1>
```

Laravel busca automáticamente la traducción en el archivo del locale activo.  
Si no encuentra la clave, muestra el texto original (el español), por lo que **el idioma base nunca queda en blanco**.

---

## Archivos de traducción

Los archivos viven en `lang/` en la raíz del proyecto:

```
lang/
├── es.json   ← Español (base — las claves y los valores son iguales)
├── en.json   ← Inglés
├── pt.json   ← Portugués
└── gn.json   ← Guaraní
```

### Estructura de un archivo

```json
{
    "Iniciar sesión": "Log in",
    "Correo o documento": "Email or document",
    "Acceder": "Sign in",
    "Bienvenido, :name": "Welcome, :name"
}
```

**Reglas:**
- La **clave** es siempre el texto en español tal como aparece en la vista.
- El **valor** es la traducción en el idioma del archivo.
- En `es.json` clave y valor son idénticos (identidad).
- Los parámetros dinámicos usan la sintaxis `:variable` (ej. `:name`).

### Traducciones con parámetros

```blade
{{-- Vista --}}
{{ __('Bienvenido, :name', ['name' => auth()->user()->name]) }}

{{-- es.json --}}
"Bienvenido, :name": "Bienvenido, :name"

{{-- en.json --}}
"Bienvenido, :name": "Welcome, :name"
```

Laravel reemplaza `:name` con el valor real en tiempo de ejecución.

---

## Cómo agregar textos traducibles

1. Localizar el texto hardcodeado en la vista Blade.
2. Envolverlo con `__()`.
3. Agregar la clave al `es.json` (clave = valor).
4. Agregar la traducción correspondiente en `en.json`, `pt.json` y `gn.json`.

```blade
{{-- Antes --}}
<button>Ver detalles</button>

{{-- Después --}}
<button>{{ __('Ver detalles') }}</button>
```

```json
// es.json
{ "Ver detalles": "Ver detalles" }

// en.json
{ "Ver detalles": "View details" }

// pt.json
{ "Ver detalles": "Ver detalhes" }

// gn.json
{ "Ver detalles": "Ehecha mba'e" }
```

> **Tip:** Mantener los archivos JSON sincronizados. Si se agrega una clave en `es.json`, agregarla en todos los demás idiomas para evitar que se muestre la clave cruda.

---

## Cómo agregar un nuevo idioma

1. **Crear el archivo** `lang/{codigo}.json` con todas las claves de `es.json` y sus traducciones.

2. **Agregar el locale** a la lista de soportados en tres lugares:

   **`app/Http/Middleware/SetLocale.php`**
   ```php
   protected const array SUPPORTED_LOCALES = ['es', 'en', 'pt', 'gn', 'nuevo'];
   ```

   **`routes/web.php`** — ruta `/locale`
   ```php
   $supported = ['es', 'en', 'pt', 'gn', 'nuevo'];
   ```

   **`resources/views/components/locale-switcher.blade.php`**
   ```php
   $locales = [
       // ... existentes
       'nuevo' => ['flag' => '🏳️', 'label' => 'XX'],
   ];
   ```

3. Listo. El selector aparecerá automáticamente en la UI.

---

## Selector de idioma en la UI

Componente Blade reutilizable en `resources/views/components/locale-switcher.blade.php`.

Se incluye como `<x-locale-switcher />` en:
- **`resources/views/layouts/app.blade.php`** — topbar del dashboard (junto al toggle de tema)
- **`resources/views/layouts/guest.blade.php`** — esquina superior derecha del login

El componente renderiza un dropdown DaisyUI con bandera y código de cada idioma.  
El idioma activo se marca con ✓ y texto en negrita.  
Cada opción envía un `POST /locale` con el código seleccionado.

---

## Persistencia del idioma

El idioma elegido se persiste en **dos niveles**:

| Nivel | Dónde | Aplica a |
|-------|-------|----------|
| Sesión PHP | `session('locale')` | Guests y usuarios autenticados |
| Base de datos | columna `users.locale` | Solo usuarios autenticados |

Cuando un usuario autenticado cambia de idioma, se actualiza tanto la sesión como la columna en DB.  
En el próximo login (desde cualquier dispositivo o navegador), el middleware leerá `user->locale` y aplicará el idioma guardado.

### Migración

```php
// database/migrations/2026_05_18_105754_add_locale_to_users_table.php
Schema::table('users', function (Blueprint $table): void {
    $table->string('locale', 5)->default('es')->after('avatar');
});
```

---

## Middleware SetLocale

**`app/Http/Middleware/SetLocale.php`**

Se ejecuta en cada request del stack web. Determina el locale en este orden de prioridad:

1. `auth()->user()->locale` — preferencia guardada en DB (solo usuarios autenticados)
2. `session('locale')` — selección de la sesión actual (guests o sesión activa)
3. `config('app.locale')` — valor por defecto del `.env` (`APP_LOCALE=es`)

```php
$locale = $request->user()?->locale
    ?? session('locale', config('app.locale'));

App::setLocale($locale);
```

El middleware está registrado globalmente en el stack web desde `bootstrap/app.php`:

```php
$middleware->web(append: [
    SetLocale::class,
]);
```

---

## Ruta de cambio de idioma

```
POST /locale
```

- **Nombre:** `locale.switch`
- **Acceso:** público (sin middleware `auth`) para que funcione también en la pantalla de login
- **Parámetro:** `locale` — código del idioma seleccionado

**Comportamiento:**
1. Valida que el locale esté en la lista de soportados
2. Guarda en sesión (`session(['locale' => $locale])`)
3. Si hay usuario autenticado, actualiza `users.locale` en DB
4. Redirige de vuelta a la página anterior (`back()`)

---

## Idiomas soportados

| Código | Idioma | Bandera | Label |
|--------|--------|---------|-------|
| `es` | Español | 🇵🇾 | ES |
| `en` | Inglés | 🇺🇸 | EN |
| `pt` | Portugués | 🇧🇷 | PT |
| `gn` | Guaraní | 🪶 | GN |

---

## Flujo completo

```
Usuario hace clic en el selector
        │
        ▼
POST /locale  { locale: "en" }
        │
        ├─ session(['locale' => 'en'])
        │
        ├─ user autenticado?
        │   ├─ SÍ → user->update(['locale' => 'en'])
        │   └─ NO → solo sesión
        │
        └─ back()  ← redirige a la misma página

Siguiente request:
        │
        ▼
Middleware SetLocale
        │
        ├─ user->locale ?? session('locale') ?? config('app.locale')
        │
        └─ App::setLocale('en')
                │
                ▼
        {{ __('Iniciar sesión') }} → "Log in"
        {{ __('Bienvenido, :name') }} → "Welcome, Juan"
```
