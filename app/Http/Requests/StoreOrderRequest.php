<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Zezwalamy na wywołanie z CLI (komendy artisan)
        return $this->is('api/*') ? auth()->check() : true;
    }

    public function rules(): array
    {
        return [
            'baselinker_order_id' => 'required|integer',
            'email' => 'required|email',
            'delivery_fullname' => 'required|string|max:255',
            'delivery_address' => 'required|string',
            'delivery_postcode' => 'required|string',
            'delivery_city' => 'required|string',
            'delivery_country_code' => 'required|string|size:2',
            'order_status_id' => 'required|integer',
            'date_add' => 'required|integer', // Spodziewamy się timestampa
            'total_price' => 'required|numeric',
            'products' => 'required|array|min:1',
            'products.*.sku' => 'required|string',
            'products.*.name' => 'required|string',
            'products.*.quantity' => 'required|integer',
            'products.*.price_brutto' => 'required|numeric',
            'products.*.tax_rate' => 'required|numeric',

            // Pola opcjonalne
            'user_login' => 'nullable|string',
            'phone' => 'nullable|string',
            'invoice_nip' => 'nullable|string',
            'payment_method_cod' => 'required|boolean',
            'delivery_package_nr' => 'nullable|string',
            'want_invoice' => 'required|boolean',
            'user_comments' => 'nullable|string',
        ];
    }
}
