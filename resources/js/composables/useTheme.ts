import { ref, watch } from 'vue';
import { DEFAULT_THEME, type ThemeName } from '@/lib/themes';

const STORAGE_KEY = 'theme';
const isBrowser = typeof window !== 'undefined';

const theme = ref<ThemeName>(DEFAULT_THEME);

function applyTheme(value: ThemeName) {
    if (!isBrowser) return;

    if (value === DEFAULT_THEME) {
        document.documentElement.removeAttribute('data-theme');
    } else {
        document.documentElement.setAttribute('data-theme', value);
    }
}

export function initializeTheme() {
    if (!isBrowser) return; // no-op during SSR

    // HopSurigao portals stay on the cyan default theme.
    theme.value = DEFAULT_THEME;
    localStorage.setItem(STORAGE_KEY, DEFAULT_THEME);
    applyTheme(theme.value);
}

function updateTheme(value: ThemeName) {
    theme.value = value;
    if (isBrowser) localStorage.setItem(STORAGE_KEY, value);
    applyTheme(value);
}

watch(theme, (value) => applyTheme(value));

export function useTheme() {
    return { theme, updateTheme };
}
