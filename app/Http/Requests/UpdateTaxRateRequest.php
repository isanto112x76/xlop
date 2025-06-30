<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaxRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:50',
            'rate' => 'sometimes|required|numeric|min:0|max:100',
            'is_default' => 'sometimes|boolean',
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
