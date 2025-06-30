<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ProductService;
use App\Http\Requests\StoreProductVariantRequest;   // Utworzymy ten request
use App\Http\Requests\UpdateProductVariantRequest; // Utworzymy ten request
use App\Http\Resources\ProductVariantResource;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;

class ProductVariantController extends Controller
{
    protected ProductService $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
        // Możesz dodać middleware autoryzacji, np. $this->authorizeResource(ProductVariant::class, 'variant');
    }

    /**
     * Wyświetla listę wariantów dla danego produktu.
     */
    public function index(Product $product): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        // Załaduj warianty z potrzebnymi relacjami (ceny, stany, media)
        $product->load([
            'variants' => function ($query) {
                $query->orderBy('position', 'asc')->with([
                    'media',
                    'prices.taxRate',
                    'stockLevels.warehouse'
                ]);
            }
        ]);
        return ProductVariantResource::collection($product->variants);
    }

    /**
     * Przechowuje nowo utworzony wariant dla produktu.
     */
    public function store(StoreProductVariantRequest $request, Product $product): ProductVariantResource
    {
        // ProductService będzie odpowiedzialny za logikę, w tym ustawienie 'is_default' jeśli to jedyny wariant
        $variant = $this->productService->createVariantForProduct($product, $request->validated());
        return new ProductVariantResource($variant->load(['media', 'prices.taxRate', 'stockLevels.warehouse']));
    }

    /**
     * Wyświetla określony wariant.
     */
    public function show(ProductVariant $variant): ProductVariantResource
    {
        $variant->loadMissing(['product', 'media', 'prices.taxRate', 'stockLevels.warehouse']);
        return new ProductVariantResource($variant);
    }

    /**
     * Aktualizuje określony wariant w bazie danych.
     */
    public function update(UpdateProductVariantRequest $request, ProductVariant $variant): ProductVariantResource
    {
        // Odfiltruj stock_levels z validated danych
        $fields = Arr::except($request->validated(), ['stock_levels']);

        // Przekaż tylko białą listę do serwisu
        $updatedVariant = $this->productService->updateVariant($variant, $fields);

        return new ProductVariantResource(
            $updatedVariant->load(['media', 'prices.taxRate', 'stockLevels.warehouse'])
        );
    }


    /**
     * Usuwa określony wariant z bazy danych.
     */
    public function destroy(ProductVariant $variant): Response
    {
        // ProductService powinien obsłużyć logikę, np. czy można usunąć domyślny wariant,
        // jeśli jest jedynym wariantem itp.
        $this->productService->deleteVariant($variant);
        return response()->noContent();
    }
}
