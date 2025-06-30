<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $warehouseId = $this->route('warehouse')->id;

        return [
            'name' => 'sometimes|required|string|max:255',
            'symbol' => ['sometimes', 'required', 'string', 'max:20', Rule::unique('warehouses')->ignore($warehouseId)],
            'address' => 'nullable|string',
            'is_default' => 'sometimes|boolean',
            'baselinker_storage_id' => ['nullable', 'string', 'max:50', Rule::unique('warehouses')->ignore($warehouseId)],
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
