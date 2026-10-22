import type { InertiaLinkProps } from '@inertiajs/react';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}

let lastKey = 0;

/**
 * A React key for a row that doesn't have an id yet. (Not crypto.randomUUID:
 * that only exists on HTTPS and localhost, not on http://*.test sites.)
 */
export function newKey(): string {
    lastKey += 1;

    return `new-${lastKey}`;
}
