<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ProjectNode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProjectNode */
final class ProjectNodeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'node_type' => $this->node_type,
            'slug' => $this->slug,
            'title' => $this->title,
            'sort_index' => $this->sort_index,
            'depth' => $this->depth,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'workspace_id' => $this->workspace_id,
            'project_id' => $this->project_id,
            'user_id' => $this->user_id,
            'parent_id' => $this->parent_id,

            'workspace' => new WorkspaceResource($this->whenLoaded('workspace')),
            'project' => new ProjectResource($this->whenLoaded('project')),
            'user' => new UserResource($this->whenLoaded('user')),
            'parent' => new self($this->whenLoaded('parent')),
        ];
    }
}
