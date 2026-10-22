<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Something in a project changed. Carries no content, only which tasks were
 * touched, so viewers re-fetch through the normal permission-checked routes.
 */
class ProjectChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  array<int, int>  $taskIds
     */
    public function __construct(public int $projectId, public array $taskIds = [])
    {
        //
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("projects.{$this->projectId}")];
    }

    public function broadcastAs(): string
    {
        return 'project.changed';
    }

    /**
     * @return array{taskIds: array<int, int>}
     */
    public function broadcastWith(): array
    {
        return ['taskIds' => $this->taskIds];
    }
}
