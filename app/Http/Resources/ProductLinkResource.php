<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductLinkResource extends JsonResource
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
            'product_id' => $this->product_id,
            'linked_product_id' => $this->linked_product_id,
            'link_type' => $this->link_type, // np. 'related', 'upsell', 'cross_sell'
            // Możesz chcieć załadować podstawowe informacje o podlinkowanym produkcie
            // Uważaj na zagnieżdżenie i potencjalne problemy z wydajnością (N+1)
            // 'linked_product_short' => new BasicProductInfoResource($this->whenLoaded('linkedProduct')),
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
