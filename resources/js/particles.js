/**
 * Partículas conectadas — Portal SEFUNE
 * Vanilla JS puro, sin dependencias externas.
 * Compatible con Livewire navigate (full-page) y Livewire 4 (SPA).
 *
 * Constantes personalizables:
 */
const PARTICLE_COLOR = '#6A9149'; // primary uneTheme
const COUNT_DESKTOP  = 95;
const COUNT_MOBILE   = 35;
const DIST           = 130;
const SPEED          = 0.45;

/** Map<canvasId, rafId> — evita loops apilados en re-renders de Livewire */
const _rafIds = new Map();
/** Map<canvasId, cleanupFn> — elimina listeners de documento al re-init */
const _cleanups = new Map();

/**
 * Inicializa la animación en el <canvas> con el id dado.
 * @param {string} canvasId
 */
export function initParticles(canvasId) {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const canvas = document.getElementById(canvasId);
    if (!canvas) return;

    // Cancelar loop previo sobre el mismo canvas
    if (_rafIds.has(canvasId)) {
        cancelAnimationFrame(_rafIds.get(canvasId));
        _rafIds.delete(canvasId);
    }
    // Eliminar listeners de documento anteriores
    if (_cleanups.has(canvasId)) {
        _cleanups.get(canvasId)();
        _cleanups.delete(canvasId);
    }

    const ctx = canvas.getContext('2d');
    const dpr = window.devicePixelRatio || 1;

    let W, H, particles;
    let mouse = { x: null, y: null };
    let paused = false;
    let lastFrame = 0;
    const FPS_CAP = 33; // ~30 fps

    function resize() {
        const rect = canvas.parentElement.getBoundingClientRect();
        W = rect.width;
        H = rect.height;
        canvas.width  = Math.round(W * dpr);
        canvas.height = Math.round(H * dpr);
        canvas.style.width  = W + 'px';
        canvas.style.height = H + 'px';
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        spawnParticles();
    }

    function spawnParticles() {
        const count = window.innerWidth < 768 ? COUNT_MOBILE : COUNT_DESKTOP;
        particles = Array.from({ length: count }, () => ({
            x:  Math.random() * W,
            y:  Math.random() * H,
            r:  1.5 + Math.random() * 1.5,
            vx: (Math.random() - 0.5) * 2 * SPEED,
            vy: (Math.random() - 0.5) * 2 * SPEED,
        }));
    }

    function loop(now) {
        const rafId = requestAnimationFrame(loop);
        _rafIds.set(canvasId, rafId);

        if (paused) return;
        if (now - lastFrame < FPS_CAP) return;
        lastFrame = now;

        ctx.clearRect(0, 0, W, H);

        // Mover y dibujar partículas
        for (const p of particles) {
            p.x += p.vx;
            p.y += p.vy;
            if (p.x < 0 || p.x > W) p.vx *= -1;
            if (p.y < 0 || p.y > H) p.vy *= -1;

            ctx.beginPath();
            ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
            ctx.fillStyle = PARTICLE_COLOR;
            ctx.globalAlpha = 0.75;
            ctx.fill();
        }

        // Líneas entre partículas cercanas
        ctx.lineWidth = 1.2;
        for (let i = 0; i < particles.length; i++) {
            for (let j = i + 1; j < particles.length; j++) {
                const dx = particles[i].x - particles[j].x;
                const dy = particles[i].y - particles[j].y;
                const d  = Math.sqrt(dx * dx + dy * dy);
                if (d < DIST) {
                    ctx.globalAlpha = (1 - d / DIST) * 0.55;
                    ctx.strokeStyle = PARTICLE_COLOR;
                    ctx.beginPath();
                    ctx.moveTo(particles[i].x, particles[i].y);
                    ctx.lineTo(particles[j].x, particles[j].y);
                    ctx.stroke();
                }
            }
        }

        // Ratón como partícula: círculo propio + conexiones del mismo estilo p-a-p
        if (mouse.x !== null) {
            // Halo suave
            const grd = ctx.createRadialGradient(mouse.x, mouse.y, 0, mouse.x, mouse.y, 12);
            grd.addColorStop(0, PARTICLE_COLOR + 'cc');
            grd.addColorStop(1, PARTICLE_COLOR + '00');
            ctx.beginPath();
            ctx.arc(mouse.x, mouse.y, 12, 0, Math.PI * 2);
            ctx.fillStyle = grd;
            ctx.globalAlpha = 1;
            ctx.fill();
            // Núcleo
            ctx.beginPath();
            ctx.arc(mouse.x, mouse.y, 3, 0, Math.PI * 2);
            ctx.fillStyle = PARTICLE_COLOR;
            ctx.globalAlpha = 1;
            ctx.fill();
            // Conexiones hacia partículas cercanas (mismo umbral y opacidad que p-a-p)
            ctx.lineWidth = 1.2;
            for (const p of particles) {
                const dx = p.x - mouse.x;
                const dy = p.y - mouse.y;
                const d  = Math.sqrt(dx * dx + dy * dy);
                if (d < DIST) {
                    ctx.globalAlpha = (1 - d / DIST) * 0.7;
                    ctx.strokeStyle = PARTICLE_COLOR;
                    ctx.beginPath();
                    ctx.moveTo(p.x, p.y);
                    ctx.lineTo(mouse.x, mouse.y);
                    ctx.stroke();
                }
            }
        }

        ctx.globalAlpha = 1;
    }

    // Escuchar en document porque el canvas tiene pointer-events:none
    const _onMove = e => {
        const rect = canvas.getBoundingClientRect();
        mouse.x = e.clientX - rect.left;
        mouse.y = e.clientY - rect.top;
    };
    const _onLeave = () => { mouse.x = null; mouse.y = null; };
    document.addEventListener('mousemove', _onMove);
    document.addEventListener('mouseleave', _onLeave);
    _cleanups.set(canvasId, () => {
        document.removeEventListener('mousemove', _onMove);
        document.removeEventListener('mouseleave', _onLeave);
    });

    // Pausar cuando el canvas sale del viewport
    const io = new IntersectionObserver(entries => {
        paused = !entries[0].isIntersecting;
    }, { threshold: 0.1 });
    io.observe(canvas);

    // Redimensionar con el contenedor padre
    const ro = new ResizeObserver(() => resize());
    ro.observe(canvas.parentElement);

    resize();
    const startId = requestAnimationFrame(loop);
    _rafIds.set(canvasId, startId);
}

// Auto-init para cualquier <canvas data-particles> en la página
function autoInit() {
    document.querySelectorAll('canvas[data-particles]').forEach(el => {
        if (!el.id) el.id = 'particles-' + Math.random().toString(36).slice(2, 7);
        initParticles(el.id);
    });
}

document.addEventListener('DOMContentLoaded', autoInit);
document.addEventListener('livewire:navigated', autoInit);
