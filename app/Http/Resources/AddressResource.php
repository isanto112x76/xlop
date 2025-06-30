<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'type' => $this->type,
            'full_name' => $this->full_name,
            'company_name' => $this->company_name,
            'address' => $this->address,
            'postcode' => $this->postcode,
            'city' => $this->city,
            'country_code' => $this->country_code,
            'phone' => $this->phone,
            'email' => $this->email,
        ];
    }
}
