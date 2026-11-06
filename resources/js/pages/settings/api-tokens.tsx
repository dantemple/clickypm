import { Head, router, useForm } from '@inertiajs/react';
import { Check, Copy, KeyRound, Trash2 } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import ApiTokenController from '@/actions/App/Http/Controllers/Settings/ApiTokenController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useClipboard } from '@/hooks/use-clipboard';
import { index } from '@/routes/api-tokens';
import type { ApiToken } from '@/types';

type Props = {
    tokens: ApiToken[];
    /** The token just made, shown in full this once. */
    newToken: string | null;
    /** The lifetimes on offer, in days. */
    lifetimes: number[];
    apiUrl: string;
};

export default function ApiTokens({
    tokens,
    newToken,
    lifetimes,
    apiUrl,
}: Props) {
    return (
        <>
            <Head title="API tokens" />

            <h1 className="sr-only">API tokens</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="API tokens"
                    description="Let scripts and tools act as you through the REST API"
                />

                {newToken && <NewToken token={newToken} apiUrl={apiUrl} />}

                <CreateTokenForm lifetimes={lifetimes} />

                <div className="overflow-hidden rounded-lg border border-border">
                    {tokens.length > 0 ? (
                        tokens.map((token) => (
                            <TokenItem key={token.id} token={token} />
                        ))
                    ) : (
                        <EmptyState />
                    )}
                </div>
            </div>

            <div className="space-y-4">
                <Heading
                    variant="small"
                    title="Using the API"
                    description="Send the token as a bearer token with every request"
                />

                <pre className="overflow-x-auto rounded-lg bg-muted p-4 font-mono text-xs leading-relaxed">
                    {`curl -H "Authorization: Bearer YOUR_TOKEN" \\\n     -H "Accept: application/json" \\\n     ${apiUrl}/me`}
                </pre>

                <ul className="space-y-1 text-sm text-muted-foreground">
                    <li>
                        <code className="text-foreground">GET /me</code>,{' '}
                        <code className="text-foreground">GET /workspaces</code>
                        ,{' '}
                        <code className="text-foreground">
                            GET /workspaces/:slug/members
                        </code>
                    </li>
                    <li>
                        <code className="text-foreground">
                            GET|POST /workspaces/:slug/projects
                        </code>
                        ,{' '}
                        <code className="text-foreground">
                            GET|PATCH /projects/:id
                        </code>
                    </li>
                    <li>
                        <code className="text-foreground">
                            GET|POST /projects/:id/tasks
                        </code>
                        ,{' '}
                        <code className="text-foreground">
                            GET|PATCH|DELETE /tasks/:id
                        </code>
                        ,{' '}
                        <code className="text-foreground">
                            GET /workspaces/:slug/tasks/WEB-42
                        </code>
                    </li>
                    <li>
                        <code className="text-foreground">
                            GET|POST /tasks/:id/comments
                        </code>
                    </li>
                </ul>
            </div>
        </>
    );
}

function NewToken({ token, apiUrl }: { token: string; apiUrl: string }) {
    const [copiedText, copy] = useClipboard();
    const copied = copiedText === token;

    return (
        <Alert className="border-primary/40 bg-primary/5">
            <KeyRound className="h-4 w-4" />
            <AlertTitle>Copy your new token now</AlertTitle>
            <AlertDescription className="space-y-3">
                <p>
                    It won't be shown again. Anyone who has it can do what you
                    can in the app, so keep it secret.
                </p>
                <div className="flex w-full items-center gap-2">
                    <code
                        className="min-w-0 flex-1 truncate rounded-md border bg-background px-3 py-2 font-mono text-xs"
                        data-test="new-api-token"
                    >
                        {token}
                    </code>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => copy(token)}
                    >
                        {copied ? (
                            <Check className="h-4 w-4" />
                        ) : (
                            <Copy className="h-4 w-4" />
                        )}
                        {copied ? 'Copied' : 'Copy'}
                    </Button>
                </div>
                <p className="text-xs">
                    Try it:{' '}
                    <code className="font-mono">
                        curl -H "Authorization: Bearer …" {apiUrl}/me
                    </code>
                </p>
            </AlertDescription>
        </Alert>
    );
}

