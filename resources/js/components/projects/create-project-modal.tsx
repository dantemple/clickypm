import { Form } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { ColorSwatches } from '@/components/projects/color-swatches';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PROJECT_COLORS } from '@/lib/tasks';
import { store } from '@/routes/projects';

export default function CreateProjectModal({ children }: PropsWithChildren) {
    const [open, setOpen] = useState(false);
    const [color, setColor] = useState(PROJECT_COLORS[0]);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent>
                <Form
                    key={String(open)}
                    {...store.form()}
                    className="space-y-6"
                    onSuccess={() => setOpen(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Create a project</DialogTitle>
                                <DialogDescription>
                                    Projects hold tasks and come with a List and
                                    a Table view.
                                </DialogDescription>
                            </DialogHeader>

                            <div className="grid gap-2">
                                <Label htmlFor="project-name">Name</Label>
                                <Input
                                    id="project-name"
                                    name="name"
                                    placeholder="e.g. Website relaunch"
                                    required
                                    autoFocus
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label>Colour</Label>
                                <ColorSwatches
                                    name="color"
                                    value={color}
                                    onChange={setColor}
                                />
                                <InputError message={errors.color} />
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    Create project
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
