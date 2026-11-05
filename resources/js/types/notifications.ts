/** An entry in the in-app inbox behind the sidebar bell. */
export type AppNotification = {
    id: string;
    message: string;
    task: { id: number; title: string };
    project: { id: number; name: string; color: string };
    actor: string | null;
    url: string;
    readAt: string | null;
    createdAt: string | null;
    createdAtDiff: string | null;
};