function CreateTokenForm({ lifetimes }: { lifetimes: number[] }) {
    const { data, setData, post, processing, errors, reset } = useForm<{
        name: string;
        expires_in_days: number | null;
        read_only: boolean;
    }>({
        name: '',
        expires_in_days: null,
        read_only: false,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(ApiTokenController.store.url(), {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    return (
        <form onSubmit={submit} className="space-y-4 rounded-lg border p-4">
            <div className="grid gap-2">
                <Label htmlFor="token-name">Name</Label>
                <Input
                    id="token-name"
                    name="name"
                    value={data.name}
                    onChange={(event) => setData('name', event.target.value)}
                    placeholder="What will use it, e.g. Claude on my laptop"
                    maxLength={255}
                    required
                />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-2 sm:grid-cols-2 sm:gap-4">
                <div className="grid gap-2">
                    <Label htmlFor="token-expiry">Expires</Label>
                    <Select
                        value={
                            data.expires_in_days === null
                                ? 'never'
                                : String(data.expires_in_days)
                        }
                        onValueChange={(value) =>
                            setData(
                                'expires_in_days',
                                value === 'never' ? null : Number(value),
                            )
                        }
                    >
                        <SelectTrigger id="token-expiry" className="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="never">Never</SelectItem>
                            {lifetimes.map((days) => (
                                <SelectItem key={days} value={String(days)}>
                                    In {days} days
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.expires_in_days} />
                </div>

                <div className="flex items-start gap-3 sm:pt-7">
                    <Checkbox
                        id="token-read-only"
                        name="read_only"
                        checked={data.read_only}
                        onCheckedChange={(value) =>
                            setData('read_only', value === true)
                        }
                        className="mt-0.5"
                    />
                    <div className="grid gap-1">
                        <Label htmlFor="token-read-only">Read only</Label>
                        <p className="text-sm text-muted-foreground">
                            Can look at everything but change nothing.
                        </p>
                    </div>
                </div>
            </div>

            <Button
                disabled={processing || data.name.trim() === ''}
                data-test="create-api-token-button"
            >
                Create token
            </Button>
        </form>
    );
}

function TokenItem({ token }: { token: ApiToken }) {
    const [isRevoking, setIsRevoking] = useState(false);

    const revoke = () => {
        setIsRevoking(true);
        router.delete(ApiTokenController.destroy.url(token.id), {
            preserveScroll: true,
            onError: () => setIsRevoking(false),
        });
    };

    return (
        <div className="flex items-center justify-between gap-4 border-b p-4 last:border-b-0">
            <div className="flex min-w-0 items-center gap-4">
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-muted">
                    <KeyRound className="h-5 w-5 text-muted-foreground" />
                </div>
                <div className="min-w-0 space-y-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <p className="truncate font-medium tracking-tight">
                            {token.name}
                        </p>
                        {token.read_only && (
                            <Badge variant="secondary">Read only</Badge>
                        )}
                        {token.expired && (
                            <Badge variant="destructive">Expired</Badge>
                        )}
                    </div>
                    <p className="text-sm text-muted-foreground">
                        Created {token.created_at_diff}
                        <span className="mx-1 text-muted-foreground/50">/</span>
                        {token.last_used_at_diff
                            ? `Last used ${token.last_used_at_diff}`
                            : 'Never used'}
                        {token.expires_at && (
                            <>
                                <span className="mx-1 text-muted-foreground/50">
                                    /
                                </span>
                                {token.expired ? 'Expired' : 'Expires'}{' '}
                                {token.expires_at}
                            </>
                        )}
                    </p>
                </div>
            </div>

            <Dialog>
                <DialogTrigger asChild>
                    <Button
                        variant="ghost"
                        size="sm"
                        className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                    >
                        <Trash2 className="h-4 w-4" />
                        <span className="sr-only">Revoke</span>
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogTitle>Revoke token</DialogTitle>
                    <DialogDescription>
                        Anything still using "{token.name}" will stop working
                        straight away. This can't be undone.
                    </DialogDescription>
                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button variant="secondary">Cancel</Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            onClick={revoke}
                            disabled={isRevoking}
                        >
                            {isRevoking ? 'Revoking...' : 'Revoke token'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}

function EmptyState() {
    return (
        <div className="p-8 text-center">
            <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-muted">
                <KeyRound className="h-7 w-7 text-muted-foreground" />
            </div>
            <p className="font-medium">No tokens yet</p>
            <p className="mt-1 text-sm text-muted-foreground">
                Create one to use the API from a script or an AI assistant
            </p>
        </div>
    );
}

ApiTokens.layout = {
    breadcrumbs: [
        {
            title: 'API tokens',
            href: index(),
        },
    ],
};
