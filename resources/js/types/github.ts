/** A repository the workspace's GitHub App installation can see. */
export type GitHubRepository = {
    id: number;
    full_name: string;
    private: boolean;
    url: string;
};

/** The GitHub App installed for a workspace. */
export type GitHubInstallation = {
    id: number;
    account_login: string | null;
    account_type: string;
    account_avatar_url: string | null;
    suspended_at: string | null;
    manage_url: string;
    repositories: GitHubRepository[];
};

/** The GitHub module's state for the workspace settings page. */
export type GitHubConnection = {
    /** Whether this server has a GitHub App to offer at all. */
    configured: boolean;
    installation: GitHubInstallation | null;
};

/** A project's GitHub settings, for the project's GitHub dialog. */
export type ProjectGitHubSettings = {
    connected: boolean;
    repositories: GitHubRepository[];
    repository_ids: number[];
    opened_status_id: number | null;
    merged_status_id: number | null;
};

export type GitHubLinkType = 'pull_request' | 'commit';

export type GitHubLinkState = 'open' | 'draft' | 'merged' | 'closed';

/** A pull request or commit that mentioned the task's key. */
export type GitHubLink = {
    id: number;
    type: GitHubLinkType;
    repository: string;
    /** The pull request number, or the commit sha. */
    external_id: string;
    title: string;
    url: string;
    state: GitHubLinkState | null;
    author: string | null;
    author_avatar_url: string | null;
    updated_at: string;
};
