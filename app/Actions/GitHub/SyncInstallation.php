<?php

namespace App\Actions\GitHub;

use App\Models\GitHubInstallation;
use App\Models\GitHubRepository;
use Illuminate\Support\Facades\DB;

/**
 * Keeps our copy of an installation and its repositories in step with
 * GitHub's "installation" and "installation_repositories" webhooks.
 */
class SyncInstallation
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(string $event, string $action, array $payload): void
    {
        $account = $payload['installation']['account'] ?? [];
        $installationId = (int) ($payload['installation']['id'] ?? 0);

        if ($installationId === 0) {
            return;
        }

        if ($event === 'installation' && $action === 'deleted') {
            GitHubInstallation::where('installation_id', $installationId)->delete();

            return;
        }

        DB::transaction(function () use ($event, $action, $payload, $account, $installationId) {
            $installation = GitHubInstallation::updateOrCreate(['installation_id' => $installationId], [
                'account_login' => $account['login'] ?? null,
                'account_type' => $account['type'] ?? 'User',
                'account_avatar_url' => $account['avatar_url'] ?? null,
                ...match ($action) {
                    'suspend' => ['suspended_at' => now()],
                    'unsuspend', 'created' => ['suspended_at' => null],
                    default => [],
                },
            ]);

            if ($event === 'installation') {
                $this->addRepositories($installation, $payload['repositories'] ?? []);
            }

            if ($event === 'installation_repositories') {
                $this->addRepositories($installation, $payload['repositories_added'] ?? []);
                $this->removeRepositories($installation, $payload['repositories_removed'] ?? []);
            }
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $repositories
     */
    private function addRepositories(GitHubInstallation $installation, array $repositories): void
    {
        foreach ($repositories as $repository) {
            if (! isset($repository['id'], $repository['full_name'])) {
                continue;
            }

            GitHubRepository::updateOrCreate(['repository_id' => (int) $repository['id']], [
                'github_installation_id' => $installation->id,
                'full_name' => (string) $repository['full_name'],
                'private' => (bool) ($repository['private'] ?? false),
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $repositories
     */
    private function removeRepositories(GitHubInstallation $installation, array $repositories): void
    {
        $ids = array_map(fn (array $repository) => (int) ($repository['id'] ?? 0), $repositories);

        $installation->repositories()->whereIn('repository_id', array_filter($ids))->delete();
    }
}
