<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductBundleItemResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'component_variant_id' => $this->component_variant_id,
            'quantity' => $this->quantity,
            // Rozbudowane info o wariancie i produkcie bazowym:
            'variant_name' => $this->componentVariant?->name,
            'variant_sku' => $this->componentVariant?->sku,
            'variant_ean' => $this->componentVariant?->ean,
            'product_name' => $this->componentVariant?->product?->name,
        ];
    }
}
