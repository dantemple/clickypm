import { Link, router } from '@inertiajs/react';
import { ChevronDown, List, Pencil, Plus, Sheet, Trash2 } from 'lucide-react';
import { useState } from 'react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import { renameView } from '@/lib/views';
import {
    destroy as destroyViewRoute,
    show as showViewRoute,
    store as storeViewRoute,
} from '@/routes/projects/views';
import type { Project, ProjectView, ViewType } from '@/types';

export const VIEW_ICONS: Record<ViewType, typeof List> = {
    list: List,
    table: Sheet,
};

export function ViewTabs({
    project,
    views,
    current,
    canEdit,
}: {
    project: Project;
    views: ProjectView[];
    current: ProjectView;
    canEdit: boolean;
}) {
    const [renaming, setRenaming] = useState<number | null>(null);

    const addView = (type: ViewType) =>
        router.post(storeViewRoute.url({ project: project.id }), { type });

    const deleteView = (view: ProjectView) => {
        if (
            confirm(`Delete the “${view.name}” view? Tasks are not affected.`)
        ) {
            router.delete(
                destroyViewRoute.url({ project: project.id, view: view.id }),
            );
        }
    };

    return (
        <div className="flex items-center gap-1 overflow-x-auto border-b px-4">
            {views.map((view) => {
                const Icon = VIEW_ICONS[view.type];
                const active = view.id === current.id;

                return (
                    <div
                        key={view.id}
                        className={cn(
                            '-mb-px flex shrink-0 items-center border-b-2 text-sm',
                            active
                                ? 'border-primary font-medium text-foreground'
                                : 'border-transparent text-muted-foreground hover:text-foreground',
                        )}
                    >
                        {renaming === view.id ? (
                            <RenameInput
                                view={view}
                                onDone={(name) => {
                                    setRenaming(null);

                                    if (name && name !== view.name) {
                                        renameView(project.id, view, name);
                                    }
                                }}
                            />
                        ) : (
                            <Link
                                href={showViewRoute.url({
                                    project: project.id,
                                    view: view.id,
                                })}
                                className="flex items-center gap-1.5 px-2 py-2.5"
                                preserveScroll
                                onDoubleClick={() =>
                                    canEdit && setRenaming(view.id)
                                }
                            >
                                <Icon className="size-4" />
                                {view.name}
                            </Link>
                        )}

                        {active && canEdit && renaming !== view.id && (
                            <DropdownMenu>
                                <DropdownMenuTrigger
                                    className="rounded p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                                    aria-label="View options"
                                >
                                    <ChevronDown className="size-3.5" />
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="start">
                                    <DropdownMenuItem
                                        onSelect={() => setRenaming(view.id)}
                                    >
                                        <Pencil /> Rename
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        variant="destructive"
                                        disabled={views.length <= 1}
                                        onSelect={() => deleteView(view)}
                                    >
                                        <Trash2 /> Delete view
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        )}
                    </div>
                );
            })}

            {canEdit && (
                <DropdownMenu>
                    <DropdownMenuTrigger className="flex shrink-0 items-center gap-1 rounded px-2 py-1.5 text-sm text-muted-foreground hover:bg-muted hover:text-foreground">
                        <Plus className="size-4" /> View
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" className="w-44">
                        <DropdownMenuLabel className="text-xs text-muted-foreground">
                            Add a view
                        </DropdownMenuLabel>
                        {(Object.keys(VIEW_ICONS) as ViewType[]).map((type) => {
                            const Icon = VIEW_ICONS[type];

                            return (
                                <DropdownMenuItem
                                    key={type}
                                    onSelect={() => addView(type)}
                                >
                                    <Icon />
                                    <span className="capitalize">{type}</span>
                                </DropdownMenuItem>
                            );
                        })}
                    </DropdownMenuContent>
                </DropdownMenu>
            )}
        </div>
    );
}

function RenameInput({
    view,
    onDone,
}: {
    view: ProjectView;
    onDone: (name: string | null) => void;
}) {
    const [name, setName] = useState(view.name);

    return (
        <input
            autoFocus
            value={name}
            aria-label="View name"
            className="my-1.5 h-7 w-32 rounded border bg-background px-2 text-sm outline-none focus:ring-2 focus:ring-ring"
            onFocus={(event) => event.currentTarget.select()}
            onChange={(event) => setName(event.target.value)}
            onBlur={() => onDone(name.trim() || null)}
            onKeyDown={(event) => {
                if (event.key === 'Enter') {
                    event.currentTarget.blur();
                }

                if (event.key === 'Escape') {
                    onDone(null);
                }
            }}
        />
    );
}
