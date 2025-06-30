<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // ✅ POPRAWKA: Sprawdzamy, czy akcja to 'show', 'store' lub 'update'.
        $actionMethod = $request->route()->getActionMethod();
        $isDetailView = in_array($actionMethod, ['show', 'store', 'update']);

        return [
            'id' => $this->id,
            'baselinker_order_id' => $this->baselinker_order_id,
            'external_order_id' => $this->external_order_id,
            'order_source' => $this->order_source,
            'date_add' => $this->date_add->toIso8601String(),
            'date_confirmed' => $this->whenNotNull($this->date_confirmed, fn() => $this->date_confirmed?->toIso8601String()),
            'date_in_status' => $this->whenNotNull($this->date_in_status, fn() => $this->date_in_status?->toIso8601String()),
            'baselinker_status_id' => $this->baselinker_status_id,
            'total_gross' => (float) $this->total_gross,
            'is_paid' => (bool) $this->paid,
            'delivery_method' => $this->delivery_method,
            'delivery_tracking_number' => $this->delivery_tracking_number,
            'want_invoice' => (bool) $this->want_invoice,
            'is_cod' => (bool) $this->is_cod,

            // Relacje
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'wz_document' => new DocumentResource($this->whenLoaded('wzDocument')),

            // Używamy odpowiedniego zasobu w zależności od kontekstu (czy to lista, czy widok szczegółowy)
            'items' => $isDetailView
                ? OrderItemResource::collection($this->whenLoaded('items'))
                : OrderItemSummaryResource::collection($this->whenLoaded('items')),

            // Dołączamy szczegółowe dane (adresy, komentarze) tylko w widoku szczegółowym
            $this->mergeWhen($isDetailView, [
                'billing_address' => new AddressResource($this->whenLoaded('billingAddress')),
                'shipping_address' => new AddressResource($this->whenLoaded('shippingAddress')),
                'customer_login' => $this->customer_login,
                'customer_comments' => $this->customer_comments,
            ]),
        ];
    }
}
