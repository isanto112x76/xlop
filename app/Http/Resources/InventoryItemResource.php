<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'expected_quantity' => (float) $this->expected_quantity,
            'counted_quantity' => (float) $this->counted_quantity,
            'difference' => (float) $this->difference,
            'product_variant_id' => $this->product_variant_id,
            'product_variant' => new ProductVariantResource($this->whenLoaded('productVariant')),
        ];
    }
}
