<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockLevelResource extends JsonResource
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
            'product_variant_id' => $this->product_variant_id,
            'warehouse_id' => $this->warehouse_id,
            'warehouse' => new WarehouseResource($this->whenLoaded('warehouse')), // Szczegóły magazynu
            'quantity' => $this->quantity,
            'reserved_quantity' => $this->reserved_quantity,
            'incoming_quantity' => $this->incoming_quantity,
            'location' => $this->location,
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
