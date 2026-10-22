import { Head } from '@inertiajs/react';
import TextLink from '@/components/text-link';
import { logout } from '@/routes';

export default function NoWorkspace() {
    return (
        <>
            <Head title="No workspace" />

            <div className="space-y-6 text-center text-sm text-muted-foreground">
                <p>
                    Ask the person who invited you to send a new invitation to
                    this email address. Once you accept it, you’ll land straight
                    in their workspace.
                </p>

                <TextLink href={logout()} className="mx-auto block">
                    Log out
                </TextLink>
            </div>
        </>
    );
}

NoWorkspace.layout = {
    title: 'You’re not in a workspace',
    description: 'Your access to your last workspace was removed.',
};
