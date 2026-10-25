import { router, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { update } from '@/routes/appearance';
import type { Auth } from '@/types';

type PageProps = { auth: Auth };

// The sidebar colour lives on the account, so it follows the user across
// devices. The server sets it on <html> for every page load; this hook keeps
// that attribute in step when it changes without a full reload.

export function useThemeColor() {
    const { auth } = usePage<PageProps>().props;
    const themeColor = auth.user?.theme_color ?? 'slate';

    useEffect(() => {
        document.documentElement.dataset.themeColor = themeColor;
    }, [themeColor]);

    const updateThemeColor = (color: string): void => {
        // Apply it straight away; the saved value comes back with the response.
        document.documentElement.dataset.themeColor = color;

        router.patch(
            update.url(),
            { theme_color: color },
            { preserveScroll: true, preserveState: true },
        );
    };

    return { themeColor, updateThemeColor } as const;
}
