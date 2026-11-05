<?php

namespace App\Jobs;

use App\Actions\GitHub\LinkCommits;
use App\Actions\GitHub\LinkPullRequest;
use App\Actions\GitHub\SyncInstallation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Handles one GitHub webhook delivery, off the request so GitHub gets its
 * answer straight away. Anything we don't recognise is ignored.
 */
class ProcessGitHubWebhook implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $event,
        public array $payload,
    ) {}

    public function handle(SyncInstallation $installation, LinkPullRequest $pullRequest, LinkCommits $commits): void
    {
        $action = (string) ($this->payload['action'] ?? '');

        match ($this->event) {
            'installation', 'installation_repositories' => $installation->handle($this->event, $action, $this->payload),
            'pull_request' => in_array($action, ['opened', 'reopened', 'edited', 'closed', 'ready_for_review', 'converted_to_draft'], true)
                ? $pullRequest->handle($action, $this->payload)
                : null,
            'push' => $commits->handle($this->payload),
            default => null,
        };
    }
}
