<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockInRequest extends FormRequest
{
    public function rules()
    {
        return [
            'product_variant_id' => 'required|exists:product_variants,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'quantity' => 'required|numeric|min:0.01',
            'purchase_price' => 'required|numeric|min:0',
            'purchase_date' => 'required|date',
            'source_document_type' => 'nullable|string',
            'source_document_id' => 'nullable|integer',
        ];
    }
}
