<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SynchronizationLog;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BaselinkerService
{
    protected PendingRequest $client;
    protected string $token;
    protected int $inventoryId;

    /**
     * Konstruktor serwisu Baselinker.
     * Inicjalizuje klienta HTTP i wczytuje podstawowe dane konfiguracyjne.
     * @throws \Exception
     */
    public function __construct()
    {
        $this->token = config('baselinker.api.token');
        $apiUrl = config('baselinker.api.url');
        $this->inventoryId = config('baselinker.inventory_id');

        if (!$this->token || !$apiUrl || !$this->inventoryId) {
            $errorMessage = 'Baselinker configuration is incomplete. Check .env and config/baselinker.php';
            Log::emergency($errorMessage);
            throw new \Exception($errorMessage);
        }

        $this->client = Http::withHeaders([
            'X-BLToken' => $this->token,
        ])->baseUrl($apiUrl)->acceptJson();
    }

    /**
     * Wysyła zapytanie do API Baselinker.
     *
     * @param string $method Nazwa metody API Baselinker.
     * @param array $params Parametry zapytania.
     * @return array|null Wynik zapytania w formie tablicy lub null w przypadku błędu.
     */
    public function sendRequest(string $method, array $params = []): ?array
    {
        try {
            $response = $this->client->asForm()->post('/connector.php', [
                'method' => $method,
                'parameters' => json_encode($params),
            ]);

            if ($response->failed()) {
                $response->throw();
            }

            $data = $response->json();

            if (isset($data['status']) && $data['status'] === 'ERROR') {
                $errorMessage = "Baselinker API Error for method '{$method}': " . ($data['error_message'] ?? 'Unknown error');
                Log::error($errorMessage, ['params' => $params, 'response' => $data, 'error_code' => $data['error_code'] ?? 'N/A']);
                throw new \Exception($errorMessage);
            }

            return $data;
        } catch (\Exception $e) {
            Log::critical("Baselinker API request failed for method '{$method}': " . $e->getMessage(), ['params' => $params]);
            throw $e;
        }
    }

    /**
     * Zapisuje log operacji do bazy danych.
     *
     * @param string $direction 'to_baselinker' | 'from_baselinker'
     * @param string $resourceType 'product' | 'category' | 'order' | 'stock'
     * @param string $status 'success' | 'failed' | 'in_progress'
     * @param string|null $message Komunikat dla użytkownika.
     * @param int|null $localId ID zasobu w naszej bazie.
     * @param string|int|null $externalId ID zasobu w Baselinkerze.
     */
    private function logSync(string $direction, string $resourceType, string $status, ?string $message = null, ?int $localId = null, $externalId = null): void
    {
        SynchronizationLog::create([
            'direction' => $direction,
            'resource_type' => $resourceType,
            'status' => $status,
            'message' => $message,
            'local_id' => $localId,
            'external_id' => $externalId,
        ]);
    }
    /**
     * Pobiera listę zdarzeń (journal) z Baselinkera.
     *
     * @param int $lastLogId ID ostatniego pobranego zdarzenia.
     * @param array $eventTypes Tablica z typami zdarzeń do pobrania.
     * @return array|null
     */
    public function getJournalList(int $lastLogId, array $eventTypes = []): ?array
    {
        $params = ['last_log_id' => $lastLogId];
        if (!empty($eventTypes)) {
            $params['logs_types'] = $eventTypes;
        }

        return $this->sendRequest('getJournalList', $params);
    }

    /**
     * Pobiera pełne dane dla określonych zamówień.
     *
     * @param array $orderIds Tablica ID zamówień do pobrania.
     * @return array|null
     */
    public function getOrders(array $orderIds): ?array
    {
        return $this->sendRequest('getOrders', ['order_ids' => $orderIds]);
    }
    // ----------------------------------------------------------------------------------
    // METODY DO ZARZĄDZANIA KATEGORIAMI
    // ----------------------------------------------------------------------------------

    public function getInventoryCategories(): ?array
    {
        return $this->sendRequest('getInventoryCategories', ['inventory_id' => $this->inventoryId]);
    }

    public function syncInventoryCategory(Category $category): ?array
    {
        $operation = $category->baselinker_category_id ? 'update' : 'add';
        try {
            $params = [
                'inventory_id' => $this->inventoryId,
                'name' => $category->name,
                'parent_id' => $category->parent->baselinker_category_id ?? 0,
            ];

            // Jeśli aktualizujemy, dodajemy ID kategorii
            if ($category->baselinker_category_id) {
                $params['category_id'] = $category->baselinker_category_id;
            }

            $response = $this->sendRequest('addInventoryCategory', $params);

            if ($response && $response['status'] === 'SUCCESS') {
                $baselinkerId = $response['category_id'];
                if (!$category->baselinker_category_id) {
                    $category->baselinker_category_id = $baselinkerId;
                    $category->saveQuietly();
                }
                $this->logSync('to_baselinker', 'category', 'success', "Successfully {$operation}d category '{$category->name}'.", $category->id, $baselinkerId);
            }
            return $response;
        } catch (\Exception $e) {
            $this->logSync('to_baselinker', 'category', 'failed', "Failed to {$operation} category '{$category->name}': " . $e->getMessage(), $category->id, $category->baselinker_category_id);
            return null;
        }
    }

    public function updateInventoryCategory(Category $category): ?array
    {
        try {
            if (!$category->baselinker_category_id) {
                throw new \Exception("Cannot update category '{$category->name}': missing baselinker_category_id.");
            }
            $params = [
                'inventory_id' => $this->inventoryId,
                'category_id' => $category->baselinker_category_id,
                'name' => $category->name,
                'parent_id' => $category->parent->baselinker_category_id ?? 0,
            ];
            $response = $this->sendRequest('updateInventoryCategory', $params);
            $this->logSync('to_baselinker', 'category', 'success', "Successfully updated category '{$category->name}'.", $category->id, $category->baselinker_category_id);
            return $response;
        } catch (\Exception $e) {
            $this->logSync('to_baselinker', 'category', 'failed', "Failed to update category '{$category->name}': " . $e->getMessage(), $category->id, $category->baselinker_category_id);
            return null;
        }
    }

    public function deleteInventoryCategory(int $baselinkerCategoryId, int $localCategoryId): ?array
    {
        try {
            $response = $this->sendRequest('deleteInventoryCategory', [
                'inventory_id' => $this->inventoryId,
                'category_id' => $baselinkerCategoryId,
            ]);
            $this->logSync('to_baselinker', 'category', 'success', "Successfully deleted category from Baselinker.", $localCategoryId, $baselinkerCategoryId);
            return $response;
        } catch (\Exception $e) {
            $this->logSync('to_baselinker', 'category', 'failed', "Failed to delete category: " . $e->getMessage(), $localCategoryId, $baselinkerCategoryId);
            return null;
        }
    }

    // ----------------------------------------------------------------------------------
    // METODY DO ZARZĄDZANIA PRODUKTAMI
    // ----------------------------------------------------------------------------------

    public function syncInventoryProduct(Product $product): ?array
    {
        $operation = $product->baselinker_id ? 'update' : 'add';
        try {
            $product->load('variants.prices.taxRate', 'variants.stockLevels.warehouse', 'variants.media', 'category', 'media');
            $payload = $this->mapProductToBaselinkerPayload($product, $product->baselinker_id);
            $response = $this->sendRequest('addInventoryProduct', $payload);

            if ($response && $response['status'] === 'SUCCESS') {
                $baselinkerId = $response['product_id'];
                if (!$product->baselinker_id) {
                    $product->baselinker_id = $baselinkerId;
                    $product->saveQuietly();
                }
                if (isset($response['variant_ids'])) {
                    foreach ($response['variant_ids'] as $ourVariantId => $baselinkerVariantId) {
                        ProductVariant::where('id', $ourVariantId)->update(['baselinker_variant_id' => $baselinkerVariantId]);
                    }
                }
                $this->logSync('to_baselinker', 'product', 'success', "Successfully {$operation}d product '{$product->name}'.", $product->id, $baselinkerId);
            }
            return $response;
        } catch (\Exception $e) {
            $this->logSync('to_baselinker', 'product', 'failed', "Failed to {$operation} product '{$product->name}': " . $e->getMessage(), $product->id, $product->baselinker_id);
            return null;
        }
    }

    public function updateInventoryProduct(Product $product): ?array
    {
        try {
            if (!$product->baselinker_id) {
                throw new \Exception("Cannot update product '{$product->name}': missing baselinker_id.");
            }
            $product->load('variants.prices.taxRate', 'variants.stockLevels.warehouse', 'variants.media', 'category', 'media');
            $payload = $this->mapProductToBaselinkerPayload($product, $product->baselinker_id);
            $response = $this->sendRequest('updateInventoryProduct', $payload);
            $this->logSync('to_baselinker', 'product', 'success', "Successfully updated product '{$product->name}'.", $product->id, $product->baselinker_id);
            return $response;
        } catch (\Exception $e) {
            $this->logSync('to_baselinker', 'product', 'failed', "Failed to update product '{$product->name}': " . $e->getMessage(), $product->id, $product->baselinker_id);
            return null;
        }
    }

    public function deleteInventoryProduct(int $baselinkerProductId, int $localProductId): ?array
    {
        try {
            $response = $this->sendRequest('deleteInventoryProduct', [
                'inventory_id' => $this->inventoryId,
                'product_id' => $baselinkerProductId,
            ]);
            $this->logSync('to_baselinker', 'product', 'success', "Successfully deleted product from Baselinker.", $localProductId, $baselinkerProductId);
            return $response;
        } catch (\Exception $e) {
            $this->logSync('to_baselinker', 'product', 'failed', "Failed to delete product: " . $e->getMessage(), $localProductId, $baselinkerProductId);
            return null;
        }
    }

    private function mapProductToBaselinkerPayload(Product $product, ?int $baselinkerProductId = null): array
    {
        $defaultVariant = $product->defaultVariant ?? $product->variants->first();
        $marketplaceAttr = $product->marketplace_attributes ?? [];
        $images = $product->getMedia('product_images')->map(fn($media) => $media->getFullUrl())->toArray();
        $features = [];
        $attributes = $product->attributes ?? [];
        if (is_array($attributes)) {
            foreach ($attributes as $key => $value) {
                if (is_scalar($key) && is_scalar($value)) {
                    $features[(string) $key] = (string) $value;
                }
            }
        }
        $variantsData = [];
        $sortedVariants = $product->variants->sortByDesc('is_default');
        foreach ($sortedVariants as $variant) {
            $prices = [];
            foreach ($variant->prices as $priceEntry) {
                if ($priceEntry->baselinker_price_group_id && $priceEntry->type === 'retail') {
                    $prices[$priceEntry->baselinker_price_group_id] = (float) $priceEntry->price;
                }
            }
            $stock = [];
            $warehouseMappings = config('baselinker.warehouses');
            foreach ($variant->stockLevels as $stockLevel) {
                $baselinkerWarehouseId = array_search($stockLevel->warehouse->id, $warehouseMappings);
                if ($baselinkerWarehouseId !== false) {
                    $stock[$baselinkerWarehouseId] = (int) $stockLevel->quantity;
                }
            }
            $variantName = $variant->override_product_name ? $variant->name : $product->name;
            $variantWeight = $variant->override_product_weight ? $variant->weight : $product->weight;
            $variantImages = $variant->has_own_media ? $variant->getMedia('variant_images')->map(fn($m) => $m->getFullUrl())->toArray() : $images;
            $variantsData[] = [
                'variant_id' => $variant->baselinker_variant_id ?: $variant->id,
                'name' => $variantName,
                'sku' => $variant->sku,
                'ean' => $variant->barcode,
                'prices' => $prices,
                'stock' => $stock,
                'location' => $variant->stockLevels->first()?->location,
                'weight' => (float) $variantWeight,
                'image' => $variantImages[0] ?? null,
            ];
        }
        $retailPrice = $defaultVariant->prices->where('type', 'retail')->first();
        $productData = [
            'name' => $product->name,
            'description' => $marketplaceAttr['long_description']['desc_1'] ?? $product->description ?? '',
            'description_short_1' => $marketplaceAttr['long_description']['desc_2'] ?? $product->short_description ?? '',
            'description_short_2' => $marketplaceAttr['long_description']['desc_3'] ?? '',
            'description_short_3' => $marketplaceAttr['long_description']['desc_4'] ?? '',
            'description_short_4' => $marketplaceAttr['long_description']['desc_5'] ?? '',
            'sku' => $defaultVariant->sku,
            'ean' => $defaultVariant->barcode,
            'is_bundle' => $product->type === 'bundle',
            'tax_rate' => (float) ($retailPrice?->taxRate?->rate ?? 0.0),
            'weight' => (float) $product->weight,
            'height' => (float) $product->height,
            'length' => (float) $product->length,
            'width' => (float) $product->width,
            'category_id' => $product->category->baselinker_category_id ?? null,
            'images' => $images,
            'features' => $features,
            'parameters' => $marketplaceAttr['parameters'] ?? [],
            'variants' => $variantsData,
        ];
        if ($baselinkerProductId) {
            $productData['product_id'] = $baselinkerProductId;
        }
        return ['inventory_id' => $this->inventoryId, 'product_data' => $productData];
    }

    // ----------------------------------------------------------------------------------
    // METODY DO MASOWEJ AKTUALIZACJI STANÓW I CEN
    // ----------------------------------------------------------------------------------

    public function updateInventoryProductsStock(array $productsData): ?array
    {
        try {
            $params = ['inventory_id' => $this->inventoryId, 'products' => $productsData];
            $response = $this->sendRequest('updateInventoryProductsStock', $params);
            $this->logSync('to_baselinker', 'stock', 'success', "Successfully updated stock for multiple products.", null, json_encode(array_keys($productsData)));
            return $response;
        } catch (\Exception $e) {
            $this->logSync('to_baselinker', 'stock', 'failed', "Failed to update stock: " . $e->getMessage(), null, json_encode(array_keys($productsData)));
            return null;
        }
    }

    public function updateInventoryProductsPrices(array $productsData): ?array
    {
        try {
            $params = ['inventory_id' => $this->inventoryId, 'price_group_id' => config('baselinker.price_group_id'), 'products' => $productsData];
            $response = $this->sendRequest('updateInventoryProductsPrices', $params);
            $this->logSync('to_baselinker', 'price', 'success', "Successfully updated prices for multiple products.", null, json_encode(array_keys($productsData)));
            return $response;
        } catch (\Exception $e) {
            $this->logSync('to_baselinker', 'price', 'failed', "Failed to update prices: " . $e->getMessage(), null, json_encode(array_keys($productsData)));
            return null;
        }
    }
}
