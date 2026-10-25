import { router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import ProfileAvatarController from '@/actions/App/Http/Controllers/Settings/ProfileAvatarController';
import InputError from '@/components/input-error';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { useInitials } from '@/hooks/use-initials';
import type { Auth } from '@/types';

const ACCEPTED_TYPES = 'image/png,image/jpeg,image/gif,image/webp';

/**
 * The profile photo with upload and remove controls. Picking a file sends
 * it straight away; the chosen image is shown while it goes up so the page
 * doesn't flicker back to initials.
 */
export default function ProfileAvatar() {
    const { auth } = usePage<{ auth: Auth }>().props;
    const getInitials = useInitials();
    const inputRef = useRef<HTMLInputElement>(null);
    const [preview, setPreview] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | undefined>();

    useEffect(() => {
        return () => {
            if (preview) {
                URL.revokeObjectURL(preview);
            }
        };
    }, [preview]);

    const upload = (file: File) => {
        setError(undefined);
        setPreview(URL.createObjectURL(file));

        router.post(
            ProfileAvatarController.store.url(),
            { avatar: file },
            {
                forceFormData: true,
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onError: (errors) => setError(errors.avatar),
                onFinish: () => {
                    setProcessing(false);
                    setPreview(null);
                },
            },
        );
    };

    const remove = () => {
        setError(undefined);

        router.delete(ProfileAvatarController.destroy.url(), {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
        });
    };

    const src = preview ?? auth.user.avatar ?? undefined;

    return (
        <div className="grid gap-2">
            <span className="text-sm font-medium">Photo</span>

            <div className="flex items-center gap-4">
                <Avatar className="size-16 rounded-lg">
                    {src ? (
                        <AvatarImage src={src} alt={auth.user.name} />
                    ) : null}
                    <AvatarFallback className="rounded-lg text-lg text-black dark:text-white">
                        {getInitials(auth.user.name)}
                    </AvatarFallback>
                </Avatar>

                <div className="space-y-2">
                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={processing}
                            onClick={() => inputRef.current?.click()}
                            data-test="upload-avatar-button"
                        >
                            {auth.user.avatar ? 'Change photo' : 'Upload photo'}
                        </Button>

                        {auth.user.avatar ? (
                            <Button
                                type="button"
                                variant="ghost"
                                disabled={processing}
                                onClick={remove}
                                data-test="remove-avatar-button"
                            >
                                Remove
                            </Button>
                        ) : null}
                    </div>

                    <p className="text-xs text-muted-foreground">
                        PNG, JPEG, GIF or WebP, up to 2 MB.
                    </p>
                </div>
            </div>

            <input
                ref={inputRef}
                type="file"
                name="avatar"
                accept={ACCEPTED_TYPES}
                className="sr-only"
                tabIndex={-1}
                aria-label="Choose a profile photo"
                onChange={(event) => {
                    const file = event.target.files?.[0];

                    if (file) {
                        upload(file);
                    }

                    // Let the same file be picked again after a failed upload.
                    event.target.value = '';
                }}
            />

            <InputError message={error} />
        </div>
    );
}
