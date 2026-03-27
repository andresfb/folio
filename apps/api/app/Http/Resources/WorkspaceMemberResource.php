<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\WorkspaceMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/** @mixin WorkspaceMember */
final class WorkspaceMemberResource extends JsonApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'role' => $this->role,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'workspace_id' => $this->workspace_id,

            'member' => new UserResource($this->whenLoaded('member')),
            'workspace' => new WorkspaceResource($this->whenLoaded('workspace')),
        ];
    }
}
