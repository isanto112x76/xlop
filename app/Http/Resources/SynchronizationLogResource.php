<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SynchronizationLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'direction' => $this->direction,
            'resource_type' => $this->resource_type,
            'status' => $this->status,
            'message' => $this->message,
            'local_id' => $this->local_id,
            'external_id' => $this->external_id,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
