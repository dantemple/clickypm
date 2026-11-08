<?php

namespace App\Http\Resources;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Project
 */
class ProjectResource extends JsonResource
{
    /**
     * A project with its statuses and tags, so a caller can create and
     * update tasks without a second request.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->team_id,
            'name' => $this->name,
            'key' => $this->key,
            'color' => $this->color,
            'description' => $this->description,
            'hidden_from_clients' => $this->hidden_from_clients,
            'archived_at' => $this->archived_at?->toIso8601String(),
            'statuses' => StatusResource::collection($this->whenLoaded('statuses')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'url' => route('projects.show', [$this->team, $this->resource]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
