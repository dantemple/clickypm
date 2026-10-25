// The colours a user can pick for the sidebar gradient. The gradient itself
// is defined per colour in app.css under [data-theme-color="…"]; the swatch
// here is only what the picker shows.
export const themeColors = [
    { value: 'slate', label: 'Slate', swatch: '#5b5c66' },
    { value: 'violet', label: 'Violet', swatch: '#7c6ff2' },
    { value: 'blue', label: 'Blue', swatch: '#3b8de0' },
    { value: 'pink', label: 'Pink', swatch: '#e06a9e' },
    { value: 'purple', label: 'Purple', swatch: '#b45fd8' },
    { value: 'indigo', label: 'Indigo', swatch: '#6a84f5' },
    { value: 'orange', label: 'Orange', swatch: '#d9752e' },
    { value: 'teal', label: 'Teal', swatch: '#3f9a9c' },
    { value: 'stone', label: 'Stone', swatch: '#a08c7d' },
    { value: 'green', label: 'Green', swatch: '#6cbf7f' },
] as const;

export type ThemeColor = (typeof themeColors)[number]['value'];

export const defaultThemeColor: ThemeColor = 'slate';

export const isThemeColor = (value: unknown): value is ThemeColor =>
    themeColors.some((color) => color.value === value);
