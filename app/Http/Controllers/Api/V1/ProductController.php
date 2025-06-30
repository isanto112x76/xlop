<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Services\ProductService;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use App\Http\Resources\ProductSearchResource;
use App\Models\ProductVariant;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    protected ProductService $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    // Pełna lista relacji do ładowania – tylko tam, gdzie potrzeba!
    private function getFullProductRelationsForShow(): array
    {
        return [
            'category',
            'manufacturer',
            'supplier',
            'media',
            'tags',
            'links',
            'variants.media',
            'variants.prices',
            'variants.stockLevels',
            'variants', // dla count() i innych operacji
            'defaultVariant.media',
            'defaultVariant.prices',
            'defaultVariant.stockLevels',

            'bundleItems',
            'bundleItems.componentVariant.product',
        ];
    }
    /**
     * Search for product variants to be used in document items.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function search(Request $request): AnonymousResourceCollection
    {
        $searchTerm = $request->input('query', '');

        if (empty($searchTerm)) {
            return ProductSearchResource::collection([]);
        }

        $variantIds = ProductVariant::query()
            ->where(function ($subQuery) use ($searchTerm) {
                $subQuery->where('sku', 'like', "%{$searchTerm}%")
                    ->orWhere('ean', 'like', "%{$searchTerm}%")
                    ->orWhere('name', 'like', "%{$searchTerm}%")
                    ->orWhereHas('product', function ($q) use ($searchTerm) {
                        $q->where('name', 'like', "%{$searchTerm}%")
                            // Dodajemy wyszukiwanie po SKU produktu głównego
                            ->orWhere('sku', 'like', "%{$searchTerm}%");
                    })
                    ->orWhereHas('stockLevels', function ($q) use ($searchTerm) {
                        $q->where('location', 'like', "%{$searchTerm}%");
                    });
            })
            ->limit(50)
            ->pluck('id');

        if ($variantIds->isEmpty()) {
            return ProductSearchResource::collection([]);
        }

        $variants = ProductVariant::whereIn('id', $variantIds)
            ->with(['product.media', 'stockLevels.warehouse', 'prices.taxRate'])
            ->take(15)->get();

        return ProductSearchResource::collection($variants);
    }
    public function index(Request $request)
    {
        $query = Product::query();

        $query->with([
            'category',
            'manufacturer',
            'supplier',
            'defaultVariant.prices',
            'defaultVariant.stockLevels',
            'media',
            'tags',
            'links',
        ]);

        // --- SEARCH ---
        if ($request->filled('search')) {
            $searchTerm = $request->input('search');
            $query->where(function (Builder $q) use ($searchTerm) {
                $q->where('products.name', 'like', "%{$searchTerm}%")
                    ->orWhere('products.sku', 'like', "%{$searchTerm}%")
                    ->orWhere('products.ean', 'like', "%{$searchTerm}%")
                    ->orWhereHas('variants', function (Builder $vq) use ($searchTerm) {
                        $vq->where('product_variants.name', 'like', "%{$searchTerm}%")
                            ->orWhere('product_variants.sku', 'like', "%{$searchTerm}%")
                            ->orWhere('product_variants.ean', 'like', "%{$searchTerm}%");
                    });
            });
        }

        // --- FILTERS ---
        foreach (['name', 'sku', 'ean', 'id', 'product_status', 'product_type', 'manufacturer_id', 'category_id', 'supplier_id'] as $field) {
            if ($request->filled($field)) {
                $column = ($field === 'id') ? 'products.id' : 'products.' . $field;
                $operator = in_array($field, ['name', 'sku', 'ean']) ? 'like' : '=';
                $value = in_array($field, ['name', 'sku', 'ean']) ? "%{$request->input($field)}%" : $request->input($field);
                $query->where($column, $operator, $value);
            }
        }
        if ($request->filled('variant_name')) {
            $query->whereHas('variants', fn(Builder $vq) => $vq->where('product_variants.name', 'like', "%{$request->input('variant_name')}%"));
        }
        if ($request->filled('variant_sku')) {
            $query->whereHas('variants', fn(Builder $vq) => $vq->where('product_variants.sku', 'like', "%{$request->input('variant_sku')}%"));
        }
        if ($request->filled('variant_ean')) {
            $query->whereHas('variants', fn(Builder $vq) => $vq->where('product_variants.ean', 'like', "%{$request->input('variant_ean')}%"));
        }

        // --- PRICE FILTERS ---
        $priceTypeForFilter = $request->input('price_type_filter', 'selling_default');
        if ($request->filled('price_gross_from') || $request->filled('price_gross_to') || $request->filled('price_net_from') || $request->filled('price_net_to')) {
            $query->whereHas('variants.prices', function (Builder $pq) use ($request, $priceTypeForFilter) {
                $pq->where('product_prices.type', $priceTypeForFilter)
                    ->where(fn($q) => $q->whereNull('product_prices.valid_from')->orWhere('product_prices.valid_from', '<=', now()))
                    ->where(fn($q) => $q->whereNull('product_prices.valid_to')->orWhere('product_prices.valid_to', '>=', now()));

                if ($request->filled('price_gross_from')) {
                    $pq->where('product_prices.price_gross', '>=', $request->input('price_gross_from'));
                }
                if ($request->filled('price_gross_to')) {
                    $pq->where('product_prices.price_gross', '<=', $request->input('price_gross_to'));
                }
                if ($request->filled('price_net_from')) {
                    $pq->where('product_prices.price_net', '>=', $request->input('price_net_from'));
                }
                if ($request->filled('price_net_to')) {
                    $pq->where('product_prices.price_net', '<=', $request->input('price_net_to'));
                }
            });
        }

        // --- STOCK FILTERS ---
        if ($request->boolean('stock_pending')) {
            $query->whereHas('variants.stockLevels', fn(Builder $slq) => $slq->where('stock_levels.incoming_quantity', '>', 0));
        }
        if ($request->filled('stock_available_from')) {
            $query->whereHas('variants.stockLevels', function (Builder $slq) use ($request) {
                $slq->havingRaw('SUM(quantity - reserved_quantity) >= ?', [$request->input('stock_available_from')])
                    ->groupBy('stock_levels.product_variant_id');
            });
        }
        if ($request->filled('stock_available_to')) {
            $query->whereHas('variants.stockLevels', function (Builder $slq) use ($request) {
                $slq->havingRaw('SUM(quantity - reserved_quantity) <= ?', [$request->input('stock_available_to')])
                    ->groupBy('stock_levels.product_variant_id');
            });
        }
        if ($request->filled('stock_total_from')) {
            $query->whereHas('variants.stockLevels', function (Builder $slq) use ($request) {
                $slq->havingRaw('SUM(quantity) >= ?', [$request->input('stock_total_from')])
                    ->groupBy('stock_levels.product_variant_id');
            });
        }
        if ($request->filled('stock_total_to')) {
            $query->whereHas('variants.stockLevels', function (Builder $slq) use ($request) {
                $slq->havingRaw('SUM(quantity) <= ?', [$request->input('stock_total_to')])
                    ->groupBy('stock_levels.product_variant_id');
            });
        }

        // --- OTHER FILTERS ---
        if ($request->boolean('allegro_ads')) {
            $query->where(function (Builder $q) {
                $q->whereJsonContains('products.marketplace_attributes->allegro->ads_active', true)
                    ->orWhereHas(
                        'variants',
                        fn(Builder $vq) =>
                        $vq->where('product_variants.override_product_marketplace_attributes', true)
                            ->whereJsonContains('product_variants.marketplace_attributes_override->allegro->ads_active', true)
                    );
            });
        }
        if ($request->filled('tag_ids')) {
            $tagIds = is_array($request->input('tag_ids')) ? $request->input('tag_ids') : [$request->input('tag_ids')];
            $query->whereHas('tags', fn(Builder $tq) => $tq->whereIn('tags.id', $tagIds));
        }
        if ($request->filled('warehouse_id')) {
            $query->whereHas('variants.stockLevels', fn(Builder $slq) => $slq->where('stock_levels.warehouse_id', $request->input('warehouse_id')));
        }
        if ($request->filled('manage_stock_filter')) {
            $query->where('products.manage_stock', (bool) $request->input('manage_stock_filter'));
        }
        if ($request->filled('variants_share_stock_filter') && (bool) $request->input('manage_stock_filter', true)) {
            $query->where('products.variants_share_stock', (bool) $request->input('variants_share_stock_filter'));
        }

        // ======================== SORTING ========================
        $query->select('products.*');

        if ($request->filled('sortBy')) {
            $sortBy = $request->input('sortBy');
            $sortDesc = $request->boolean('sortDesc', false);
            $direction = $sortDesc ? 'desc' : 'asc';

            $allowedSorts = ['id', 'name', 'sku', 'ean', 'created_at', 'updated_at', 'product_status', 'product_type', 'weight'];
            $relationSorts = ['manufacturer', 'category', 'supplier'];
            $priceSorts = ['retail_price_gross', 'purchase_price_gross', 'retail_price_net', 'purchase_price_net'];

            if (in_array($sortBy, $allowedSorts)) {
                $query->orderBy('products.' . $sortBy, $direction);
            } elseif (in_array($sortBy, $relationSorts)) {
                $relationTable = Str::plural($sortBy);
                $query->leftJoin($relationTable, 'products.' . $sortBy . '_id', '=', $relationTable . '.id')
                    ->orderBy($relationTable . '.name', $direction);
            } elseif (str_starts_with($sortBy, 'manufacturer.') || str_starts_with($sortBy, 'category.') || str_starts_with($sortBy, 'supplier.')) {
                [$relationName, $columnName] = explode('.', $sortBy);
                $tableName = Str::plural($relationName);
                if ($columnName === 'name') {
                    $query->leftJoin($tableName, 'products.' . $relationName . '_id', '=', $tableName . '.id')
                        ->orderBy($tableName . '.' . $columnName, $direction);
                } elseif ($relationName === 'category' && $columnName === 'slug') {
                    $query->leftJoin($tableName, 'products.' . $relationName . '_id', '=', $tableName . '.id')
                        ->orderBy($tableName . '.' . $columnName, $direction);
                }
            } elseif (in_array($sortBy, $priceSorts)) {
                $priceFieldToSort = Str::contains($sortBy, '_gross') ? 'price_gross' : 'price_net';
                $priceTypeForSort = Str::startsWith($sortBy, 'retail_') ? $request->input('price_type_filter', 'selling_default') : 'purchase_default';

                $subQuery = ProductPrice::select($priceFieldToSort)
                    ->join('product_variants', 'product_prices.variant_id', '=', 'product_variants.id')
                    ->whereColumn('product_variants.product_id', 'products.id')
                    ->where('product_variants.is_default', true)
                    ->where('product_prices.type', $priceTypeForSort)
                    ->where(fn($q) => $q->whereNull('product_prices.valid_from')->orWhere('product_prices.valid_from', '<=', now()))
                    ->where(fn($q) => $q->whereNull('product_prices.valid_to')->orWhere('product_prices.valid_to', '>=', now()))
                    ->orderBy($priceFieldToSort, $direction)
                    ->limit(1);

                $query->orderByRaw("({$subQuery->toSql()}) {$direction}", $subQuery->getBindings());
            }

        } else {
            $query->orderBy('products.created_at', 'desc');
        }

        $perPage = $request->input('per_page', 15);
        $products = $query->paginate($perPage);

        return ProductResource::collection($products);
    }

    public function store(StoreProductRequest $request)
    {
        $product = $this->productService->createProduct($request->validated());
        $product->loadMissing($this->getFullProductRelationsForShow());
        return new ProductResource($product);
    }

    public function show(Product $product)
    {
        $product->loadMissing($this->getFullProductRelationsForShow());
        return new ProductResource($product);
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $updatedProduct = $this->productService->updateProduct($product, $request->validated());
        $updatedProduct->loadMissing($this->getFullProductRelationsForShow());
        return new ProductResource($updatedProduct);
    }

    public function destroy(Product $product)
    {
        $this->productService->deleteProduct($product);
        return response()->noContent();
    }
}
