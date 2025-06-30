<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Każdy zalogowany użytkownik (z uprawnieniami) może tworzyć
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'symbol' => 'required|string|max:20|unique:warehouses,symbol',
            'address' => 'nullable|string',
            'is_default' => 'sometimes|boolean',
            'baselinker_storage_id' => 'nullable|string|max:50|unique:warehouses,baselinker_storage_id',
        ];
    }

    protected function prepareForValidation()
    {
        if ($this->has('is_default')) {
            $this->merge([
                'is_default' => filter_var($this->is_default, FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }
}
