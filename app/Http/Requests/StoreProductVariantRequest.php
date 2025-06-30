<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Product; // Potrzebne do pobrania ID produktu z trasy

class StoreProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Dostosuj uprawnienia, jeśli potrzebujesz
    }

    protected function prepareForValidation()
    {
        // Konwersja 'true'/'false' stringów na boolean dla flag
        $booleanFields = [
            'is_default',
            'override_product_description',
            'override_product_weight',
            'override_product_attributes',
            'override_product_marketplace_attributes',
            'has_own_media'
        ];

        foreach ($booleanFields as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $this->merge([$field => filter_var($this->input($field), FILTER_VALIDATE_BOOLEAN)]);
            }
        }
    }

    public function rules(): array
    {
        $productId = $this->route('product') instanceof Product ? $this->route('product')->id : null;

        return [
            'name' => 'required|string|max:255',
            'sku' => [
                'required',
                'string',
                'max:100',
                Rule::unique('product_variants', 'sku')->where(function ($query) use ($productId) {
                    if ($productId) { // SKU musi być unikalne w obrębie produktu
                        return $query->where('product_id', $productId);
                    }
                    // Jeśli $productId jest null (np. standalone tworzenie wariantu - rzadkie),
                    // wtedy unikalność globalna lub pomiń ten warunek.
                    // Dla apiResource('products.variants') $productId zawsze będzie dostępne.
                })->whereNull('deleted_at')
            ],
            'ean' => [
                'nullable',
                'string',
                'max:30',
                Rule::unique('product_variants', 'ean')->where(function ($query) use ($productId) {
                    if ($productId) {
                        return $query->where('product_id', $productId);
                    }
                })->whereNull('deleted_at')
            ],
            'barcode' => 'nullable|string|max:100',
            'attributes' => 'nullable|array',
            'is_default' => 'sometimes|boolean',
            'position' => 'sometimes|integer|min:0',
            'foreign_id' => 'nullable|string|max:255',
            'description_override' => 'nullable|string',
            'weight_override' => 'nullable|numeric|min:0|regex:/^\d*(\.\d{1,3})?$/',
            'attributes_override' => 'nullable|array',
            'marketplace_attributes_override' => 'nullable|array',

            'override_product_description' => 'sometimes|boolean',
            'override_product_weight' => 'sometimes|boolean',
            'override_product_attributes' => 'sometimes|boolean',
            'override_product_marketplace_attributes' => 'sometimes|boolean',
            'has_own_media' => 'sometimes|boolean',

            'prices' => 'sometimes|array',
            'prices.*.type' => ['required_with:prices', 'string', Rule::in(['retail', 'wholesale', 'sale', 'purchase', 'base'])],
            'prices.*.price_net' => 'required_with:prices|numeric|min:0',
            'prices.*.price_gross' => 'required_with:prices|numeric|min:0',
            'prices.*.tax_rate_id' => 'required_with:prices|integer|exists:tax_rates,id',
            'prices.*.currency' => 'sometimes|required_with:prices|string|size:3',
            'prices.*.valid_from' => 'nullable|date_format:Y-m-d H:i:s',
            'prices.*.valid_to' => 'nullable|date_format:Y-m-d H:i:s|after_or_equal:prices.*.valid_from',
            'prices.*.baselinker_price_group_id' => 'nullable|integer',


            'stock_levels' => 'sometimes|array',
            'stock_levels.*.warehouse_id' => 'required_with:stock_levels|integer|exists:warehouses,id',
            'stock_levels.*.quantity' => 'required_with:stock_levels|integer',
            'stock_levels.*.reserved_quantity' => 'sometimes|integer|min:0',
            'stock_levels.*.incoming_quantity' => 'sometimes|integer|min:0',
            'stock_levels.*.location' => 'nullable|string|max:255',

            // Możesz dodać walidację dla 'new_variant_images' jeśli chcesz obsługiwać upload razem z tworzeniem wariantu
            // 'new_variant_images' => 'nullable|array',
            // 'new_variant_images.*' => 'image|mimes:jpeg,png,jpg,webp|max:2048',
        ];
    }
}
