<?php

namespace App\Http\Resources;

use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Team
 */
class WorkspaceResource extends JsonResource
{
    /**
     * A workspace as the caller sees it, with their role in it.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'is_personal' => $this->is_personal,
            'role' => $request->user()?->teamRole($this->resource)?->value,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
