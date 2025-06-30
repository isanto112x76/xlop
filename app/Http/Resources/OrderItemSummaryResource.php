<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $productVariant = $this->whenLoaded('productVariant');
        $product = $productVariant && $productVariant->relationLoaded('product') ? $productVariant->product : null;

        $thumbnailUrl = $product?->getFirstMediaUrl('images', 'thumb') ?? '';

        // ✅ NOWA LOGIKA: Dwustopniowe wyszukiwanie lokalizacji
        $location = null;
        if ($productVariant) {
            // Krok 1: Spróbuj znaleźć lokalizację w bieżącym wariancie
            $location = $productVariant->stockLevels->first()?->location;

            // Krok 2: Jeśli nie ma, spróbuj znaleźć w wariancie domyślnym
            if (!$location && $product && $product->relationLoaded('defaultVariant')) {
                $location = $product->defaultVariant?->stockLevels?->first()?->location;
            }
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'ean' => $this->ean,
            'quantity' => (int) $this->quantity,
            'thumbnail_url' => $thumbnailUrl,
            'location' => $location,
        ];
    }
}
