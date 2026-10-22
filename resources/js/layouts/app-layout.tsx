import { usePage } from '@inertiajs/react';
import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import type { BreadcrumbItem } from '@/types';
import { setUrlDefaults } from '@/wayfinder';

export default function AppLayout({
    breadcrumbs = [],
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    children: React.ReactNode;
}) {
    const { currentTeam } = usePage().props;

    // Workspace routes all start with /{current_team}; fill it in for every
    // Wayfinder URL built below this layout so callers can leave it out.
    setUrlDefaults({ current_team: currentTeam?.slug });

    return (
        <AppLayoutTemplate breadcrumbs={breadcrumbs}>
            {children}
        </AppLayoutTemplate>
    );
}
