<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\ProductVariant;

class UpdateProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Dostosuj uprawnienia
    }

    protected function prepareForValidation()
    {
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
        $variantId = $this->route('variant') instanceof ProductVariant ? $this->route('variant')->id : ($this->route('variant') ?? null);
        // $productId potrzebne do scope'owania unikalności SKU/EAN do produktu
        $productId = $this->route('variant') instanceof ProductVariant ? $this->route('variant')->product_id : null;
        // Jeśli trasa nie zawiera {product}, a tylko {variant} (shallow), product_id trzeba pobrać z modelu wariantu
        if (!$productId && $variantId) {
            $variantModel = ProductVariant::find($variantId);
            if ($variantModel) {
                $productId = $variantModel->product_id;
            }
        }

        return [
            'name' => 'sometimes|required|string|max:255',
            'sku' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('product_variants', 'sku')->where(function ($query) use ($productId) {
                    if ($productId) {
                        return $query->where('product_id', $productId);
                    }
                })->ignore($variantId)->whereNull('deleted_at')
            ],
            'ean' => [
                'nullable',
                'string',
                'max:30',
                Rule::unique('product_variants', 'ean')->where(function ($query) use ($productId) {
                    if ($productId) {
                        return $query->where('product_id', $productId);
                    }
                })->ignore($variantId)->whereNull('deleted_at')
            ],
            'barcode' => 'nullable|string|max:100',
            'attributes' => 'nullable|array',
            'is_default' => 'sometimes|boolean',
            'position' => 'sometimes|integer|min:0',

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
            'prices.*.id' => ['nullable', 'integer', Rule::exists('product_prices', 'id')->where('variant_id', $variantId)], // variant_id
            'prices.*.type' => ['required_with:prices', 'string', Rule::in(['retail', 'wholesale', 'sale', 'purchase', 'base'])],
            'prices.*.price_net' => 'required_with:prices|numeric|min:0',
            'prices.*.price_gross' => 'required_with:prices|numeric|min:0',
            'prices.*.tax_rate_id' => 'required_with:prices|integer|exists:tax_rates,id',
            'prices.*.currency' => 'sometimes|required_with:prices|string|size:3',
            'prices.*.valid_from' => 'nullable|date_format:Y-m-d H:i:s',
            'prices.*.valid_to' => 'nullable|date_format:Y-m-d H:i:s|after_or_equal:prices.*.valid_from',
            'prices.*.baselinker_price_group_id' => 'nullable|integer',

            'stock_levels' => 'sometimes|array',
            'stock_levels.*.id' => ['nullable', 'integer', Rule::exists('stock_levels', 'id')->where('product_variant_id', $variantId)],
            'stock_levels.*.warehouse_id' => 'required_with:stock_levels|integer|exists:warehouses,id',
            'stock_levels.*.quantity' => 'required_with:stock_levels|integer',
            'stock_levels.*.reserved_quantity' => 'sometimes|integer|min:0',
            'stock_levels.*.incoming_quantity' => 'sometimes|integer|min:0',
            'stock_levels.*.location' => 'nullable|string|max:255',

            // Media (tylko ID do usunięcia, ID w nowej kolejności, nowe pliki przez MediaController)
            // 'new_variant_images' => 'nullable|array',
            // 'new_variant_images.*' => 'image|mimes:jpeg,png,jpg,webp|max:2048',
            'deleted_variant_image_ids' => 'nullable|array',
            'deleted_variant_image_ids.*' => 'integer|exists:media,id', // Sprawdza, czy media istnieją i należą do tego wariantu
            'variant_image_order' => 'nullable|array',
            'variant_image_order.*' => 'integer|exists:media,id',
        ];
    }
}
