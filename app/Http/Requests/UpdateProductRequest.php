<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str; // Dla Str::slug, jeśli potrzebne w prepareForValidation

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Przygotowuje dane wejściowe do walidacji.
     */
    protected function prepareForValidation(): void
    {
        // === Konwersja dla pól produktu głównego ===
        $this->mergeIfMissingOrEmptyString('ean', null);
        $this->mergeIfMissingOrEmptyString('pos_code', null);
        // 'short_description' jest teraz częścią 'description' (JSON)
        // $this->mergeIfMissingOrEmptyString('short_description', null);
        $this->mergeIfMissingOrEmptyString('weight', null, true, 'float');

        $this->mergeIfMissingOrEmptyString('category_id', null, true, 'int');
        $this->mergeIfMissingOrEmptyString('manufacturer_id', null, true, 'int');
        $this->mergeIfMissingOrEmptyString('supplier_id', null, true, 'int');
        $this->mergeIfMissingOrEmptyString('foreign_id', null);

        // Konwersja booleanów dla produktu głównego
        $this->convertInputToBooleanBasedOnPresence('manage_stock');
        $this->convertInputToBooleanBasedOnPresence('variants_share_stock');

        // Pola JSON dla produktu głównego - zapewnij, że są tablicami
        $this->decodeJsonField('description', ['short' => null, 'full' => null, 'features' => []]);
        $this->decodeJsonField('attributes', []);
        $this->decodeJsonField('marketplace_attributes', ['parameters' => [], 'long_description' => []]);
        $this->decodeJsonField('dimensions', ['length' => null, 'width' => null, 'height' => null]);


        // === Konwersja dla wariantów ===
        if ($this->has('variants') && is_array($this->input('variants'))) {
            $variantsInput = $this->input('variants');
            foreach ($variantsInput as $key => $variant) {
                if (!is_array($variant))
                    continue;

                // Konwersja booleanów dla każdego wariantu
                foreach (['is_default', 'override_product_description', 'override_product_weight', 'override_product_attributes', 'override_product_marketplace_attributes', 'has_own_media'] as $boolField) {
                    if (array_key_exists($boolField, $variant)) {
                        $variantsInput[$key][$boolField] = $this->convertToBoolean($variant[$boolField]);
                    } else {
                        // Jeśli flaga nie jest wysłana, załóż false (typowo dla checkboxów)
                        $variantsInput[$key][$boolField] = false;
                    }
                }

                // Pola JSON dla wariantów
                $variantsInput[$key] = $this->decodeJsonFieldInArray($variant, [
                    'attributes' => [],
                    'description_override' => ['short' => null, 'full' => null, 'features' => []],
                    'attributes_override' => [],
                    'marketplace_attributes_override' => ['parameters' => [], 'long_description' => []]
                ]);


                // Konwersja pustych stringów na null dla opcjonalnych pól tekstowych wariantu
                foreach (['ean', 'barcode', /* 'description_override' - teraz JSON */] as $nullableStringField) {
                    if (array_key_exists($nullableStringField, $variant) && $variant[$nullableStringField] === '') {
                        $variantsInput[$key][$nullableStringField] = null;
                    }
                }
                // Specjalna obsługa dla barcode (INT w bazie)
                if (array_key_exists('barcode', $variant) && ($variant['barcode'] === '' || $variant['barcode'] === null)) {
                    $variantsInput[$key]['barcode'] = null;
                } elseif (array_key_exists('barcode', $variant) && !is_numeric($variant['barcode']) && !is_null($variant['barcode'])) {
                    // Jeśli nie jest numeryczny i nie jest nullem, ustaw na null, aby uniknąć błędu `numeric`
                    // Lepsza walidacja w rules() to złapie. Tutaj tylko przygotowanie.
                    // Można by też próbować usunąć znaki nienumeryczne, ale to ryzykowne.
                }


                if (array_key_exists('weight_override', $variant)) {
                    $variantsInput[$key]['weight_override'] = ($variant['weight_override'] === '' || $variant['weight_override'] === null) ? null : (float) $variant['weight_override'];
                }


                if (isset($variant['prices']) && is_array($variant['prices'])) {
                    foreach ($variant['prices'] as $priceKey => $price) {
                        if (!is_array($price))
                            continue;
                        foreach (['price_net', 'price_gross'] as $numericField) {
                            if (array_key_exists($numericField, $price)) {
                                $variantsInput[$key]['prices'][$priceKey][$numericField] = ($price[$numericField] === '' || $price[$numericField] === null) ? null : (float) $price[$numericField];
                            }
                        }
                        if (array_key_exists('baselinker_price_group_id', $price)) {
                            $variantsInput[$key]['prices'][$priceKey]['baselinker_price_group_id'] = ($price['baselinker_price_group_id'] === '' || $price['baselinker_price_group_id'] === null) ? null : (int) $price['baselinker_price_group_id'];
                        }
                        foreach (['valid_from', 'valid_to'] as $dateField) {
                            if (array_key_exists($dateField, $price) && $price[$dateField] === '') {
                                $variantsInput[$key]['prices'][$priceKey][$dateField] = null;
                            }
                        }
                    }
                }

                if (isset($variant['stock_levels']) && is_array($variant['stock_levels'])) {
                    foreach ($variant['stock_levels'] as $stockKey => $stock) {
                        if (!is_array($stock))
                            continue;
                        foreach (['quantity', 'reserved_quantity', 'incoming_quantity'] as $intField) {
                            if (array_key_exists($intField, $stock)) {
                                $variantsInput[$key]['stock_levels'][$stockKey][$intField] = ($stock[$intField] === '' || $stock[$intField] === null) ? 0 : (int) $stock[$intField];
                            }
                        }
                        if (array_key_exists('location', $stock) && $stock['location'] === '') {
                            $variantsInput[$key]['stock_levels'][$stockKey]['location'] = null;
                        }
                    }
                }
            }
            $this->merge(['variants' => $variantsInput]);
        }

        if ($this->has('bundle_items') && is_array($this->input('bundle_items'))) {
            $bundleItemsInput = $this->input('bundle_items');
            foreach ($bundleItemsInput as $key => $item) {
                if (is_array($item) && array_key_exists('quantity', $item)) {
                    $bundleItemsInput[$key]['quantity'] = ($item['quantity'] === '' || $item['quantity'] === null) ? 1 : (int) $item['quantity'];
                }
            }
            $this->merge(['bundle_items' => $bundleItemsInput]);
        }

        if ($this->has('tag_ids') && is_array($this->input('tag_ids'))) {
            $this->merge([
                'tag_ids' => array_values(array_filter(array_map(function ($tagId) {
                    return is_numeric($tagId) ? intval($tagId) : null;
                }, $this->input('tag_ids')), fn($id) => $id !== null && $id > 0))
            ]);
        } elseif ($this->has('tag_ids') && $this->input('tag_ids') === null) {
            $this->merge(['tag_ids' => []]);
        }
    }

    protected function convertToBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if ($value === null) { // Jawnie null nie jest ani true ani false dla flag, które mogą nie być wysłane
            return false; // Domyślnie, jeśli nie ma wartości (np. odznaczony checkbox)
        }
        if (is_string($value)) {
            $valLower = strtolower($value);
            if ($valLower === 'true' || $valLower === '1' || $valLower === 'on' || $valLower === 'yes') {
                return true;
            }
            if ($valLower === 'false' || $valLower === '0' || $valLower === 'off' || $valLower === 'no' || $valLower === '') {
                return false;
            }
        }
        if (is_numeric($value)) {
            return (int) $value === 1;
        }
        return false;
    }

    protected function convertInputToBooleanBasedOnPresence(string $key): void
    {
        if ($this->has($key)) {
            $this->merge([$key => $this->convertToBoolean($this->input($key))]);
        } else {
            // Jeśli klucz nie jest wysyłany (np. odznaczony checkbox, który nie wysyła wartości),
            // zakładamy false. To jest istotne, bo inaczej pole nie zostanie zaktualizowane na false.
            $this->merge([$key => false]);
        }
    }

    protected function mergeIfMissingOrEmptyString(string $key, $defaultValue, bool $numeric = false, string $type = 'int'): void
    {
        if (!$this->has($key) || $this->input($key) === '' || $this->input($key) === null) {
            $this->merge([$key => $defaultValue]);
        } elseif ($numeric) {
            $value = $this->input($key);
            if (is_numeric($value)) {
                $this->merge([$key => ($type === 'float' ? (float) $value : (int) $value)]);
            } else {
                $this->merge([$key => $defaultValue]); // Jeśli nie jest numeryczny, a powinien być
            }
        }
    }

    protected function decodeJsonField(string $fieldName, array $defaultStructure = []): void
    {
        if ($this->has($fieldName)) {
            $value = $this->input($fieldName);
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $this->merge([$fieldName => $decoded]);
                } else {
                    $this->merge([$fieldName => $defaultStructure]);
                }
            } elseif (is_array($value)) {
                // Już jest tablicą, upewnij się, że ma klucze z defaultStructure, jeśli są puste
                $merged = array_merge($defaultStructure, $value);
                // Dla zagnieżdżonych struktur jak marketplace_attributes
                if ($fieldName === 'marketplace_attributes') {
                    $merged['parameters'] = $merged['parameters'] ?? ($defaultStructure['parameters'] ?? []);
                    $merged['long_description'] = $merged['long_description'] ?? ($defaultStructure['long_description'] ?? []);
                }
                $this->merge([$fieldName => $merged]);

            } elseif ($value === null) {
                $this->merge([$fieldName => $defaultStructure]);
            }
        } else {
            $this->mergeIfMissing([$fieldName => $defaultStructure]);
        }
    }

    protected function decodeJsonFieldInArray(array $arrayData, array $fieldsWithDefaults): array
    {
        foreach ($fieldsWithDefaults as $field => $defaultStructure) {
            if (array_key_exists($field, $arrayData)) {
                $value = $arrayData[$field];
                if (is_string($value)) {
                    $decoded = json_decode($value, true);
                    $arrayData[$field] = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : $defaultStructure;
                } elseif (is_array($value)) {
                    // Już jest tablicą, upewnij się, że ma klucze z defaultStructure
                    $merged = array_merge($defaultStructure, $value);
                    if ($field === 'marketplace_attributes_override') { // Tak samo dla marketplace_attributes_override
                        $merged['parameters'] = $merged['parameters'] ?? ($defaultStructure['parameters'] ?? []);
                        $merged['long_description'] = $merged['long_description'] ?? ($defaultStructure['long_description'] ?? []);
                    }
                    $arrayData[$field] = $merged;

                } elseif ($value === null) {
                    $arrayData[$field] = $defaultStructure;
                }
            } else {
                $arrayData[$field] = $defaultStructure;
            }
        }
        return $arrayData;
    }

    public function rules(): array
    {
        $productIdInput = $this->route('product');
        $productId = null;
        if ($productIdInput instanceof \App\Models\Product)
            $productId = $productIdInput->id;
        elseif (is_numeric($productIdInput))
            $productId = (int) $productIdInput;
        elseif (is_object($productIdInput) && isset($productIdInput->id))
            $productId = $productIdInput->id;

        $productRules = [
            'name' => 'sometimes|required|string|max:255',
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($productId)->whereNull('deleted_at')],
            'sku' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($productId)->whereNull('deleted_at')],
            'ean' => ['nullable', 'string', 'max:30', Rule::unique('products', 'ean')->ignore($productId)->whereNull('deleted_at')],
            'pos_code' => ['nullable', 'string', 'max:50'], // Usunięta unikalność
            'foreign_id' => 'nullable|string|max:255',
            'description' => 'nullable|array',
            'description.short' => 'nullable|string|max:1000', // Zwiększony limit
            'description.full' => 'nullable|string',
            'description.features' => 'nullable|array',
            'description.features.*' => 'nullable|string|max:255',
            'attributes' => 'nullable|array',
            'attributes.*' => 'nullable|string|max:1000', // Zwiększony limit dla wartości atrybutów
            'marketplace_attributes' => 'nullable|array',
            'marketplace_attributes.parameters' => 'nullable|array',
            'marketplace_attributes.parameters.*' => 'nullable|string|max:2000',
            'marketplace_attributes.long_description' => 'nullable|array',
            'marketplace_attributes.long_description.*' => 'nullable|string',
            'dimensions' => 'nullable|array',
            'dimensions.length' => 'nullable|numeric|min:0',
            'dimensions.width' => 'nullable|numeric|min:0',
            'dimensions.height' => 'nullable|numeric|min:0',
            'status' => ['sometimes', 'required', Rule::in(['active', 'inactive', 'draft', 'archived'])],
            'type' => ['sometimes', 'required', Rule::in(['standard', 'bundle'])],
            'category_id' => 'nullable|sometimes|integer|exists:categories,id',
            'manufacturer_id' => 'nullable|sometimes|integer|exists:manufacturers,id',
            'supplier_id' => 'nullable|sometimes|integer|exists:suppliers,id',
            'weight' => 'nullable|numeric|min:0|regex:/^\d*(\.\d{1,3})?$/',
            'manage_stock' => 'sometimes|boolean',
            'variants_share_stock' => 'sometimes|boolean',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'integer|exists:tags,id',
        ];

        $variantRules = [];
        foreach ((array) $this->input('variants', []) as $key => $variantData) {
            if (!is_array($variantData))
                continue;
            $variantIdToIgnore = $variantData['id'] ?? null;

            $variantRules["variants.{$key}.id"] = ['nullable', 'integer', Rule::exists('product_variants', 'id')->where('product_id', $productId)];
            $variantRules["variants.{$key}.name"] = 'required|string|max:255';
            $variantRules["variants.{$key}.slug"] = ['nullable', 'string', 'max:255', Rule::unique('product_variants', 'slug')->ignore($variantIdToIgnore, 'id')->where('product_id', $productId)->whereNull('deleted_at')];
            $variantRules["variants.{$key}.sku"] = ['required', 'string', 'max:100', Rule::unique('product_variants', 'sku')->where(fn($query) => $query->where('product_id', $productId))->ignore($variantIdToIgnore, 'id')->whereNull('deleted_at')];
            $variantRules["variants.{$key}.ean"] = ['nullable', 'string', 'max:30', Rule::unique('product_variants', 'ean')->where(fn($query) => $query->where('product_id', $productId))->ignore($variantIdToIgnore, 'id')->whereNull('deleted_at')];

            // Zmiana dla barcode - INT(11) w bazie
            $variantRules["variants.{$key}.barcode"] = ['nullable', 'numeric', 'digits_between:1,11']; // lub 'regex:/^\d{1,11}$/'

            $variantRules["variants.{$key}.attributes"] = 'nullable|array';
            $variantRules["variants.{$key}.attributes.*"] = 'nullable|string|max:255';
            $variantRules["variants.{$key}.is_default"] = 'sometimes|boolean';
            $variantRules["variants.{$key}.position"] = 'sometimes|integer|min:0';

            $variantRules["variants.{$key}.description_override"] = 'nullable|array';
            $variantRules["variants.{$key}.description_override.short"] = 'nullable|string|max:500';
            $variantRules["variants.{$key}.description_override.full"] = 'nullable|string';

            $variantRules["variants.{$key}.weight_override"] = 'nullable|numeric|min:0|regex:/^\d*(\.\d{1,3})?$/';

            $variantRules["variants.{$key}.attributes_override"] = 'nullable|array';
            $variantRules["variants.{$key}.attributes_override.*"] = 'nullable|string|max:255';

            $variantRules["variants.{$key}.marketplace_attributes_override"] = 'nullable|array';
            $variantRules["variants.{$key}.marketplace_attributes_override.parameters"] = 'nullable|array';
            $variantRules["variants.{$key}.marketplace_attributes_override.parameters.*"] = 'nullable|string|max:1000';
            $variantRules["variants.{$key}.marketplace_attributes_override.long_description"] = 'nullable|array';
            $variantRules["variants.{$key}.marketplace_attributes_override.long_description.*"] = 'nullable|string';

            $variantRules["variants.{$key}.override_product_description"] = 'sometimes|boolean';
            $variantRules["variants.{$key}.override_product_weight"] = 'sometimes|boolean';
            $variantRules["variants.{$key}.override_product_attributes"] = 'sometimes|boolean';
            $variantRules["variants.{$key}.override_product_marketplace_attributes"] = 'sometimes|boolean';
            $variantRules["variants.{$key}.has_own_media"] = 'sometimes|boolean';

            $variantRules["variants.{$key}.prices"] = 'sometimes|array';
            if (isset($variantData['prices']) && is_array($variantData['prices'])) {
                foreach ($variantData['prices'] as $priceKey => $priceData) {
                    if (!is_array($priceData))
                        continue;
                    $variantRules["variants.{$key}.prices.{$priceKey}.id"] = ['nullable', 'integer', Rule::exists('product_prices', 'id')->where('variant_id', $variantIdToIgnore)];
                    $variantRules["variants.{$key}.prices.{$priceKey}.type"] = ['required', 'string', Rule::in(['retail', 'purchase', 'wholesale', 'promo', 'base'])];
                    $variantRules["variants.{$key}.prices.{$priceKey}.price_net"] = 'required|numeric|min:0';
                    $variantRules["variants.{$key}.prices.{$priceKey}.price_gross"] = 'required|numeric|min:0';
                    $variantRules["variants.{$key}.prices.{$priceKey}.tax_rate_id"] = 'required|integer|exists:tax_rates,id';
                    $variantRules["variants.{$key}.prices.{$priceKey}.currency"] = 'sometimes|required|string|size:3';
                    $variantRules["variants.{$key}.prices.{$priceKey}.valid_from"] = 'nullable|date_format:Y-m-d H:i:s,Y-m-d\TH:i:s.uZ,Y-m-d,Y-m-d\TH:i:s'; // Więcej formatów daty
                    $variantRules["variants.{$key}.prices.{$priceKey}.valid_to"] = ['nullable', 'date_format:Y-m-d H:i:s,Y-m-d\TH:i:s.uZ,Y-m-d,Y-m-d\TH:i:s', Rule::when(isset($priceData['valid_from']) && $priceData['valid_from'], 'after_or_equal:variants.' . $key . '.prices.' . $priceKey . '.valid_from')];
                    $variantRules["variants.{$key}.prices.{$priceKey}.baselinker_price_group_id"] = 'nullable|integer';
                }
            }

            $variantRules["variants.{$key}.stock_levels"] = 'sometimes|array';
            if (isset($variantData['stock_levels']) && is_array($variantData['stock_levels'])) {
                foreach ($variantData['stock_levels'] as $stockKey => $stockData) {
                    if (!is_array($stockData))
                        continue;
                    $currentWarehouseId = $stockData['warehouse_id'] ?? null;
                    // Dla stock_levels, klucz obcy do wariantu to 'product_variant_id'
                    $variantRules["variants.{$key}.stock_levels.{$stockKey}.id"] = ['nullable', 'integer', Rule::exists('stock_levels', 'id')->where('product_variant_id', $variantIdToIgnore)->where('warehouse_id', $currentWarehouseId)];
                    $variantRules["variants.{$key}.stock_levels.{$stockKey}.warehouse_id"] = 'required|integer|exists:warehouses,id';
                    $variantRules["variants.{$key}.stock_levels.{$stockKey}.quantity"] = 'required|integer';
                    $variantRules["variants.{$key}.stock_levels.{$stockKey}.reserved_quantity"] = 'sometimes|integer|min:0';
                    $variantRules["variants.{$key}.stock_levels.{$stockKey}.incoming_quantity"] = 'sometimes|integer|min:0';
                    $variantRules["variants.{$key}.stock_levels.{$stockKey}.location"] = 'nullable|string|max:255';
                }
            }
            $variantRules["variants.{$key}.new_variant_images"] = 'nullable|array';
            $variantRules["variants.{$key}.new_variant_images.*"] = 'image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048';
            $variantRules["variants.{$key}.deleted_variant_image_ids"] = 'nullable|array';
            $variantRules["variants.{$key}.deleted_variant_image_ids.*"] = 'integer|exists:media,id';
            $variantRules["variants.{$key}.variant_image_order"] = 'nullable|array';
            $variantRules["variants.{$key}.variant_image_order.*"] = 'integer|exists:media,id';
        }

        if ($this->input('product_type') === 'standard' && empty($this->input('variants'))) {
            $variantRules['variants'] = 'required|array|min:1';
        } elseif ($this->has('variants') && $this->input('variants') === []) {
            $variantRules['variants'] = 'sometimes|array|max:0'; // Jeśli produkt nie jest standardowy, pusta tablica wariantów jest OK
        }


        $bundleItemRules = [];
        if ($this->input('product_type') === 'bundle') {
            $bundleItemRules['bundle_items'] = 'required|array|min:1';
            foreach ((array) $this->input('bundle_items', []) as $key => $bundleItemData) {
                if (!is_array($bundleItemData))
                    continue;
                $bundleItemRules["bundle_items.{$key}.id"] = ['nullable', 'integer', Rule::exists('product_bundle_items', 'id')->where('bundle_product_id', $productId)];
                $bundleItemRules["bundle_items.{$key}.component_variant_id"] = 'required|integer|exists:product_variants,id';
                $bundleItemRules["bundle_items.{$key}.quantity"] = 'required|integer|min:1';
            }
        } else {
            $bundleItemRules['bundle_items'] = 'nullable|array|max:0';
        }

        $productLinkRules = [];
        if ($this->has('product_links') && is_array($this->input('product_links'))) {
            $productLinkRules['product_links'] = 'nullable|array';
            foreach ($this->input('product_links') as $key => $linkData) {
                if (!is_array($linkData))
                    continue;
                $productLinkRules["product_links.{$key}.id"] = ['nullable', 'integer', Rule::exists('product_links', 'id')->where('product_id', $productId)];
                $productLinkRules["product_links.{$key}.type"] = ['required', 'string', Rule::in(['related', 'upsell', 'cross_sell', 'external'])]; // Zgodnie z ProductService, platform to string
                $productLinkRules["product_links.{$key}.platform"] = ['required', 'string', 'max:255']; // Zgodnie z migracją
                $productLinkRules["product_links.{$key}.external_id"] = ['required', 'string', 'max:255']; // Zgodnie z migracją
                $productLinkRules["product_links.{$key}.url"] = ['nullable', 'url', Rule::requiredIf(fn() => isset($linkData['type']) && $linkData['type'] === 'external')]; // Zgodnie z migracją
            }
        }

        $mediaRules = [
            'new_images' => 'nullable|array',
            'new_images.*' => 'image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'deleted_image_ids' => 'nullable|array',
            'deleted_image_ids.*' => 'integer|exists:media,id',
            'image_order' => 'nullable|array',
            'image_order.*' => 'integer|exists:media,id',
        ];

        return array_merge($productRules, $variantRules, $bundleItemRules, $productLinkRules, $mediaRules);
    }

    protected function passedValidation()
    {
        if (
            $this->filled('variants') && is_array($this->input('variants')) &&
            $this->input('product_type', $this->route('product')?->type ?? 'standard') !== 'bundle'
        ) { // Użyj type zamiast product_type

            $defaultCount = 0;
            $actualVariantsData = array_filter($this->input('variants'), fn($variant) => is_array($variant) && !empty($variant));

            if (!empty($actualVariantsData)) {
                foreach ($actualVariantsData as $variantData) {
                    if (isset($variantData['is_default']) && $this->convertToBoolean($variantData['is_default'])) {
                        $defaultCount++;
                    }
                }

                if ($defaultCount !== 1) {
                    // $this->validator->errors()->add('variants', 'Dokładnie jeden wariant musi być oznaczony jako domyślny.');
                }
            } elseif ($this->input('product_type') === 'standard') {
                // $this->validator->errors()->add('variants', 'Produkt standardowy musi posiadać co najmniej jeden wariant.');
            }
        }

        if ($this->input('product_type') === 'bundle' && empty($this->input('bundle_items'))) {
            // $this->validator->errors()->add('bundle_items', 'Produkt typu zestaw (bundle) musi zawierać co najmniej jeden element składowy.');
        }
    }
}
