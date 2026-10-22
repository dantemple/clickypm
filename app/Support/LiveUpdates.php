<?php

namespace App\Support;

use App\Events\ProjectChanged;
use App\Events\WorkspaceChanged;
use App\Models\Project;
use Throwable;

/**
 * Collects what changed during a request (or command) and broadcasts one
 * "project changed" and one "workspace changed" message per project and
 * workspace when it finishes, however many rows were saved along the way.
 *
 * Messages skip the browser that made the change (via its X-Socket-ID).
 */
class LiveUpdates
{
    /**
     * Task ids touched per project id (an empty list means "the project itself").
     *
     * @var array<int, array<int, int>>
     */
    private array $projects = [];

    public function projectChanged(int $projectId, ?int $taskId = null): void
    {
        $this->projects[$projectId] ??= [];

        if ($taskId !== null && ! in_array($taskId, $this->projects[$projectId], true)) {
            $this->projects[$projectId][] = $taskId;
        }
    }

    public function flush(): void
    {
        $projects = $this->projects;
        $this->projects = [];

        if ($projects === []) {
            return;
        }

        try {
            foreach ($projects as $projectId => $taskIds) {
                broadcast(new ProjectChanged($projectId, $taskIds))->toOthers();
            }

            Project::withTrashed()
                ->whereKey(array_keys($projects))
                ->distinct()
                ->pluck('team_id')
                ->each(fn (int $teamId) => broadcast(new WorkspaceChanged($teamId))->toOthers());
        } catch (Throwable $exception) {
            // The change itself has been saved; a missed live update just
            // means people see it on their next refresh.
            report($exception);
        }
    }
}
