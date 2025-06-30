<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Enums\DocumentType;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(array_column(DocumentType::cases(), 'value'))],
            'number' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'contractor_id' => ['nullable', 'exists:suppliers,id'],
            'document_date' => ['required', 'date'],
            'issue_date' => ['required', 'date'],
            'delivery_date' => ['nullable', 'date'],
            'payment_date' => ['nullable', 'date'],
            'payment_method' => ['required', 'string', 'max:255'],
            'paid' => ['required', 'boolean'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'responsible' => ['nullable', 'string', 'max:255'],
            'transport' => ['nullable', 'string', 'max:255'],
            'notes_internal' => ['nullable', 'string'],
            'notes_print' => ['nullable', 'string'],
            'related_document_type' => ['nullable', 'string'],
            'related_document_number' => ['nullable', 'string'],

            'products' => ['required', 'array', 'min:1'],
            'products.*.product_variant_id' => ['required', 'exists:product_variants,id'],
            'products.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'products.*.unit_price' => ['required', 'numeric', 'min:0'],

            // POPRAWKA: Zmiana walidacji z 'vat_rate' na 'tax_rate_id'
            'products.*.tax_rate_id' => ['required', 'exists:tax_rates,id'],

            'new_attachments' => 'nullable|array',
            'new_attachments.*' => 'file|mimes:pdf,jpg,png,doc,docx,xls,xlsx|max:10240',
        ];
    }
}
