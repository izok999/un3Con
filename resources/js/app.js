import './bootstrap';
import './particles';

// ── UNE Theme System ──────────────────────────────────────────────────

const allowedThemes = ['uneTheme', 'uneThemeDark'];

function readPersistedTheme() {
    const theme = readThemeCookie() ?? localStorage.getItem('une-theme');

    return allowedThemes.includes(theme) ? theme : null;
}

function readThemeCookie() {
    const serializedThemeCookie = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('une-theme='));

    if (! serializedThemeCookie) {
        return null;
    }

    const theme = decodeURIComponent(serializedThemeCookie.split('=').slice(1).join('='));

    return allowedThemes.includes(theme) ? theme : null;
}

function persistTheme(theme) {
    if (! allowedThemes.includes(theme)) {
        return;
    }

    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('une-theme', theme);
    document.cookie = `une-theme=${encodeURIComponent(theme)}; path=/; max-age=31536000; samesite=lax`;
}

// 1. Aplicar tema guardado en localStorage antes del primer render
(function () {
    const saved = readPersistedTheme();

    if (saved) {
        persistTheme(saved);
    }
})();

// 2. Toggle via event delegation (sobrevive re-renders de Livewire)
document.addEventListener('change', (e) => {
    if (e.target.id === 'theme-toggle') {
        const theme = e.target.checked ? 'uneThemeDark' : 'uneTheme';
        persistTheme(theme);
    }
});

// 3. Sincronizar estado del checkbox tras cada carga / navegación Livewire
function syncThemeToggle() {
    const theme = readPersistedTheme();

    if (theme) {
        persistTheme(theme);
    }

    const toggle = document.getElementById('theme-toggle');

    if (toggle) {
        toggle.checked = document.documentElement.getAttribute('data-theme') === 'uneThemeDark';
    }
}

let routeTransitionTimeout;
const routeTransitionClearDelay = 240;

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

    routeTransitionTimeout = window.setTimeout(() => setRouteTransition(null), routeTransitionClearDelay);
});

// 4. Topbar: clase "scrolled" para el efecto glass intensificado al hacer scroll
window.addEventListener('scroll', () => {
    const topbar = document.getElementById('main-topbar');
    if (topbar) topbar.classList.toggle('scrolled', window.scrollY > 10);
}, { passive: true });
