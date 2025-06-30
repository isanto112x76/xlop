<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'company_name' => $this->company_name,
            'tax_id' => $this->tax_id,
            'baselinker_user_login' => $this->baselinker_user_login,
            'created_at' => $this->created_at->toIso8601String(),
            // Warunkowo dołączamy adresy, jeśli zostały załadowane
            'addresses' => AddressResource::collection($this->whenLoaded('addresses')),
        ];
    }
}
