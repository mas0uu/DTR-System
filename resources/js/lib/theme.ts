import { useEffect, useMemo, useState } from 'react';

export type ThemeMode = 'light' | 'dark';

const THEME_STORAGE_KEY = 'ui-theme-mode';
const DARK_THEME_CLASS = 'theme-dark';
const THEME_EVENT = 'app-theme-change';

function getSystemTheme(): ThemeMode {
    if (typeof window === 'undefined') {
        return 'light';
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function getStoredTheme(): ThemeMode | null {
    if (typeof window === 'undefined') {
        return null;
    }

    const storedTheme = window.localStorage.getItem(THEME_STORAGE_KEY);
    if (storedTheme === 'light' || storedTheme === 'dark') {
        return storedTheme;
    }

    return null;
}

function applyThemeClass(themeMode: ThemeMode): void {
    if (typeof document === 'undefined') {
        return;
    }

    document.documentElement.classList.toggle(DARK_THEME_CLASS, themeMode === 'dark');
    document.documentElement.setAttribute('data-theme', themeMode);
}

function persistTheme(themeMode: ThemeMode): void {
    if (typeof window === 'undefined') {
        return;
    }

    window.localStorage.setItem(THEME_STORAGE_KEY, themeMode);
}

function broadcastTheme(themeMode: ThemeMode): void {
    if (typeof window === 'undefined') {
        return;
    }

    window.dispatchEvent(new CustomEvent<ThemeMode>(THEME_EVENT, { detail: themeMode }));
}

export function getInitialTheme(): ThemeMode {
    return getStoredTheme() ?? getSystemTheme();
}

export function initializeTheme(): void {
    applyThemeClass(getInitialTheme());
}

export function setTheme(themeMode: ThemeMode): void {
    applyThemeClass(themeMode);
    persistTheme(themeMode);
    broadcastTheme(themeMode);
}

export function useThemeMode() {
    const [themeMode, setThemeMode] = useState<ThemeMode>(() => {
        if (typeof document !== 'undefined' && document.documentElement.classList.contains(DARK_THEME_CLASS)) {
            return 'dark';
        }

        return getInitialTheme();
    });

    useEffect(() => {
        const onThemeChange = (event: Event) => {
            const customEvent = event as CustomEvent<ThemeMode>;
            if (customEvent.detail === 'light' || customEvent.detail === 'dark') {
                setThemeMode(customEvent.detail);
            }
        };

        const onStorage = (event: StorageEvent) => {
            if (event.key !== THEME_STORAGE_KEY) {
                return;
            }

            const nextTheme = event.newValue === 'dark' ? 'dark' : 'light';
            setThemeMode(nextTheme);
            applyThemeClass(nextTheme);
        };

        window.addEventListener(THEME_EVENT, onThemeChange as EventListener);
        window.addEventListener('storage', onStorage);

        return () => {
            window.removeEventListener(THEME_EVENT, onThemeChange as EventListener);
            window.removeEventListener('storage', onStorage);
        };
    }, []);

    const setAndPersistTheme = useMemo(
        () => (nextTheme: ThemeMode) => {
            setThemeMode(nextTheme);
            setTheme(nextTheme);
        },
        [],
    );

    const toggleTheme = useMemo(
        () => () => {
            setAndPersistTheme(themeMode === 'dark' ? 'light' : 'dark');
        },
        [setAndPersistTheme, themeMode],
    );

    return {
        themeMode,
        isDark: themeMode === 'dark',
        setThemeMode: setAndPersistTheme,
        toggleTheme,
    };
}
