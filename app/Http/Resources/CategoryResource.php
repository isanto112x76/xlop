<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'parent_id' => $this->parent_id,
            'baselinker_category_id' => $this->baselinker_category_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'parent' => new CategoryResource($this->whenLoaded('parent')),
            'children_count' => $this->whenCounted('children'),
            'children' => CategoryResource::collection($this->whenLoaded('childrenRecursive')),
        ];
    }
}
