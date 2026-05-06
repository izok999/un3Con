import './bootstrap';
import './particles';

// ── UNE Theme System ──────────────────────────────────────────────────

// 1. Aplicar tema guardado en localStorage antes del primer render
(function () {
    const saved = localStorage.getItem('une-theme');
    if (saved) document.documentElement.setAttribute('data-theme', saved);
})();

// 2. Toggle via event delegation (sobrevive re-renders de Livewire)
document.addEventListener('change', (e) => {
    if (e.target.id === 'theme-toggle') {
        const theme = e.target.checked ? 'uneThemeDark' : 'uneTheme';
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('une-theme', theme);
    }
});

// 3. Sincronizar estado del checkbox tras cada carga / navegación Livewire
function syncThemeToggle() {
    const toggle = document.getElementById('theme-toggle');
    if (toggle) {
        toggle.checked = document.documentElement.getAttribute('data-theme') === 'uneThemeDark';
    }
}

let routeTransitionTimeout;

function setRouteTransition(state) {
    if (routeTransitionTimeout) {
        window.clearTimeout(routeTransitionTimeout);
    }

    if (! state) {
        delete document.documentElement.dataset.routeTransition;

        return;
    }

    document.documentElement.dataset.routeTransition = state;
}

document.addEventListener('DOMContentLoaded', syncThemeToggle);
document.addEventListener('livewire:navigated', syncThemeToggle);
document.addEventListener('livewire:navigate', () => setRouteTransition('out'));
document.addEventListener('livewire:navigated', () => {
    setRouteTransition('in');

    routeTransitionTimeout = window.setTimeout(() => setRouteTransition(null), 460);
});

// 4. Topbar: clase "scrolled" para el efecto glass intensificado al hacer scroll
window.addEventListener('scroll', () => {
    const topbar = document.getElementById('main-topbar');
    if (topbar) topbar.classList.toggle('scrolled', window.scrollY > 10);
}, { passive: true });
