<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:customers,email',
            'phone' => 'nullable|string|max:30',
            'company_name' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:20',
            'baselinker_user_login' => 'nullable|string|max:255',
        ];
    }
}
