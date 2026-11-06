<?php

namespace App\Http\Resources;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Task
 */
class TaskResource extends JsonResource
{
    /**
     * A task with its status, people and tags written out, so a caller
     * needn't look up ids. Counts and custom field values appear where the
     * controller loaded them.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->project->key.'-'.$this->number,
            'number' => $this->number,
            'project_id' => $this->project_id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => new StatusResource($this->status),
            'priority' => $this->priority ? strtolower($this->priority->name) : null,
            'assignee' => $this->assignee?->only(['id', 'name']),
            'creator' => $this->creator?->only(['id', 'name']),
            'due_date' => $this->due_date?->toDateString(),
            'start_date' => $this->start_date?->toDateString(),
            'is_milestone' => $this->is_milestone,
            'progress' => $this->progress,
            'needs_triage' => $this->needs_triage,
            'position' => $this->position,
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            // Keyed by custom field id.
            'custom_fields' => $this->when(
                $this->relationLoaded('customFieldValues'),
                fn () => (object) $this->customFieldValues->pluck('value', 'custom_field_id')->all(),
            ),
            'comments_count' => $this->whenCounted('comments'),
            'subtasks_count' => $this->whenCounted('subtasks'),
            'attachments_count' => $this->whenCounted('attachments'),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'url' => $this->url(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
