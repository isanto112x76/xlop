<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\ProductVariantResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $productVariant = $this->whenLoaded('productVariant');

        // Domyślnie pusta miniaturka
        $thumbnailUrl = '';

        if ($productVariant && !$productVariant instanceof \Illuminate\Http\Resources\MissingValue) {
            // Spróbuj pobrać z 'variant_images'
            $thumbnailUrl = $productVariant->getFirstMediaUrl('images', 'thumb');

            // Jeśli brak, spróbuj z produktu głównego
            if (empty($thumbnailUrl) && $productVariant->relationLoaded('product') && $productVariant->product) {
                $thumbnailUrl = $productVariant->product->getFirstMediaUrl('images', 'thumb');
            }
        }

        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'product_variant_id' => $this->product_variant_id,
            'order_product_id' => $this->order_product_id,
            'sku' => $this->sku,
            'name' => $this->name,
            'quantity' => (int) $this->quantity,
            'price_gross' => (float) $this->price_gross,
            'tax_rate' => (float) $this->tax_rate,
            'thumbnail_url' => $thumbnailUrl,
            'product_variant' => new ProductVariantResource($productVariant),
        ];
    }
}
