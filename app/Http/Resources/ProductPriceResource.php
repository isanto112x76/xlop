<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductPriceResource extends JsonResource
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
            'variant_id' => $this->variant_id,
            'type' => $this->type,
            'price_net' => (float) $this->price_net,
            'price_gross' => (float) $this->price_gross,
            'currency' => $this->currency,
            'valid_from' => $this->valid_from ? $this->valid_from->toDateString() : null,
            'valid_to' => $this->valid_to ? $this->valid_to->toDateString() : null,
            'tax_rate_id' => $this->tax_rate_id, // Teraz poprawne
            'tax_rate' => new TaxRateResource($this->whenLoaded('taxRate')), // Teraz poprawne
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
