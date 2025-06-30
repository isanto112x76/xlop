<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductBundleItemResource;
use App\Models\Product;
use App\Models\ProductBundleItem;
use Illuminate\Http\Request;

class ProductBundleItemController extends Controller
{
    // Dodaj element do zestawu
    public function store(Request $request, Product $product)
    {
        if ($product->product_type !== 'bundle') {
            return response()->json(['message' => 'Produkt nie jest zestawem'], 422);
        }
        $validated = $request->validate([
            'component_variant_id' => 'required|exists:product_variants,id',
            'quantity' => 'required|integer|min:1',
        ]);
        // Unikalność kombinacji zapewnia migracja (unikalny indeks)
        $item = $product->bundleItems()->create($validated);
        $item->load('componentVariant.product');
        return new ProductBundleItemResource($item);
    }

    // Aktualizuj ilość elementu
    public function update(Request $request, ProductBundleItem $bundleItem)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);
        $bundleItem->update($validated);
        $bundleItem->load('componentVariant.product');
        return new ProductBundleItemResource($bundleItem);
    }

    // Usuń element z zestawu
    public function destroy(ProductBundleItem $bundleItem)
    {
        $bundleItem->delete();
        return response()->noContent();
    }
}
