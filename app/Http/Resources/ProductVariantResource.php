<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'name' => $this->name,
            'sku' => $this->sku,
            'ean' => $this->ean,
            'barcode' => $this->barcode,
            'is_default' => (bool) $this->is_default,
            'position' => $this->position,

            'stock_levels' => StockLevelResource::collection($this->whenLoaded('stockLevels')),

            // Tylko pola nadpisywalne! (reszta dziedziczona z produktu)
            'description_override' => $this->description_override,
            'weight_override' => $this->weight_override,
            'attributes_override' => $this->attributes_override,
            'marketplace_attributes_override' => $this->marketplace_attributes_override,

            'override_product_description' => (bool) $this->override_product_description,
            'override_product_weight' => (bool) $this->override_product_weight,
            'override_product_attributes' => (bool) $this->override_product_attributes,
            'override_product_marketplace_attributes' => (bool) $this->override_product_marketplace_attributes,

            'has_own_media' => (bool) $this->has_own_media,
            'thumbnail' => $this->thumbnail,
            // Zoptymalizowane parsowanie JSON – jeśli coś trafi jako string, zwracamy array/object
            'description_override_value' => is_array($this->description_override) ? $this->description_override : (
                is_string($this->description_override) && $this->description_override !== ''
                ? json_decode($this->description_override, true)
                : []
            ),
            'weight_override_value' => $this->weight_override,
            'attributes_override_value' => is_array($this->attributes_override) ? $this->attributes_override : (
                is_string($this->attributes_override) && $this->attributes_override !== ''
                ? json_decode($this->attributes_override, true)
                : []
            ),
            'marketplace_attributes_override_value' => is_array($this->marketplace_attributes_override) ? $this->marketplace_attributes_override : (
                is_string($this->marketplace_attributes_override) && $this->marketplace_attributes_override !== ''
                ? json_decode($this->marketplace_attributes_override, true)
                : []
            ),

            // EFFECTIVE pola - gotowe do UI (sklejone z produktu+nadpisane z wariantu)
            'effective_description' => $this->effective_description,
            'effective_weight' => $this->effective_weight,
            'effective_attributes' => $this->effective_attributes,
            'effective_marketplace_attributes' => $this->effective_marketplace_attributes,

            // Media, ceny, stock_levels (wszystko relacje)
            'media' => MediaResource::collection($this->whenLoaded('media')),
            'prices' => ProductPriceResource::collection($this->whenLoaded('prices')),
            'stock_levels_editable' => StockLevelResource::collection($this->whenLoaded('stockLevels')),

            // Liczniki stocku na wariancie (opcjonalnie)
            'total_stock' => $this->total_stock_individual,
            'total_available_stock' => $this->total_available_stock_individual,
            'total_reserved_stock' => $this->total_reserved_stock_individual,
            'total_incoming_stock' => $this->total_incoming_stock_individual,

            // Daty
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
