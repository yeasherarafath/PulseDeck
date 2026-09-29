/**
 * Central theme manager (dark / light, default light).
 *
 * - The layout <head> runs a tiny inline snippet pre-paint that applies the
 *   stored theme to avoid a flash of the wrong theme (FOUC).
 * - This module owns runtime toggling + persistence and notifies listeners
 *   (ApexCharts, Monaco, …) via the `theme:changed` document event.
 */
const STORAGE_KEY = 'status-theme';

export function getTheme() {
    const stored = localStorage.getItem(STORAGE_KEY);

    if (stored === 'dark' || stored === 'light') {
        return stored;
    }

    return document.documentElement.dataset.themeDefault || 'light';
}

export function setTheme(theme) {
    const value = theme === 'dark' ? 'dark' : 'light';

    document.documentElement.setAttribute('data-bs-theme', value);
    localStorage.setItem(STORAGE_KEY, value);

    document
        .querySelector('meta[name="color-scheme"]')
        ?.setAttribute('content', value === 'dark' ? 'dark light' : 'light dark');

    document.dispatchEvent(new CustomEvent('theme:changed', { detail: { theme: value } }));
}

export function initTheme() {
    setTheme(getTheme());
}
