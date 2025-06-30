<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'invoice_number' => 'nullable|string|max:255',
            'issue_date' => 'required|date',
            'amount_net' => 'required|numeric|min:0',
            'amount_gross' => 'required|numeric|min:0',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'expense_category_id' => 'required|exists:expense_categories,id',
        ];
    }
}
