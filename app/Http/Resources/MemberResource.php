<?php

namespace App\Http\Resources;

use App\Models\Membership;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class MemberResource extends JsonResource
{
    /**
     * Someone on a workspace, with their role there. Built from the
     * workspace's members relation, which carries the membership pivot.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Membership|null $membership */
        $membership = $this->getRelation('pivot');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar' => $this->avatar,
            'role' => $membership?->role->value,
        ];
    }
}
