<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $categoryId = $this->route('category')->id;
        return [
            'name' => 'sometimes|required|string|max:255',
            'parent_id' => ['nullable', Rule::exists('categories', 'id')->whereNot('id', $categoryId)], // Zapobiega pętli
            'baselinker_category_id' => ['nullable', 'integer', Rule::unique('categories')->ignore($categoryId)],
        ];
    }
}
