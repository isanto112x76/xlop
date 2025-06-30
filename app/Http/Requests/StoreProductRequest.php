<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\ProductVariant; // Dla Rule::unique

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Rozważ dodanie logiki uprawnień
    }

    protected function prepareForValidation()
    {
        // Przetwarzanie danych wejściowych przed walidacją, jeśli to konieczne
        // Np. konwersja pustych stringów na null dla pól opcjonalnych,
        // zapewnienie, że is_default jest booleanem itp.

        $variants = $this->input('variants', []);
        $defaultFound = false;
        foreach ($variants as $variant) {
            if (isset($variant['is_default']) && filter_var($variant['is_default'], FILTER_VALIDATE_BOOLEAN)) {
                $defaultFound = true;
                break;
            }
        }

        if (!$defaultFound && count($variants) > 0) {
            // Jeśli żaden wariant nie jest oznaczony jako domyślny, oznacz pierwszy
            // To jest prosta logika, ProductService również to obsłuży, ale walidacja może tego wymagać.
            // Możesz też rzucić błąd walidacji, jeśli dokładnie jeden nie jest oznaczony jako domyślny.
            $modifiedVariants = $this->input('variants');
            if (isset($modifiedVariants[0])) {
                $modifiedVariants[0]['is_default'] = true;
                $this->merge(['variants' => $modifiedVariants]);
            }
        }
    }


    public function rules(): array
    {
        // Pola dla produktu głównego (dostosowane do Product.php fillable)
        // Klucz 'product.*' został usunięty dla uproszczenia, dane produktu są na najwyższym poziomie.
        // Jeśli wolisz strukturę zagnieżdżoną 'product.*', odpowiednio dostosuj.
        $rules = [
            // Dane produktu
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:100|unique:products,sku', // SKU produktu głównego
            'ean' => ['nullable', 'string', 'max:30', Rule::unique('products', 'ean')->whereNull('deleted_at')],
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id', // Zakładając, że products.category_id to FK do categories.id
            'manufacturer_id' => 'nullable|exists:manufacturers,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'product_type' => ['required', Rule::in(['standard', 'bundle'])], // Zgodnie z Product.php
            'status' => ['required', Rule::in(['active', 'inactive', 'draft', 'archived'])], // Przykładowe, dostosuj
            'manage_stock' => 'required|boolean',
            'weight' => 'nullable|numeric|min:0',
            'attributes' => 'nullable|array',
            'attributes.*' => 'sometimes|string', // Możesz bardziej szczegółowo walidować strukturę JSON
            'marketplace_attributes' => 'nullable|array',
            'inventory_id' => 'required|exists:inventories,id', // Zgodnie z Product.php fillable
            'pos_code' => ['nullable', 'string', 'max:100', Rule::unique('products', 'pos_code')->whereNull('deleted_at')],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('products', 'slug')->whereNull('deleted_at')],
            'baselinker_id' => 'nullable|string|max:255',
            // 'tax_rate_id' -> USUNIĘTE z produktu, przeniesione do cen

            // Warianty
            'variants' => 'required|array|min:1', // Musi być przynajmniej jeden wariant
            'variants.*.name' => 'required_with:variants|string|max:255', // Nazwa wariantu
            'variants.*.sku' => [
                'required_with:variants',
                'string',
                'max:100',
                Rule::unique('product_variants', 'sku')->whereNull('deleted_at')
                // Jeśli SKU wariantu może się powtarzać między różnymi produktami, ale nie w ramach jednego:
                // Rule::unique('product_variants', 'sku')->where(function ($query) {
                // return $query->where('product_id', $this->product_id_being_created_or_updated_if_available);
                // })
            ],
            'variants.*.ean' => [
                'nullable',
                'string',
                'max:30',
                Rule::unique('product_variants', 'ean')->whereNull('deleted_at')
            ],
            'variants.*.barcode' => 'nullable|string|max:100',
            'variants.*.attributes' => 'nullable|array',
            'variants.*.is_default' => 'required_with:variants|boolean',
            'variants.*.position' => 'nullable|integer|min:0',

            // Ceny dla wariantów (jako tablica obiektów cenowych)
            'variants.*.prices' => 'sometimes|array',
            // Jeśli 'prices' są wymagane dla każdego wariantu: 'required_with:variants|array|min:1'
            'variants.*.prices.*.type' => [
                'required_with:variants.*.prices',
                'string',
                Rule::in(['retail', 'purchase', 'wholesale', 'promo', 'base']) // Zgodnie z migracją product_prices
            ],
            'variants.*.prices.*.currency' => 'required_with:variants.*.prices|string|size:3',
            'variants.*.prices.*.price_net' => 'required_with:variants.*.prices|numeric|min:0',
            'variants.*.prices.*.price_gross' => 'required_with:variants.*.prices|numeric|min:0',
            'variants.*.prices.*.tax_rate_id' => [ // Kluczowe po refaktoryzacji
                'required_with:variants.*.prices',
                'integer',
                'exists:tax_rates,id'
            ],
            'variants.*.prices.*.valid_from' => 'nullable|date_format:Y-m-d',
            'variants.*.prices.*.valid_to' => 'nullable|date_format:Y-m-d|after_or_equal:variants.*.prices.*.valid_from',

            // Stany magazynowe dla wariantów
            'variants.*.stock_levels' => 'sometimes|array',
            'variants.*.stock_levels.*.warehouse_id' => 'required_with:variants.*.stock_levels|exists:warehouses,id',
            'variants.*.stock_levels.*.quantity' => 'required_with:variants.*.stock_levels|integer|min:0',
            'variants.*.stock_levels.*.reserved_quantity' => 'nullable|integer|min:0',
            'variants.*.stock_levels.*.incoming_quantity' => 'nullable|integer|min:0',
            'variants.*.stock_levels.*.location' => 'nullable|string|max:255',

            // Zdjęcia dla wariantów
            'variants.*.new_images' => 'nullable|array',
            'variants.*.new_images.*' => 'image|mimes:jpeg,png,jpg,webp|max:2048', // Max 2MB

            // Zestawy (Bundle Items)
            'bundle_items' => 'required_if:product_type,bundle|array|min:1',
            'bundle_items.*.component_variant_id' => 'required_with:bundle_items|exists:product_variants,id',
            'bundle_items.*.quantity' => 'required_with:bundle_items|integer|min:1',

            // Tagi
            'tags' => 'nullable|array',
            'tags.*' => 'integer|exists:tags,id',

            // Główne zdjęcia produktu
            'images' => 'nullable|array', // Lub 'new_main_images' dla spójności
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:2048',
        ];


        $rules['variants'] = [
            'required',
            'array',
            'min:1',
            function ($attribute, $value, $fail) {
                $defaultCount = 0;
                foreach ($value as $variant) {
                    if (isset($variant['is_default']) && filter_var($variant['is_default'], FILTER_VALIDATE_BOOLEAN)) {
                        $defaultCount++;
                    }
                }
                if ($defaultCount !== 1) {
                    $fail('Dokładnie jeden wariant musi być oznaczony jako domyślny.');
                }
            }
        ];


        return $rules;
    }

    public function messages(): array
    {
        return [
            'product_type.required' => 'Typ produktu jest wymagany.',
            'product_type.in' => 'Wybrany typ produktu jest nieprawidłowy.',
            'name.required' => 'Nazwa produktu jest wymagana.',
            'sku.required' => 'SKU produktu jest wymagane.',
            'sku.unique' => 'Podane SKU produktu już istnieje.',
            // ... inne komunikaty ...
            'variants.required' => 'Produkt musi posiadać co najmniej jeden wariant.',
            'variants.*.sku.required' => 'SKU wariantu jest wymagane.',
            'variants.*.sku.unique' => 'Podane SKU wariantu już istnieje.',
            'variants.*.prices.*.tax_rate_id.required' => 'Stawka VAT dla ceny wariantu jest wymagana.',
            'variants.*.is_default.required' => 'Należy określić, czy wariant jest domyślny.',
        ];
    }
}
