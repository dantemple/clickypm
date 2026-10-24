import { router, usePage } from '@inertiajs/react';
import { Command } from 'cmdk';
import { MessageSquare, Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { SidebarMenuButton } from '@/components/ui/sidebar';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { search as searchRoute } from '@/routes';

type ProjectResult = {
    id: number;
    name: string;
    color: string;
    description: string | null;
    url: string;
};

type TaskResult = {
    id: number;
    title: string;
    needs_triage: boolean;
    completed_at: string | null;
    project: { id: number; name: string; color: string };
    status: { id: number; name: string; color: string; is_done: boolean };
    url: string;
};

type CommentResult = {
    id: number;
    snippet: string;
    is_internal: boolean;
    user: { id: number; name: string } | null;
    task: { id: number; title: string };
    project: { id: number; name: string; color: string };
    url: string;
};

type SearchResults = {
    projects: ProjectResult[];
    tasks: TaskResult[];
    comments: CommentResult[];
};

const MIN_LENGTH = 2;

const isMac =
    typeof navigator !== 'undefined' &&
    /Mac|iPhone|iPad/.test(navigator.platform);

/** The sidebar's Search entry, which opens the palette. So does ⌘K / Ctrl+K. */
export function SearchButton() {
    const [open, setOpen] = useState(false);

    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            if (
                (event.metaKey || event.ctrlKey) &&
                event.key.toLowerCase() === 'k'
            ) {
                event.preventDefault();
                setOpen((current) => !current);
            }
        };

        document.addEventListener('keydown', onKeyDown);

        return () => document.removeEventListener('keydown', onKeyDown);
    }, []);

    return (
        <>
            <SidebarMenuButton
                tooltip={{ children: 'Search' }}
                onClick={() => setOpen(true)}
            >
                <Search />
                <span>Search</span>
                <kbd className="pointer-events-none ml-auto rounded border bg-muted px-1.5 font-mono text-[10px] text-muted-foreground group-data-[collapsible=icon]:hidden">
                    {isMac ? '⌘' : 'Ctrl'} K
                </kbd>
            </SidebarMenuButton>

            <CommandPalette open={open} onOpenChange={setOpen} />
        </>
    );
}

