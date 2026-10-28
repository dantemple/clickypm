import { useForm, usePage } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
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
import { move } from '@/routes/projects';
import type { Project, Team } from '@/types';

/**
 * The other workspaces the viewer could move a project into: any they
 * belong to as more than a client.
 */
export function useMoveDestinations(): Team[] {
    const { teams } = usePage().props;

    return (teams ?? []).filter(
        (team) => !team.isCurrent && team.role !== 'client',
    );
}

export function MoveProjectDialog({
    project,
    open,
    onOpenChange,
}: {
    project: Project;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                {open && (
                    <MoveForm
                        project={project}
                        onDone={() => onOpenChange(false)}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

function MoveForm({
    project,
    onDone,
}: {
    project: Project;
    onDone: () => void;
}) {
    const destinations = useMoveDestinations();
    const form = useForm({ team: destinations[0]?.slug ?? '' });
    const destination = destinations.find(
        (team) => team.slug === form.data.team,
    );

    return (
        <form
            className="space-y-6"
            onSubmit={(event) => {
                event.preventDefault();
                // The project page is reused in the new workspace, so the
                // dialog has to close itself once the move goes through.
                form.post(move.url({ project: project.id }), {
                    onSuccess: onDone,
                });
            }}
        >
            <DialogHeader>
                <DialogTitle>Move project</DialogTitle>
                <DialogDescription>
                    Move “{project.name}” to another of your workspaces.
                </DialogDescription>
            </DialogHeader>

            <div className="grid gap-2">
                <Label htmlFor="move-team">Workspace</Label>
                <Select
                    value={form.data.team}
                    onValueChange={(value) => form.setData('team', value)}
                >
                    <SelectTrigger id="move-team" className="w-full">
                        <SelectValue placeholder="Choose a workspace" />
                    </SelectTrigger>
                    <SelectContent>
                        {destinations.map((team) => (
                            <SelectItem key={team.slug} value={team.slug}>
                                {team.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={form.errors.team} />
            </div>

            <div className="rounded-md border p-3 text-sm text-muted-foreground">
                Everything in the project comes along: tasks, statuses, views,
                comments and attachments. Custom fields are copied into the new
                workspace if it doesn’t already have them. Tasks assigned to
                people who aren’t in{' '}
                {destination ? `“${destination.name}”` : 'that workspace'} will
                become unassigned.
            </div>

            <DialogFooter>
                <Button
                    type="submit"
                    disabled={form.processing || !form.data.team}
                >
                    Move project
                </Button>
            </DialogFooter>
        </form>
    );
}
