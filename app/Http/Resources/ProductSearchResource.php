<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductSearchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Pobieramy domyślną cenę raz, aby uniknąć wielokrotnych zapytań
        $defaultPrice = $this->prices()->where('type', 'retail')->with('taxRate')->first();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'ean' => $this->barcode,
            'thumbnail' => $this->getFirstMediaUrl('default', 'thumb'),
            'location' => $this->whenLoaded('stockLevels', function () {
                return $this->stockLevels->first()->location ?? 'Brak lokalizacji';
            }),
            'unit_price' => $defaultPrice?->price_net ?? 0,

            // ✅ POPRAWKA: Zwracamy `tax_rate_id` zamiast `vat_rate`
            'tax_rate_id' => $defaultPrice?->tax_rate_id ?? null,

            'unit' => $this->unit,
            'is_default' => $this->is_default ?? false,
            'is_selectable' => $this->is_selectable ?? true,
        ];
    }
}
