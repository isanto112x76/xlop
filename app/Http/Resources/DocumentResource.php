<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\SupplierResource;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\WarehouseResource;
use App\Http\Resources\DocumentItemResource;
use App\Http\Resources\MediaResource;

class DocumentResource extends JsonResource
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
            'number' => $this->number,
            'type' => $this->type?->value, // Bezpieczne pobranie wartości enuma
            'type_label' => $this->type?->getLabel(), // Bezpieczne wywołanie metody
            'name' => $this->name,
            'currency' => $this->currency,
            'document_date' => $this->document_date?->format('Y-m-d'),
            'issue_date' => $this->issue_date?->format('Y-m-d'),
            'delivery_date' => $this->delivery_date?->format('Y-m-d'),
            'payment_date' => $this->payment_date?->format('Y-m-d'),
            'total_net' => $this->total_net,
            'total_gross' => $this->total_gross,
            'paid' => (bool) $this->paid,
            'paid_amount' => $this->paid_amount,

            // === KLUCZOWA POPRAWKA ===
            // Zabezpieczenie pól timestamp, które mogą być nullem w bazie danych.
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
            'closed_at' => $this->closed_at,
            // =========================

            'foreign_number' => $this->foreign_number,
            'payment_method' => $this->payment_method,
            'delivery_method' => $this->delivery_method,
            'delivery_tracking_number' => $this->delivery_tracking_number,
            'responsible_id' => $this->responsible_id,
            'notes_internal' => $this->notes_internal,
            'notes_print' => $this->notes_print,
            'source_warehouse_id' => $this->source_warehouse_id,
            'target_warehouse_id' => $this->target_warehouse_id,
            'related_order_id' => $this->related_order_id,

            // Relacje
            'user' => new UserResource($this->whenLoaded('user')),
            'responsible' => new UserResource($this->whenLoaded('responsible')),
            'supplier' => new SupplierResource($this->whenLoaded('supplier')),
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'parent_document' => new DocumentResource($this->whenLoaded('parentDocument')),
            'child_documents' => DocumentResource::collection($this->whenLoaded('childDocuments')),
            'sourceWarehouse' => new WarehouseResource($this->whenLoaded('sourceWarehouse')),
            'targetWarehouse' => new WarehouseResource($this->whenLoaded('targetWarehouse')),
            'items' => DocumentItemResource::collection($this->whenLoaded('items')),
            'attachments' => MediaResource::collection($this->whenLoaded('media')),
        ];
    }
}
