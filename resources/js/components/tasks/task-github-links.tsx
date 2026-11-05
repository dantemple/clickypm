import {
    GitCommitHorizontal,
    GitMerge,
    GitPullRequest,
    GitPullRequestClosed,
    GitPullRequestDraft,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import type { GitHubLink } from '@/types';

const STATE_LABELS: Record<NonNullable<GitHubLink['state']>, string> = {
    open: 'Open',
    draft: 'Draft',
    merged: 'Merged',
    closed: 'Closed',
};

function LinkIcon({ link }: { link: GitHubLink }) {
    const className = 'size-4 shrink-0';

    if (link.type === 'commit') {
        return (
            <GitCommitHorizontal
                className={cn(className, 'text-muted-foreground')}
            />
        );
    }

    switch (link.state) {
        case 'merged':
            return <GitMerge className={cn(className, 'text-purple-500')} />;
        case 'closed':
            return (
                <GitPullRequestClosed
                    className={cn(className, 'text-red-500')}
                />
            );
        case 'draft':
            return (
                <GitPullRequestDraft
                    className={cn(className, 'text-muted-foreground')}
                />
            );
        default:
            return (
                <GitPullRequest className={cn(className, 'text-green-600')} />
            );
    }
}

/**
 * The pull requests and commits on GitHub that mention this task. Part of
 * the GitHub module; the panel only shows once there is something to list.
 */
export function TaskGitHubLinks({ links }: { links: GitHubLink[] }) {
    return (
        <section className="flex flex-col gap-2 border-t px-4 py-3">
            <h3 className="text-sm font-medium">Development</h3>
            <ul className="flex flex-col gap-1">
                {links.map((link) => (
                    <li
                        key={link.id}
                        className="flex items-start gap-2 rounded px-1 py-1 text-sm hover:bg-muted/50"
                    >
                        <LinkIcon link={link} />
                        <span className="min-w-0 flex-1">
                            <a
                                href={link.url}
                                target="_blank"
                                rel="noreferrer"
                                className="hover:underline"
                            >
                                <span className="font-mono text-xs text-muted-foreground">
                                    {link.type === 'commit'
                                        ? link.external_id.slice(0, 7)
                                        : `#${link.external_id}`}
                                </span>{' '}
                                {link.title}
                            </a>
                            <span className="block truncate text-xs text-muted-foreground">
                                {link.repository}
                                {link.author && ` · ${link.author}`}
                                {link.state && ` · ${STATE_LABELS[link.state]}`}
                            </span>
                        </span>
                    </li>
                ))}
            </ul>
        </section>
    );
}
