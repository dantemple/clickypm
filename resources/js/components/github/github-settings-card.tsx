import { router } from '@inertiajs/react';
import { ExternalLink, Lock } from 'lucide-react';
import Heading from '@/components/heading';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { connect, destroy } from '@/routes/teams/github';
import type { GitHubConnection, Team } from '@/types';

/**
 * The GitHub module's card in the workspace settings: connect the GitHub
 * App, see which repositories it can reach, or disconnect it.
 */
export function GitHubSettingsCard({
    team,
    github,
    canManage,
}: {
    team: Team;
    github: GitHubConnection;
    canManage: boolean;
}) {
    const installation = github.installation;

    const disconnect = () => {
        if (
            confirm(
                'Disconnect GitHub from this workspace? Pull requests and commits already on tasks stay; new ones stop arriving.',
            )
        ) {
            router.delete(destroy.url(team.slug), { preserveScroll: true });
        }
    };

    return (
        <div className="space-y-6" data-test="github-card">
            <Heading
                variant="small"
                title="GitHub"
                description="Pull requests and commits that mention a task key, like WEB-42, are shown on the task."
            />

            {!github.configured ? (
                <p className="rounded-lg border p-4 text-sm text-muted-foreground">
                    This server doesn’t have a GitHub App set up yet. Whoever
                    hosts it needs to add GITHUB_APP_SLUG and
                    GITHUB_WEBHOOK_SECRET (see .env.example).
                </p>
            ) : !installation ? (
                <div className="flex flex-col gap-4 rounded-lg border p-4 sm:flex-row sm:items-center sm:justify-between">
                    <p className="text-sm text-muted-foreground">
                        Install the app on your GitHub account or organisation
                        and choose which repositories it can see. Each project
                        then picks the repositories it follows.
                    </p>
                    {canManage && (
                        <Button asChild className="shrink-0">
                            <a
                                href={connect.url(team.slug)}
                                data-test="github-connect"
                            >
                                Connect GitHub
                            </a>
                        </Button>
                    )}
                </div>
            ) : (
                <div className="space-y-4 rounded-lg border p-4">
                    <div className="flex items-center justify-between gap-4">
                        <div className="flex items-center gap-3">
                            <Avatar className="h-10 w-10 rounded-md">
                                {installation.account_avatar_url && (
                                    <AvatarImage
                                        src={installation.account_avatar_url}
                                        alt=""
                                    />
                                )}
                                <AvatarFallback className="rounded-md">
                                    GH
                                </AvatarFallback>
                            </Avatar>
                            <div>
                                <div className="font-medium">
                                    {installation.account_login ??
                                        'Connecting…'}
                                </div>
                                <div className="text-sm text-muted-foreground">
                                    {installation.suspended_at
                                        ? 'Suspended on GitHub'
                                        : installation.account_type ===
                                            'Organization'
                                          ? 'GitHub organisation'
                                          : 'GitHub account'}
                                </div>
                            </div>
                        </div>
                        <div className="flex items-center gap-2">
                            {installation.account_login && (
                                <Button variant="outline" size="sm" asChild>
                                    <a
                                        href={installation.manage_url}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        Manage on GitHub <ExternalLink />
                                    </a>
                                </Button>
                            )}
                            {canManage && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="text-destructive hover:text-destructive"
                                    data-test="github-disconnect"
                                    onClick={disconnect}
                                >
                                    Disconnect
                                </Button>
                            )}
                        </div>
                    </div>

                    {!installation.account_login ? (
                        <p className="text-sm text-muted-foreground">
                            Waiting for GitHub to tell us about this
                            installation. That arrives by webhook a moment after
                            installing; if it never does, check the app’s Recent
                            Deliveries on GitHub for a failed delivery, and that
                            a queue worker is running.
                        </p>
                    ) : installation.repositories.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No repositories yet. Choose some under “Manage on
                            GitHub” and they’ll appear here a moment later.
                        </p>
                    ) : (
                        <ul className="grid gap-1 text-sm sm:grid-cols-2">
                            {installation.repositories.map((repository) => (
                                <li
                                    key={repository.id}
                                    className="flex items-center gap-1.5"
                                >
                                    <a
                                        href={repository.url}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="truncate hover:underline"
                                    >
                                        {repository.full_name}
                                    </a>
                                    {repository.private && (
                                        <Lock className="size-3 shrink-0 text-muted-foreground" />
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            )}
        </div>
    );
}
