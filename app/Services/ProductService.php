<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductPrice;
use App\Models\StockLevel;
use App\Models\ProductLink;
use App\Models\ProductBundleItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;

class ProductService
{
    protected function sanitizeJsonInput($input, array $defaultStructure = [], bool $mergeDefaults = false): ?array
    {
        $parsedInput = null;
        if (is_string($input)) {
            $decoded = json_decode($input, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $parsedInput = $decoded;
            }
        } elseif (is_array($input)) {
            $parsedInput = $input;
        }
        if ($parsedInput !== null) {
            if ($mergeDefaults) {
                return array_replace_recursive($defaultStructure, $parsedInput);
            }
            return $parsedInput;
        }
        return $defaultStructure;
    }

    public function createProduct(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            $productData = [
                'name' => Arr::get($data, 'name'),
                'slug' => Arr::get($data, 'slug', Str::slug(Arr::get($data, 'name', ''))),
                'sku' => Arr::get($data, 'sku'),
                'ean' => Arr::get($data, 'ean'),
                'pos_code' => Arr::get($data, 'pos_code'),
                'foreign_id' => Arr::get($data, 'foreign_id'),

                'description' => $this->sanitizeJsonInput(Arr::get($data, 'description'), ['short' => null, 'full' => null, 'features' => []], true),
                'attributes' => $this->sanitizeJsonInput(Arr::get($data, 'attributes'), [], false),
                'marketplace_attributes' => $this->sanitizeJsonInput(Arr::get($data, 'marketplace_attributes'), ['parameters' => [], 'long_description' => []], true),
                'dimensions' => $this->sanitizeJsonInput(Arr::get($data, 'dimensions'), ['length' => null, 'width' => null, 'height' => null], true),

                'status' => Arr::get($data, 'status', 'draft'),
                'product_type' => Arr::get($data, 'product_type', 'standard'),
                'category_id' => Arr::get($data, 'category_id'),
                'manufacturer_id' => Arr::get($data, 'manufacturer_id'),
                'supplier_id' => Arr::get($data, 'supplier_id'),
                'weight' => Arr::get($data, 'weight') ? (float) Arr::get($data, 'weight') : null,

                'manage_stock' => filter_var(Arr::get($data, 'manage_stock', true), FILTER_VALIDATE_BOOLEAN),
                'variants_share_stock' => filter_var(Arr::get($data, 'variants_share_stock', false), FILTER_VALIDATE_BOOLEAN),
            ];

            $product = Product::create($productData);

            if (array_key_exists('tag_ids', $data)) {
                $product->tags()->sync(Arr::get($data, 'tag_ids', []));
            }

            // Warianty i domyślny wariant
            if (empty(Arr::get($data, 'variants')) && $productData['product_type'] !== 'bundle') {
                $defaultVariantData = [
                    'name' => '',
                    'sku' => $product->sku,
                    'ean' => $product->ean,
                    'is_default' => true,
                    'position' => 0,
                    'attributes_override' => null,
                    'marketplace_attributes_override' => null,
                    'description_override' => null,
                    'prices' => Arr::get($data, 'default_variant_prices', []),
                    'stock_levels' => Arr::get($data, 'default_variant_stock_levels', []),
                ];
                $this->createOrUpdateVariant($product, $defaultVariantData, 0, true);
            } elseif (Arr::has($data, 'variants')) {
                $this->synchronizeVariants($product, Arr::get($data, 'variants', []));
            }

            if (Arr::has($data, 'product_links')) {
                $this->syncProductLinks($product, Arr::get($data, 'product_links', []));
            }

            if ($productData['product_type'] === 'bundle' && Arr::has($data, 'bundle_items')) {
                $this->syncBundleItems($product, Arr::get($data, 'bundle_items', []));
            }

            if (isset($data['new_images']) && is_array($data['new_images'])) {
                foreach ($data['new_images'] as $imageFile) {
                    if ($imageFile instanceof UploadedFile) {
                        try {
                            $product->addMedia($imageFile)->toMediaCollection('product_images');
                        } catch (FileDoesNotExist | FileIsTooBig $e) {
                            \Log::error("ProductService: Błąd uploadu zdjęcia (create): " . $e->getMessage());
                        }
                    }
                }
            }
            $product->refresh()->load(Product::DEFAULT_PRODUCT_LOAD);
            return $product;
        });
    }

    public function updateProduct(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data) {
            $productData = [
                'name' => Arr::get($data, 'name', $product->name),
                'slug' => Arr::get($data, 'slug', $product->slug),
                'sku' => Arr::get($data, 'sku', $product->sku),
                'ean' => Arr::get($data, 'ean', $product->ean),
                'pos_code' => Arr::get($data, 'pos_code', $product->pos_code),
                'foreign_id' => Arr::get($data, 'foreign_id', $product->foreign_id),
                'description' => $this->sanitizeJsonInput(Arr::get($data, 'description'), $product->description ?? ['short' => null, 'full' => null, 'features' => []], true),
                'attributes' => $this->sanitizeJsonInput(Arr::get($data, 'attributes'), [], false),
                'marketplace_attributes' => $this->sanitizeJsonInput(
                    Arr::get($data, 'marketplace_attributes'),
                    ['parameters' => [], 'long_description' => []],
                    false
                ),
                'dimensions' => $this->sanitizeJsonInput(Arr::get($data, 'dimensions'), $product->dimensions ?? ['length' => null, 'width' => null, 'height' => null], true),
                'status' => Arr::get($data, 'status', $product->status),
                'product_type' => Arr::get($data, 'product_type', $product->product_type),
                'category_id' => Arr::get($data, 'category_id', $product->category_id),
                'manufacturer_id' => Arr::get($data, 'manufacturer_id', $product->manufacturer_id),
                'supplier_id' => Arr::get($data, 'supplier_id', $product->supplier_id),
                'weight' => Arr::get($data, 'weight', $product->weight) !== null ? (float) Arr::get($data, 'weight', $product->weight) : null,
                'manage_stock' => filter_var(Arr::get($data, 'manage_stock', $product->manage_stock), FILTER_VALIDATE_BOOLEAN),
                'variants_share_stock' => filter_var(Arr::get($data, 'variants_share_stock', $product->variants_share_stock), FILTER_VALIDATE_BOOLEAN),
            ];

            $product->update($productData);

            if (Arr::has($data, 'tag_ids')) {
                $product->tags()->sync(Arr::get($data, 'tag_ids', []));
            }
            if (Arr::has($data, 'product_links')) {
                $this->syncProductLinks($product, Arr::get($data, 'product_links', []));
            }
            if (Arr::has($data, 'variants')) {
                $this->synchronizeVariants($product, Arr::get($data, 'variants', []));
            }

            $currentProductType = Arr::get($data, 'product_type', $product->product_type);
            if ($currentProductType === 'bundle' && Arr::has($data, 'bundle_items')) {
                $this->syncBundleItems($product, Arr::get($data, 'bundle_items', []));
            } elseif ($currentProductType !== 'bundle') {
                if ($product->bundleItems()->exists()) {
                    $product->bundleItems()->delete();
                }
            }

            // Media (zakładając, że te dane są wysyłane w głównym payloadzie produktu)
            if (isset($data['new_images']) && is_array($data['new_images'])) {
                foreach ($data['new_images'] as $imageFile) {
                    if ($imageFile instanceof UploadedFile) {
                        try {
                            $product->addMedia($imageFile)->toMediaCollection('product_images');
                        } catch (FileDoesNotExist | FileIsTooBig $e) {
                            \Log::error("ProductService: Błąd uploadu zdjęcia produktu (update): " . $e->getMessage());
                        }
                    }
                }
            }
            if (isset($data['deleted_image_ids']) && is_array($data['deleted_image_ids'])) {
                $product->getMedia('product_images')->whereIn('id', $data['deleted_image_ids'])->each(fn(Media $media) => $media->delete());
            }
            if (isset($data['image_order']) && is_array($data['image_order'])) {
                $this->updateMediaOrder($product, $data['image_order'], 'product_images');
            }

            $product->refresh()->load(Product::DEFAULT_PRODUCT_LOAD);
            return $product;
        });
    }

    // ---------- Poniżej bez zmian, ale bez powielania zbędnych danych ------------

    protected function createOrUpdateVariant(Product $product, array $variantInput, int $position, bool $isMarkedAsDefaultInInput): ProductVariant
    {
        $variantId = Arr::get($variantInput, 'id');
        $existingVariant = $variantId ? $product->variants()->find($variantId) : null;

        $variantData = [
            'name' => Arr::get($variantInput, 'name', $existingVariant?->name),
            'sku' => Arr::get($variantInput, 'sku', $existingVariant?->sku),
            'ean' => Arr::get($variantInput, 'ean', $existingVariant?->ean),
            'barcode' => Arr::get($variantInput, 'barcode', $existingVariant?->barcode) !== null ? (string) Arr::get($variantInput, 'barcode', $existingVariant?->barcode) : null,
            'position' => Arr::get($variantInput, 'position', $existingVariant?->position ?? $position),
            'is_default' => $isMarkedAsDefaultInInput,
            'description_override' => $this->sanitizeJsonInput(Arr::get($variantInput, 'description_override'), $existingVariant?->description_override ?? ['short' => null, 'full' => null, 'features' => []]),
            'weight_override' => Arr::get($variantInput, 'weight_override', $existingVariant?->weight_override) !== null ? (float) Arr::get($variantInput, 'weight_override', $existingVariant?->weight_override) : null,
            'attributes_override' => $this->sanitizeJsonInput(Arr::get($variantInput, 'attributes_override'), $existingVariant?->attributes_override ?? []),
            'marketplace_attributes_override' => $this->sanitizeJsonInput(Arr::get($variantInput, 'marketplace_attributes_override'), $existingVariant?->marketplace_attributes_override ?? ['parameters' => [], 'long_description' => []]),
            'override_product_description' => filter_var(Arr::get($variantInput, 'override_product_description', $existingVariant?->override_product_description ?? false), FILTER_VALIDATE_BOOLEAN),
            'override_product_weight' => filter_var(Arr::get($variantInput, 'override_product_weight', $existingVariant?->override_product_weight ?? false), FILTER_VALIDATE_BOOLEAN),
            'override_product_attributes' => filter_var(Arr::get($variantInput, 'override_product_attributes', $existingVariant?->override_product_attributes ?? false), FILTER_VALIDATE_BOOLEAN),
            'override_product_marketplace_attributes' => filter_var(Arr::get($variantInput, 'override_product_marketplace_attributes', $existingVariant?->override_product_marketplace_attributes ?? false), FILTER_VALIDATE_BOOLEAN),
            'has_own_media' => filter_var(Arr::get($variantInput, 'has_own_media', $existingVariant?->has_own_media ?? false), FILTER_VALIDATE_BOOLEAN),
        ];

        if (!empty($variantInput['slug'])) {
            $variantData['slug'] = $variantInput['slug'];
        }

        $variant = $product->variants()->updateOrCreate(['id' => $variantId], $variantData);

        if (empty($variant->slug) && ($variant->wasRecentlyCreated || $variant->isDirty('name') || ($variant->product && $variant->product->isDirty('name')))) {
            $variant->slug = ProductVariant::generateUniqueSlug($variant, $product->name, $variant->name);
            $variant->saveQuietly();
        }

        if (Arr::has($variantInput, 'prices')) {
            $this->syncVariantPrices($variant, Arr::get($variantInput, 'prices', []));
        }
        if (Arr::has($variantInput, 'stock_levels')) {
            $this->applyStockLogicOnVariantSave($product, $variant, Arr::get($variantInput, 'stock_levels', []));
        }

        if ($variant->has_own_media) {
            if (isset($variantInput['deleted_variant_image_ids']) && is_array($variantInput['deleted_variant_image_ids'])) {
                $variant->getMedia('variant_images')->whereIn('id', $variantInput['deleted_variant_image_ids'])->each(fn(Media $media) => $media->delete());
            }
            if (isset($variantInput['variant_image_order']) && is_array($variantInput['variant_image_order'])) {
                $this->updateMediaOrder($variant, $variantInput['variant_image_order'], 'variant_images');
            }
        } elseif (array_key_exists('has_own_media', $variantInput) && !$variantInput['has_own_media']) {
            $variant->clearMediaCollection('variant_images');
        }
        return $variant;
    }

    protected function syncVariantPrices(ProductVariant $variant, array $pricesInput): void
    {
        $existingPriceIds = $variant->prices()->pluck('id')->toArray();
        $inputPriceIds = array_filter(array_map(fn($p) => is_array($p) ? (int) (Arr::get($p, 'id', 0)) : 0, $pricesInput));
        $pricesToDelete = array_diff($existingPriceIds, $inputPriceIds);

        if (!empty($pricesToDelete)) {
            ProductPrice::whereIn('id', $pricesToDelete)->where('variant_id', $variant->id)->delete();
        }

        foreach ($pricesInput as $priceInput) {
            if (!is_array($priceInput) || !isset($priceInput['tax_rate_id']))
                continue;

            $priceData = [
                'variant_id' => $variant->id,
                'type' => Arr::get($priceInput, 'type', 'standard'),
                'price_net' => Arr::get($priceInput, 'price_net', 0.0),
                'price_gross' => Arr::get($priceInput, 'price_gross', 0.0),
                'tax_rate_id' => Arr::get($priceInput, 'tax_rate_id'),
                'currency' => Arr::get($priceInput, 'currency', 'PLN'),
                'valid_from' => Arr::get($priceInput, 'valid_from'),
                'valid_to' => Arr::get($priceInput, 'valid_to'),
                'baselinker_price_group_id' => Arr::get($priceInput, 'baselinker_price_group_id'),
            ];
            ProductPrice::updateOrCreate(['id' => Arr::get($priceInput, 'id')], $priceData);
        }
    }

    protected function syncVariantStockLevels(ProductVariant $variant, array $stockLevelsInput): void
    {
        $existingStockLevelCompositeKeys = $variant->stockLevels()
            ->get(['id', 'warehouse_id'])
            ->mapWithKeys(fn($sl) => [$sl->warehouse_id => $sl->id])
            ->toArray();

        $inputStockLevelWarehouseIds = [];
        foreach ($stockLevelsInput as $stockInput) {
            if (!is_array($stockInput) || empty($stockInput['warehouse_id']))
                continue;
            $inputStockLevelWarehouseIds[] = (int) $stockInput['warehouse_id'];

            StockLevel::updateOrCreate(
                [
                    'product_variant_id' => $variant->id,
                    'warehouse_id' => (int) $stockInput['warehouse_id'],
                ],
                [
                    'quantity' => Arr::get($stockInput, 'quantity', 0),
                    'reserved_quantity' => Arr::get($stockInput, 'reserved_quantity', 0),
                    'incoming_quantity' => Arr::get($stockInput, 'incoming_quantity', 0),
                    'location' => Arr::get($stockInput, 'location'),
                ]
            );
        }
        $warehousesToDeleteStockFor = array_diff(array_keys($existingStockLevelCompositeKeys), $inputStockLevelWarehouseIds);
        if (!empty($warehousesToDeleteStockFor)) {
            StockLevel::where('product_variant_id', $variant->id)
                ->whereIn('warehouse_id', $warehousesToDeleteStockFor)
                ->delete();
        }
    }

    protected function syncProductLinks(Product $product, array $linksInput): void
    {
        $product->links()->delete();
        foreach ($linksInput as $linkInput) {
            if (!is_array($linkInput) || empty(Arr::get($linkInput, 'platform')) || empty(Arr::get($linkInput, 'external_id')))
                continue;
            $product->links()->create([
                'platform' => Arr::get($linkInput, 'platform'),
                'external_id' => Arr::get($linkInput, 'external_id'),
                'url' => Arr::get($linkInput, 'url'),
                'synchronized_at' => Arr::get($linkInput, 'synchronized_at'),
            ]);
        }
    }

    protected function syncBundleItems(Product $product, array $itemsInput): void
    {
        $product->bundleItems()->delete();
        foreach ($itemsInput as $itemInput) {
            $componentVariantId = Arr::get($itemInput, 'component_variant_id') ?? Arr::get($itemInput, 'item_variant_id');
            if (!is_array($itemInput) || empty($componentVariantId) || !isset($itemInput['quantity']))
                continue;

            $product->bundleItems()->create([
                'item_variant_id' => $componentVariantId,
                'quantity' => $itemInput['quantity'],
            ]);
        }
    }

    public function updateMediaOrder(Model $model, array $mediaIds, string $collectionName): void
    {
        if (!method_exists($model, 'getMedia') || !$model instanceof \Spatie\MediaLibrary\HasMedia) {
            \Log::warning("Model " . get_class($model) . " ID: {$model->id} nie używa traita InteractsWithMedia lub getMedia jest niedostępne.");
            return;
        }
        $sanitizedMediaIds = array_values(array_unique(array_filter(array_map('intval', $mediaIds), fn($id) => $id > 0)));

        if (empty($sanitizedMediaIds)) {
            if (!empty($mediaIds)) {
                \Log::warning("updateMediaOrder: pusta tablica mediaIds po sanitacji dla " . get_class($model) . " ID: {$model->id}, kolekcja: {$collectionName}. Oryginalne ID: " . implode(',', $mediaIds));
            }
            return;
        }

        try {
            \Spatie\MediaLibrary\MediaCollections\Models\Media::setNewOrder($sanitizedMediaIds);
        } catch (\Exception $e) {
            \Log::error("Błąd podczas ustawiania nowej kolejności mediów (Spatie): " . $e->getMessage() . ". Próba ręcznej aktualizacji.");
            DB::transaction(function () use ($sanitizedMediaIds) {
                foreach ($sanitizedMediaIds as $order => $mediaId) {
                    Media::where('id', $mediaId)->update(['order_column' => $order + 1]);
                }
            });
        }
    }

    // --- Wariant CRUDy bez powielania danych ---

    public function createVariantForProduct(Product $product, array $data): ProductVariant
    {
        return DB::transaction(function () use ($product, $data) {
            $isDefaultCandidate = filter_var(Arr::get($data, 'is_default', false), FILTER_VALIDATE_BOOLEAN);
            $liveVariantsCount = $product->variants()->whereNull('deleted_at')->count();

            if ($liveVariantsCount === 0) {
                $isDefaultCandidate = true;
            }

            $position = Arr::get($data, 'position', ($liveVariantsCount > 0 ? $product->variants()->whereNull('deleted_at')->max('position') + 1 : 0));
            $variant = $this->createOrUpdateVariant($product, $data, $position, $isDefaultCandidate);

            $this->ensureDefaultVariantExists($product);
            return $variant;
        });
    }

    public function updateVariant(ProductVariant $variant, array $data): ProductVariant
    {
        return DB::transaction(function () use ($variant, $data) {
            $product = $variant->product()->withTrashed()->firstOrFail();
            $isMarkedAsDefaultInInput = filter_var(Arr::get($data, 'is_default', $variant->is_default), FILTER_VALIDATE_BOOLEAN);

            $updatedVariant = $this->createOrUpdateVariant($product, array_merge($data, ['id' => $variant->id]), Arr::get($data, 'position', $variant->position), $isMarkedAsDefaultInInput);

            $this->ensureDefaultVariantExists($product);
            return $updatedVariant;
        });
    }

    public function deleteVariant(ProductVariant $variant): bool
    {
        return DB::transaction(function () use ($variant) {
            $product = $variant->product()->withTrashed()->first();
            if (!$product) {
                return $variant->delete();
            }

            $liveVariantsCount = $product->variants()->where('id', '!=', $variant->id)->whereNull('deleted_at')->count();

            if ($liveVariantsCount < 1 && !$variant->trashed()) {
                if ($product->type === 'standard') {
                    throw new \Exception("Nie można usunąć ostatniego aktywnego wariantu produktu standardowego.");
                }
            }

            $wasDefault = $variant->is_default;
            $deleted = $variant->delete();

            if ($deleted && $wasDefault) {
                $this->ensureDefaultVariantExists($product);
            }
            return (bool) $deleted;
        });
    }

    protected function applyStockLogicOnVariantSave(Product $product, ProductVariant $variant, array $stockLevelsInput): void
    {
        if ($product->manage_stock) {
            if ($product->variants_share_stock) {
                $defaultVariantForProduct = $product->defaultVariant()->first();
                if ($defaultVariantForProduct && $variant->id === $defaultVariantForProduct->id) {
                    $this->syncVariantStockLevels($variant, $stockLevelsInput);
                } else {
                    if ($variant->stockLevels()->exists()) {
                        $variant->stockLevels()->delete();
                    }
                }
            } else {
                $this->syncVariantStockLevels($variant, $stockLevelsInput);
            }
        } else {
            $defaultVariantForProduct = $product->defaultVariant()->first();
            if ($defaultVariantForProduct && $variant->id === $defaultVariantForProduct->id) {
                $this->syncVariantStockLevels($variant, $stockLevelsInput);
            } else {
                if ($variant->stockLevels()->exists()) {
                    $variant->stockLevels()->delete();
                }
            }
        }
    }

    public function ensureDefaultVariantExists(Product $product): void
    {
        $liveVariants = $product->variants()->whereNull('deleted_at')->orderBy('position')->get();

        if ($liveVariants->isNotEmpty()) {
            $currentDefault = $liveVariants->firstWhere('is_default', true);
            if (!$currentDefault) {
                $newDefault = $liveVariants->first();
                if ($newDefault) {
                    DB::table('product_variants')->where('id', $newDefault->id)->update(['is_default' => true]);
                    $product->load('defaultVariant');
                }
            } else {
                ProductVariant::where('product_id', $product->id)
                    ->where('id', '!=', $currentDefault->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
        }
        $product->load('defaultVariant');
    }

    protected function synchronizeVariants(Product $product, array $variantsInput): void
    {
        $existingLiveVariantIds = $product->variants()->whereNull('deleted_at')->pluck('id')->toArray();
        $inputVariantIds = [];
        $targetDefaultVariantIdFromInput = null;

        foreach ($variantsInput as $variantInput) {
            if (!is_array($variantInput))
                continue;
            $currentVariantId = Arr::get($variantInput, 'id');
            if ($currentVariantId) {
                $inputVariantIds[] = (int) $currentVariantId;
            }
            if (filter_var(Arr::get($variantInput, 'is_default', false), FILTER_VALIDATE_BOOLEAN)) {
                $targetDefaultVariantIdFromInput = $currentVariantId ? (int) $currentVariantId : 'new_variant_is_default';
            }
        }

        $variantsToDeleteIds = array_diff($existingLiveVariantIds, $inputVariantIds);
        if (!empty($variantsToDeleteIds)) {
            $variantsBeingDeleted = ProductVariant::whereIn('id', $variantsToDeleteIds)->where('product_id', $product->id)->get();
            foreach ($variantsBeingDeleted as $variantToDel) {
                try {
                    $this->deleteVariant($variantToDel);
                } catch (\Exception $e) {
                    \Log::warning("Nie można usunąć wariantu ID {$variantToDel->id} podczas synchronizacji: {$e->getMessage()}");
                }
            }
        }

        $processedDefaultCandidate = false;
        foreach ($variantsInput as $index => $variantInput) {
            if (!is_array($variantInput))
                continue;
            $isMarkedAsDefault = false;
            $currentVariantIdInLoop = Arr::get($variantInput, 'id');

            if ($targetDefaultVariantIdFromInput === 'new_variant_is_default' && !$currentVariantIdInLoop && !$processedDefaultCandidate) {
                $isMarkedAsDefault = true;
                $processedDefaultCandidate = true;
            } elseif ($currentVariantIdInLoop && (int) $currentVariantIdInLoop === $targetDefaultVariantIdFromInput) {
                $isMarkedAsDefault = true;
            }

            $this->createOrUpdateVariant($product, $variantInput, Arr::get($variantInput, 'position', $index), $isMarkedAsDefault);
        }
        $this->ensureDefaultVariantExists($product);
    }

    public function deleteProduct(Product $product): bool
    {
        return DB::transaction(function () use ($product) {
            foreach ($product->variants()->get() as $variant) {
                $variant->delete();
            }
            return $product->delete();
        });
    }
}