function CommandPalette({
    open,
    onOpenChange,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<SearchResults | null>(null);
    const [loading, setLoading] = useState(false);
    const term = query.trim();

    // Start fresh each time the palette opens.
    useEffect(() => {
        if (!open) {
            setQuery('');
            setResults(null);
        }
    }, [open]);

    // Fetch shortly after typing stops; a newer search cancels an older one.
    useEffect(() => {
        if (!open || !teamSlug || term.length < MIN_LENGTH) {
            setResults(null);
            setLoading(false);

            return;
        }

        const controller = new AbortController();
        const timer = setTimeout(async () => {
            setLoading(true);

            try {
                const response = await fetch(
                    searchRoute.url(teamSlug, { query: { q: term } }),
                    {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        signal: controller.signal,
                    },
                );

                if (response.ok) {
                    setResults((await response.json()) as SearchResults);
                }

                setLoading(false);
            } catch (error) {
                if (
                    !(
                        error instanceof DOMException &&
                        error.name === 'AbortError'
                    )
                ) {
                    setLoading(false);
                }
            }
        }, 200);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [open, teamSlug, term]);

    const go = (url: string) => {
        onOpenChange(false);
        router.visit(url);
    };

    const nothingFound =
        results !== null &&
        results.projects.length === 0 &&
        results.tasks.length === 0 &&
        results.comments.length === 0;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="top-[15%] translate-y-0 gap-0 overflow-hidden p-0 sm:max-w-xl [&>button:last-child]:hidden">
                <DialogTitle className="sr-only">Search</DialogTitle>
                <DialogDescription className="sr-only">
                    Search the projects, tasks and comments in this workspace
                </DialogDescription>

                <Command shouldFilter={false} label="Search the workspace">
                    <div className="flex items-center gap-2 border-b px-3">
                        <Search className="size-4 shrink-0 text-muted-foreground" />
                        <Command.Input
                            value={query}
                            onValueChange={setQuery}
                            placeholder="Search tasks, projects and comments…"
                            className="h-11 w-full bg-transparent text-sm outline-none placeholder:text-muted-foreground"
                        />
                        {loading && <Spinner className="size-4" />}
                    </div>

                    <Command.List className="max-h-[60vh] overflow-y-auto p-1 [&_[cmdk-group-heading]]:px-2 [&_[cmdk-group-heading]]:py-1.5 [&_[cmdk-group-heading]]:text-xs [&_[cmdk-group-heading]]:font-medium [&_[cmdk-group-heading]]:text-muted-foreground">
                        {term.length < MIN_LENGTH && (
                            <p className="px-3 py-6 text-center text-sm text-muted-foreground">
                                Type at least {MIN_LENGTH} characters to search
                                this workspace.
                            </p>
                        )}

                        {nothingFound && (
                            <Command.Empty className="px-3 py-6 text-center text-sm text-muted-foreground">
                                No results for “{term}”.
                            </Command.Empty>
                        )}

                        {results && results.projects.length > 0 && (
                            <Command.Group heading="Projects">
                                {results.projects.map((project) => (
                                    <Item
                                        key={`project-${project.id}`}
                                        value={`project-${project.id}`}
                                        onSelect={() => go(project.url)}
                                    >
                                        <Dot color={project.color} />
                                        <span className="truncate">
                                            {project.name}
                                        </span>
                                        {project.description && (
                                            <span className="truncate text-muted-foreground">
                                                {project.description}
                                            </span>
                                        )}
                                    </Item>
                                ))}
                            </Command.Group>
                        )}

                        {results && results.tasks.length > 0 && (
                            <Command.Group heading="Tasks">
                                {results.tasks.map((task) => (
                                    <Item
                                        key={`task-${task.id}`}
                                        value={`task-${task.id}`}
                                        onSelect={() => go(task.url)}
                                    >
                                        <Dot color={task.status.color} />
                                        <span
                                            className={cn(
                                                'truncate',
                                                task.completed_at &&
                                                    'text-muted-foreground line-through',
                                            )}
                                        >
                                            {task.title}
                                        </span>
                                        {task.needs_triage && (
                                            <span className="shrink-0 rounded border px-1 text-[10px] text-muted-foreground uppercase">
                                                Request
                                            </span>
                                        )}
                                        <span className="ml-auto shrink-0 text-xs text-muted-foreground">
                                            {task.project.name}
                                        </span>
                                    </Item>
                                ))}
                            </Command.Group>
                        )}

                        {results && results.comments.length > 0 && (
                            <Command.Group heading="Comments">
                                {results.comments.map((comment) => (
                                    <Item
                                        key={`comment-${comment.id}`}
                                        value={`comment-${comment.id}`}
                                        onSelect={() => go(comment.url)}
                                        className="items-start"
                                    >
                                        <MessageSquare className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                        <span className="flex min-w-0 flex-col">
                                            <span className="truncate">
                                                {comment.snippet}
                                            </span>
                                            <span className="truncate text-xs text-muted-foreground">
                                                {comment.user?.name ??
                                                    'Someone'}
                                                {comment.is_internal &&
                                                    ' (internal note)'}{' '}
                                                on {comment.task.title} ·{' '}
                                                {comment.project.name}
                                            </span>
                                        </span>
                                    </Item>
                                ))}
                            </Command.Group>
                        )}
                    </Command.List>

                    <div className="flex items-center gap-3 border-t px-3 py-1.5 text-[11px] text-muted-foreground">
                        <span>↑↓ to move</span>
                        <span>↵ to open</span>
                        <span>esc to close</span>
                    </div>
                </Command>
            </DialogContent>
        </Dialog>
    );
}

function Item({
    className,
    ...props
}: React.ComponentProps<typeof Command.Item>) {
    return (
        <Command.Item
            className={cn(
                'flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-sm select-none data-[selected=true]:bg-accent data-[selected=true]:text-accent-foreground',
                className,
            )}
            {...props}
        />
    );
}

function Dot({ color }: { color: string }) {
    return (
        <span
            className="size-2.5 shrink-0 rounded-full"
            style={{ backgroundColor: color }}
        />
    );
}
