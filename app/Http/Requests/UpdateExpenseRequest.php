<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'invoice_number' => 'nullable|string|max:255',
            'issue_date' => 'sometimes|required|date',
            'amount_net' => 'sometimes|required|numeric|min:0',
            'amount_gross' => 'sometimes|required|numeric|min:0',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'expense_category_id' => 'sometimes|required|exists:expense_categories,id',
        ];
    }
}
