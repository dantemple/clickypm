import { Head, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import NotificationController from '@/actions/App/Http/Controllers/Settings/NotificationController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/notifications';
import type { Auth } from '@/types';

type PageProps = {
    auth: Auth;
};

export default function Notifications() {
    const { auth } = usePage<PageProps>().props;
    const { data, setData, put, processing, errors } = useForm({
        task_emails: auth.user.task_emails,
        reminder_emails: auth.user.reminder_emails,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        put(NotificationController.update.url(), { preserveScroll: true });
    };

    return (
        <>
            <Head title="Notification settings" />

            <h1 className="sr-only">Notification settings</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Email notifications"
                    description="Choose which emails you receive about your work"
                />

                <form onSubmit={submit} className="space-y-6">
                    <Setting
                        id="task_emails"
                        title="Task activity"
                        description="When a task is assigned to you, someone comments on a task you follow, or a client sends a request."
                        checked={data.task_emails}
                        onChange={(checked) => setData('task_emails', checked)}
                        error={errors.task_emails}
                    />

                    <Setting
                        id="reminder_emails"
                        title="Daily reminder"
                        description="A morning email listing your tasks that are due today or overdue. Only sent on days you have some."
                        checked={data.reminder_emails}
                        onChange={(checked) =>
                            setData('reminder_emails', checked)
                        }
                        error={errors.reminder_emails}
                    />

                    <div className="flex items-center gap-4">
                        <Button
                            disabled={processing}
                            data-test="update-notifications-button"
                        >
                            Save
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

function Setting({
    id,
    title,
    description,
    checked,
    onChange,
    error,
}: {
    id: string;
    title: string;
    description: string;
    checked: boolean;
    onChange: (checked: boolean) => void;
    error?: string;
}) {
    return (
        <div className="flex items-start gap-3">
            <Checkbox
                id={id}
                name={id}
                checked={checked}
                onCheckedChange={(value) => onChange(value === true)}
                className="mt-0.5"
            />
            <div className="grid gap-1">
                <Label htmlFor={id}>{title}</Label>
                <p className="text-sm text-muted-foreground">{description}</p>
                <InputError message={error} />
            </div>
        </div>
    );
}

Notifications.layout = {
    breadcrumbs: [
        {
            title: 'Notification settings',
            href: edit(),
        },
    ],
};
