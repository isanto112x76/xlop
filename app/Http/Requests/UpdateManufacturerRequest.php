<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateManufacturerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $manufacturerId = $this->route('manufacturer')->id;
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('manufacturers')->ignore($manufacturerId)],
        ];
    }


}
