import { router, usePage } from '@inertiajs/react';
import { Bell, CheckCheck } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenuBadge,
    SidebarMenuButton,
    useSidebar,
} from '@/components/ui/sidebar';
import { Spinner } from '@/components/ui/spinner';
import { useIsMobile } from '@/hooks/use-mobile';
import { useNotificationLiveUpdates } from '@/lib/live-updates';
import { cn } from '@/lib/utils';
import { index, read, readAll } from '@/routes/notifications';
import type { AppNotification } from '@/types';

/**
 * The sidebar's bell: an unread badge and a panel of the latest
 * notifications. Opening one marks it read and goes to the task.
 */
export function NotificationsButton() {
    const { auth, currentTeam, unreadNotificationsCount } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';
    const { state } = useSidebar();
    const isMobile = useIsMobile();
    const [open, setOpen] = useState(false);
    const [notifications, setNotifications] = useState<
        AppNotification[] | null
    >(null);
    const unread = unreadNotificationsCount ?? 0;

    const load = useCallback(
        async (signal?: AbortSignal) => {
            try {
                const response = await fetch(index.url(teamSlug), {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    signal,
                });

                if (response.ok) {
                    const data = (await response.json()) as {
                        notifications: AppNotification[];
                    };
                    setNotifications(data.notifications);
                }
            } catch (error) {
                if (
                    !(
                        error instanceof DOMException &&
                        error.name === 'AbortError'
                    )
                ) {
                    throw error;
                }
            }
        },
        [teamSlug],
    );

    // Fetch fresh each time the panel opens.
    useEffect(() => {
        if (!open) {
            return;
        }

        const controller = new AbortController();
        void load(controller.signal);

        return () => controller.abort();
    }, [open, load]);

    // A new notification while the panel is open shows up without reopening.
    useNotificationLiveUpdates(
        auth.user.id,
        () => {
            if (open) {
                void load();
            }
        },
        [open, load],
    );

    const openNotification = (notification: AppNotification) => {
        router.patch(
            read.url({ current_team: teamSlug, notification: notification.id }),
        );
    };

    const markAllRead = () => {
        router.post(
            readAll.url(teamSlug),
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    const now = new Date().toISOString();
                    setNotifications(
                        (current) =>
                            current?.map((notification) => ({
                                ...notification,
                                readAt: notification.readAt ?? now,
                            })) ?? null,
                    );
                },
            },
        );
    };

    return (
        <DropdownMenu open={open} onOpenChange={setOpen}>
            <DropdownMenuTrigger asChild>
                <SidebarMenuButton
                    tooltip={{ children: 'Notifications' }}
                    className="data-[state=open]:bg-sidebar-accent"
                    aria-label={
                        unread > 0
                            ? `Notifications, ${unread} unread`
                            : 'Notifications'
                    }
                >
                    <span className="relative flex shrink-0">
                        <Bell className="size-4 shrink-0" />
                        {unread > 0 && (
                            <span
                                aria-hidden
                                className="absolute -top-0.5 -right-0.5 hidden size-2 rounded-full bg-red-500 group-data-[collapsible=icon]:block"
                            />
                        )}
                    </span>
                    <span>Notifications</span>
                </SidebarMenuButton>
            </DropdownMenuTrigger>
            {unread > 0 && (
                <SidebarMenuBadge
                    className="bg-red-500 text-white group-data-[collapsible=icon]:hidden"
                    title="Unread notifications"
                >
                    {unread > 99 ? '99+' : unread}
                </SidebarMenuBadge>
            )}
            <DropdownMenuContent
                className="w-80 rounded-lg p-0"
                align="start"
                side={
                    isMobile
                        ? 'bottom'
                        : state === 'collapsed'
                          ? 'right'
                          : 'bottom'
                }
            >
                <div className="flex items-center justify-between border-b px-3 py-2">
                    <span className="text-sm font-medium">Notifications</span>
                    <button
                        type="button"
                        onClick={markAllRead}
                        disabled={unread === 0}
                        className="flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground disabled:opacity-50"
                    >
                        <CheckCheck className="size-3.5" />
                        Mark all read
                    </button>
                </div>
                <div className="max-h-96 overflow-y-auto p-1">
                    {notifications === null && (
                        <div className="flex justify-center py-6">
                            <Spinner />
                        </div>
                    )}
                    {notifications?.length === 0 && (
                        <p className="px-3 py-6 text-center text-sm text-muted-foreground">
                            Nothing yet. Assignments, comments and mentions show
                            up here.
                        </p>
                    )}
                    {notifications?.map((notification) => (
                        <DropdownMenuItem
                            key={notification.id}
                            onSelect={() => openNotification(notification)}
                            className={cn(
                                'cursor-pointer items-start gap-2.5 px-2.5 py-2',
                                !notification.readAt && 'bg-accent/50',
                            )}
                        >
                            <span
                                className="mt-1.5 size-2.5 shrink-0 rounded-sm"
                                style={{
                                    backgroundColor: notification.project.color,
                                }}
                            />
                            <span className="min-w-0 flex-1">
                                <span
                                    className={cn(
                                        'block truncate',
                                        !notification.readAt && 'font-medium',
                                    )}
                                >
                                    {notification.message}
                                </span>
                                <span className="block truncate text-xs text-muted-foreground">
                                    {notification.task.title} ·{' '}
                                    {notification.project.name}
                                </span>
                                <span className="block text-xs text-muted-foreground">
                                    {notification.createdAtDiff}
                                </span>
                            </span>
                            {!notification.readAt && (
                                <span
                                    aria-label="Unread"
                                    className="mt-2 size-2 shrink-0 rounded-full bg-red-500"
                                />
                            )}
                        </DropdownMenuItem>
                    ))}
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
