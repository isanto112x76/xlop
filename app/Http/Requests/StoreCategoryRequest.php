<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
            'baselinker_category_id' => 'nullable|integer|unique:categories,baselinker_category_id',
            // Slug jest generowany automatycznie przez model
        ];
    }
}
