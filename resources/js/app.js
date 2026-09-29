import '@tabler/core/dist/js/tabler.min.js';
import TomSelect from 'tom-select';
import { getTheme, setTheme } from './theme.js';

// NOTE: Tabler bundles its own Bootstrap build which owns all
// data-bs-* behavior. Do NOT import a second Bootstrap copy — duplicate
// Data-API handlers double-toggle dropdowns/modals (open+close = dead UI).
// Programmatic modal use goes through a hidden data-bs-toggle trigger
// (see service-form.js showTestModal), never window.bootstrap.
window.TomSelect = TomSelect;

document.addEventListener('DOMContentLoaded', () => {
    // The inline head snippet already applied the theme pre-paint;
    // normalize here so late markup and toggles stay consistent.
    document.documentElement.setAttribute('data-bs-theme', getTheme());

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const current = document.documentElement.getAttribute('data-bs-theme');
            setTheme(current === 'dark' ? 'light' : 'dark');
        });
    });

    // Opt-in enhancement for selects: <select data-tom-select>
    document.querySelectorAll('select[data-tom-select]').forEach((el) => {
        if (!el.tomselect) {
            new TomSelect(el, {});
        }
    });

    // Request-builder form (code-split: only loaded where needed).
    if (document.querySelector('[data-service-form]')) {
        import('./service-form.js');
    }

    // Public status polling + charts (code-split).
    if (document.querySelector('[data-status-poll]') || document.getElementById('response-chart')) {
        import('./status-page.js');
    }

    // Branding image dropzones (code-split).
    if (document.querySelector('[data-branding-dropzone]')) {
        import('./branding-upload.js');
    }
});
