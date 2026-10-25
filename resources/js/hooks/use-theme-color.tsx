import { useSyncExternalStore } from 'react';
import type { ThemeColor } from '@/lib/theme-colors';
import { defaultThemeColor, isThemeColor } from '@/lib/theme-colors';

// Works like use-appearance: the choice lives in localStorage for the client
// and in a cookie so the server can set it on <html> before the page paints.

const COOKIE = 'theme_color';

const listeners = new Set<() => void>();
let currentColor: ThemeColor = defaultThemeColor;

const setCookie = (name: string, value: string, days = 365): void => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;
    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const getStoredColor = (): ThemeColor => {
    if (typeof window === 'undefined') {
        return defaultThemeColor;
    }

    const stored = localStorage.getItem(COOKIE);

    return isThemeColor(stored) ? stored : defaultThemeColor;
};

const applyColor = (color: ThemeColor): void => {
    if (typeof document === 'undefined') {
        return;
    }

    document.documentElement.dataset.themeColor = color;
};

const subscribe = (callback: () => void) => {
    listeners.add(callback);

    return () => listeners.delete(callback);
};

const notify = (): void => listeners.forEach((listener) => listener());

export function initializeThemeColor(): void {
    if (typeof window === 'undefined') {
        return;
    }

    currentColor = getStoredColor();
    applyColor(currentColor);
}

export function useThemeColor() {
    const themeColor = useSyncExternalStore(
        subscribe,
        () => currentColor,
        () => defaultThemeColor,
    );

    const updateThemeColor = (color: ThemeColor): void => {
        currentColor = color;

        localStorage.setItem(COOKIE, color);
        setCookie(COOKIE, color);

        applyColor(color);
        notify();
    };

    return { themeColor, updateThemeColor } as const;
}
