<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentItemResource extends JsonResource
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
            'document_id' => $this->document_id,
            'product_variant_id' => $this->product_variant_id,
            'quantity' => $this->quantity,
            'price_net' => $this->price_net,
            'price_gross' => $this->price_gross,
            'tax_rate_id' => $this->tax_rate_id,

            // ✅ POPRAWKA OSTATECZNA: Bezwarunkowe dołączenie obiektu TaxRate.
            // Zamiast `whenLoaded`, bezpośrednio odwołujemy się do relacji.
            // To gwarantuje, że obiekt `taxRate` zawsze znajdzie się w odpowiedzi API, jeśli istnieje.
            'taxRate' => new TaxRateResource($this->taxRate),

            'product_variant' => new ProductVariantResource($this->whenLoaded('productVariant')),
        ];
    }
}
