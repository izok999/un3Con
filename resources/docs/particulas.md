# Animación de Partículas Conectadas — Login SEFUNE

> Implementación vanilla JS, sin dependencias externas. Aplicada sobre el layout guest de Laravel Breeze (Livewire/Volt stack).

---

## Archivos involucrados

| Archivo | Rol |
|---|---|
| `resources/js/particles.js` | Lógica completa de la animación |
| `resources/js/app.js` | Import del módulo |
| `resources/views/layouts/guest.blade.php` | Canvas HTML + estructura z-index |

---

## Cómo funciona

### 1. Canvas en el layout guest

En `layouts/guest.blade.php` el wrapper principal tiene `relative overflow-hidden`. Dentro, como primer hijo, vive el canvas con `absolute inset-0` y `pointer-events-none` para que no bloquee los clicks del formulario:

```html
<div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 relative overflow-hidden">

    <canvas id="login-particles" data-particles
            class="absolute inset-0 w-full h-full pointer-events-none"
            style="z-index:0"></canvas>

    <!-- Logo y card con z-index:1 para quedar por encima -->
    <div class="mb-4 relative" style="z-index:1"> ... </div>
    <div class="w-full sm:max-w-md ... relative" style="z-index:1"> {{ $slot }} </div>
</div>
```

El atributo `data-particles` lo detecta el auto-init al cargar la página.

---

### 2. Auto-inicialización

`particles.js` escucha dos eventos para cubrir carga inicial y navegaciones SPA de Livewire:

```js
document.addEventListener('DOMContentLoaded', autoInit);
document.addEventListener('livewire:navigated', autoInit);
```

`autoInit` busca todos los `<canvas data-particles>`, les asigna un `id` si no tienen, y llama a `initParticles(id)`.

---

### 3. Loop de animación

- Se usa `requestAnimationFrame` con un **cap de ~30 fps** (`FPS_CAP = 33 ms`) para no saturar la GPU en pantallas de alta frecuencia.
- Cada frame: `clearRect` → mover partículas → dibujar círculos → dibujar líneas p-a-p → dibujar cursor.
- Las partículas rebotan en los bordes (`vx *= -1`, `vy *= -1`).

---

### 4. Partículas

```js
const PARTICLE_COLOR = '#6A9149'; // verde primary uneTheme
const COUNT_DESKTOP  = 95;
const COUNT_MOBILE   = 35;        // < 768px
const DIST           = 130;       // umbral de conexión en px
const SPEED          = 0.45;      // px por frame
```

Cada partícula es un objeto `{ x, y, r, vx, vy }` con radio entre 1.5 y 3px y `globalAlpha = 0.75`.

---

### 5. Líneas entre partículas

Se iteran todos los pares `(i, j)` con `j > i`. Si la distancia euclidiana es menor que `DIST`, se traza una línea cuya opacidad decrece linealmente con la distancia:

```js
ctx.globalAlpha = (1 - d / DIST) * 0.55;
```

Grosor: `1.2px`.

---

### 6. Cursor como partícula

El cursor se representa con:

- **Halo radial** (`createRadialGradient`, radio 12px) con `globalAlpha = 1`.
- **Núcleo** sólido de radio 3px, mismo color.
- **Conexiones** a partículas dentro de `DIST`, con opacidad `(1 - d / DIST) * 0.7` y grosor `1.2px`.

Como el canvas tiene `pointer-events-none`, los listeners de ratón se registran en **`document`** (no en el canvas):

```js
document.addEventListener('mousemove', _onMove);
document.addEventListener('mouseleave', _onLeave);
```

---

### 7. Gestión de memoria y Livewire

Dos `Map` globales previenen fugas al navegar con Livewire:

| Map | Contenido |
|---|---|
| `_rafIds` | `canvasId → rafId` activo — se cancela antes de re-init |
| `_cleanups` | `canvasId → fn()` que remueve los listeners de `document` |

Al llamar `initParticles(id)` por segunda vez (e.g. navegación SPA), ambos se limpian primero.

---

### 8. Rendimiento y accesibilidad

| Feature | Detalle |
|---|---|
| `prefers-reduced-motion` | Si está activa, `initParticles` sale inmediatamente sin montar nada |
| `IntersectionObserver` | Pausa el loop cuando el canvas sale del viewport (`paused = true`) |
| `ResizeObserver` | Re-calcula dimensiones y regenera partículas al redimensionar el contenedor |
| DPR | `canvas.width = Math.round(W * dpr)` + `ctx.setTransform(dpr, ...)` para pantallas retina |

---

## Personalización rápida

Editar las constantes al inicio de `resources/js/particles.js`:

```js
const PARTICLE_COLOR = '#6A9149'; // color HEX
const COUNT_DESKTOP  = 95;        // cantidad en escritorio
const COUNT_MOBILE   = 35;        // cantidad en móvil (< 768px)
const DIST           = 130;       // distancia máxima de conexión (px)
const SPEED          = 0.45;      // velocidad de movimiento
```

Después de cualquier cambio, recompilar:

```bash
./vendor/bin/sail npm run build
```
