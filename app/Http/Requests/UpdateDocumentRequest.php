<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // ✅ POPRAWKA: Kompletny zestaw reguł walidacji
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'foreign_number' => ['nullable', 'string', 'max:255'],
            'document_date' => ['sometimes', 'required', 'date'],
            'issue_date' => ['sometimes', 'required', 'date'],
            'delivery_date' => ['nullable', 'date'],
            'payment_date' => ['nullable', 'date'],
            'contractor_id' => ['nullable', 'integer'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'responsible_id' => ['nullable', 'exists:users,id'],
            'related_order_id' => ['nullable', 'exists:orders,id'],
            'payment_method' => ['required', 'string', 'max:255'],
            'paid' => ['required', 'boolean'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:3'],
            'delivery_method' => ['nullable', 'string', 'max:255'],
            'delivery_tracking_number' => ['nullable', 'string', 'max:255'],
            'notes_internal' => ['nullable', 'string'],
            'notes_print' => ['nullable', 'string'],
            'products' => ['sometimes', 'required', 'array'],
            'products.*.id' => ['nullable', 'integer'],
            'products.*.product_variant_id' => ['required', 'exists:product_variants,id'],
            'products.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'products.*.unit_price' => ['required', 'numeric', 'min:0'],
            'products.*.tax_rate_id' => ['required', 'exists:tax_rates,id'],
            'new_attachments' => 'nullable|array',
            'new_attachments.*' => 'file|mimes:pdf,jpg,png,doc,docx,xls,xlsx|max:10240',
            'deleted_media_ids' => 'nullable|array',
            'deleted_media_ids.*' => 'integer|exists:media,id',
        ];
    }
}
