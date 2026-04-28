# Tema Visual UNE — Sistema de Estilos

Stack: **Tailwind CSS v4 + DaisyUI 5 + MaryUI 2.8 + Livewire 3**

---

## Temas disponibles

| Nombre | Modo | Activación |
|---|---|---|
| `uneTheme` | Claro (default) | Automático o toggle |
| `uneThemeDark` | Oscuro | Toggle ☀️/🌙 |

El tema activo se persiste en `localStorage` bajo la clave `une-theme` y se aplica en `<html data-theme="...">` antes del primer render para evitar flash.

---

## Paleta de colores

| Token | Claro | Oscuro | Uso |
|---|---|---|---|
| `--color-primary` | `#6A9149` | `#7FB356` | Verde institucional UNE |
| `--color-secondary` | `#CC9933` | `#D4A847` | Dorado institucional |
| `--color-accent` | `#F6CD1B` | `#F6CD1B` | Amarillo énfasis |
| `--color-neutral` | `#2a2a2a` | `#1a1a2e` | Fondo neutro / footer |
| `--color-base-100` | `#ffffff` | `#1e2028` | Fondo de tarjetas |
| `--color-base-200` | `#f3f4f6` | `#16181f` | Fondo de páginas |
| `--color-base-300` | `#e5e7eb` | `#2a2d3a` | Bordes |
| `--color-base-content` | `#1f2937` | `#e5e7eb` | Texto principal |
| `--color-error` | `#af3030` | `#d44444` | Errores / deudas |

---

## Radios globales

Definidos en ambos temas vía variables DaisyUI:

```css
--rounded-box:      1.5rem;   /* rounded-2xl — tarjetas, modales */
--rounded-btn:      0.75rem;  /* rounded-xl  — botones */
--rounded-badge:    1.5rem;
--rounded-selector: 0.5rem;
```

---

## Fondo con patrón

Clase `.bg-app-pattern` — gradiente diagonal verde→dorado con puntos blancos semitransparentes.

```html
<body class="bg-app-pattern">
```

Definido en `app.css` bajo `@layer utilities`. Tiene variante automática para `uneThemeDark`.

---

## Clases Glass

Todas definidas en `resources/css/app.css` bajo `@layer utilities`. Tienen overrides para modo oscuro automáticos.

### `.glass-card`
Tarjetas y stat blocks. Incluye hover con elevación suave.

```html
<div class="card glass-card">...</div>
<div class="stat glass-card">...</div>
```

| Propiedad | Valor claro | Valor oscuro |
|---|---|---|
| background | `white / 40%` | `white / 7%` |
| backdrop-filter | `blur(24px)` | `blur(24px)` |
| border | `white / 20%` | `white / 10%` |
| shadow hover | `0 20px 48px black/20` | `0 20px 48px black/60` |

### `.glass-surface`
Superficies sin hover: dropdowns, popovers.

```html
<ul class="dropdown-content glass-surface rounded-2xl ...">
```

### `.glass-sidebar`
Panel lateral del layout autenticado.

```html
<x-slot:sidebar class="glass-sidebar">
```

### `.glass-navbar` / `.glass-navbar.scrolled`
Topbar sticky. La clase `.scrolled` se aplica automáticamente via JS al hacer scroll (`window.scrollY > 10`).

```html
<div id="main-topbar" class="navbar glass-navbar sticky top-0 z-50">
```

---

## Badges

Todos los badges del sistema tienen padding y border-radius personalizados definidos globalmente en `app.css`. No requieren clases adicionales — se aplican automáticamente a cualquier elemento con clase `.badge`.

| Variante | Clase | Border radius | Padding |
|---|---|---|---|
| Normal | `.badge` | `0.75rem` | `0.35em 0.65em` |
| Pequeño | `.badge.badge-sm` | `0.625rem` | `0.28em 0.55em` |
| Grande | `.badge.badge-lg` | `0.875rem` | `0.45em 0.85em` |

Usar las clases de color DaisyUI de siempre:

```html
<span class="badge badge-success">Vigente</span>
<span class="badge badge-warning badge-sm">No vigente</span>
<span class="badge badge-error">Deuda</span>
<span class="badge badge-neutral badge-sm">2026-I</span>
<span class="badge badge-outline">Período</span>
```

Para páginas nuevas no es necesario nada adicional — el estilo global cubre todos los badges automáticamente.

---

## Toggle de modo oscuro

El checkbox con `id="theme-toggle"` puede colocarse en cualquier layout. El JS en `app.js` lo detecta por delegación.

```html
<label class="swap swap-rotate btn btn-ghost btn-sm">
    <input type="checkbox" id="theme-toggle" />
    <!-- ícono sol (swap-off) -->
    <!-- ícono luna (swap-on) -->
</label>
```

El JS en `resources/js/app.js` maneja:
1. Persistencia en `localStorage`
2. Sincronización del estado del checkbox tras navegación `wire:navigate`
3. Aplicación del tema antes del primer render (inline script en `<head>`)

---

## Archivos modificados

| Archivo | Cambios |
|---|---|
| `resources/css/app.css` | Tema oscuro `uneThemeDark`, radios globales, `.bg-app-pattern`, clases glass |
| `resources/js/app.js` | Theme toggle, persistencia localStorage, scroll listener topbar |
| `resources/views/layouts/app.blade.php` | Sidebar glass, topbar sticky glass, toggle en navbar |
| `resources/views/layouts/guest.blade.php` | Fondo gradiente, card formulario glass |
| `resources/views/welcome.blade.php` | Navbar sticky glass con toggle, tarjetas features glass |
| `resources/views/dashboard.blade.php` | Stat cards y welcome card en glass |

---

## Uso en páginas nuevas

Para cualquier vista nueva dentro del layout autenticado, los estilos se aplican automáticamente. Para tarjetas nuevas, usar:

```html
{{-- Tarjeta con glass y hover --}}
<div class="card glass-card">
    <div class="card-body">...</div>
</div>

{{-- Stat con glass --}}
<div class="stat glass-card">...</div>
```

Para páginas que no usan los layouts base (standalone), agregar:

```html
<html data-theme="uneTheme">
<body class="bg-app-pattern font-sans antialiased text-base-content">
<script>const t=localStorage.getItem('une-theme');if(t)document.documentElement.setAttribute('data-theme',t);</script>
```
