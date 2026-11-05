import { Link, useForm, usePage } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { update } from '@/routes/projects/github';
import { edit as editTeam } from '@/routes/teams';
import type { Project, ProjectGitHubSettings, Status } from '@/types';

/** Which repositories the project follows, and what pull requests do to its tasks. */
export function GitHubDialog({
    project,
    statuses,
    github,
    open,
    onOpenChange,
}: {
    project: Project;
    statuses: Status[];
    github: ProjectGitHubSettings;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                {open && (
                    <GitHubForm
                        project={project}
                        statuses={statuses}
                        github={github}
                        onDone={() => onOpenChange(false)}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

const NONE = 'none';

function GitHubForm({
    project,
    statuses,
    github,
    onDone,
}: {
    project: Project;
    statuses: Status[];
    github: ProjectGitHubSettings;
    onDone: () => void;
}) {
    const teamSlug = usePage().props.currentTeam?.slug ?? '';
    const form = useForm({
        repository_ids: github.repository_ids,
        opened_status_id: github.opened_status_id,
        merged_status_id: github.merged_status_id,
    });

    const toggle = (id: number, checked: boolean) =>
        form.setData(
            'repository_ids',
            checked
                ? [...form.data.repository_ids, id]
                : form.data.repository_ids.filter((other) => other !== id),
        );

    const statusSelect = (
        field: 'opened_status_id' | 'merged_status_id',
        label: string,
    ) => (
        <div className="grid gap-2">
            <Label htmlFor={`github-${field}`}>{label}</Label>
            <Select
                value={form.data[field] ? String(form.data[field]) : NONE}
                onValueChange={(value) =>
                    form.setData(field, value === NONE ? null : Number(value))
                }
            >
                <SelectTrigger id={`github-${field}`}>
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={NONE}>Don’t move them</SelectItem>
                    {statuses.map((status) => (
                        <SelectItem key={status.id} value={String(status.id)}>
                            <span className="flex items-center gap-2">
                                <span
                                    className="size-2.5 rounded-sm"
                                    style={{ backgroundColor: status.color }}
                                />
                                {status.name}
                            </span>
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <InputError message={form.errors[field]} />
        </div>
    );

    return (
        <form
            className="space-y-6"
            onSubmit={(event) => {
                event.preventDefault();
                form.put(update.url({ project: project.id }), {
                    preserveScroll: true,
                    onSuccess: onDone,
                });
            }}
        >
            <DialogHeader>
                <DialogTitle>GitHub</DialogTitle>
                <DialogDescription>
                    Pull requests and commits anywhere in the workspace’s
                    repositories link to tasks they mention by key. Only the
                    repositories this project follows move its tasks.
                </DialogDescription>
            </DialogHeader>

            {!github.connected ? (
                <p className="rounded-md border p-3 text-sm text-muted-foreground">
                    GitHub isn’t connected to this workspace yet.{' '}
                    <Link
                        href={editTeam(teamSlug)}
                        className="text-foreground underline"
                    >
                        Connect it in the workspace settings
                    </Link>
                    .
                </p>
            ) : github.repositories.length === 0 ? (
                <p className="rounded-md border p-3 text-sm text-muted-foreground">
                    The GitHub App can’t see any repositories yet. Choose some
                    on GitHub, under the app’s settings.
                </p>
            ) : (
                <div className="grid gap-2">
                    <Label>Repositories this project follows</Label>
                    <ul className="max-h-60 divide-y overflow-y-auto rounded-md border">
                        {github.repositories.map((repository) => (
                            <li key={repository.id}>
                                <label className="flex items-center gap-3 px-3 py-2 text-sm">
                                    <Checkbox
                                        checked={form.data.repository_ids.includes(
                                            repository.id,
                                        )}
                                        onCheckedChange={(checked) =>
                                            toggle(
                                                repository.id,
                                                checked === true,
                                            )
                                        }
                                    />
                                    <span className="truncate">
                                        {repository.full_name}
                                    </span>
                                    {repository.private && (
                                        <Lock className="size-3 shrink-0 text-muted-foreground" />
                                    )}
                                </label>
                            </li>
                        ))}
                    </ul>
                    <InputError message={form.errors.repository_ids} />
                </div>
            )}

            {statusSelect(
                'opened_status_id',
                'When a pull request opens, move its tasks to',
            )}
            {statusSelect(
                'merged_status_id',
                'When a pull request merges, move its tasks to',
            )}

            <DialogFooter>
                <Button type="submit" disabled={form.processing}>
                    Save
                </Button>
            </DialogFooter>
        </form>
    );
}
