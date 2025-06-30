<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStockInRequest;
use App\Http\Requests\IssueStockOutRequest;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use App\Models\StockBatch;
use App\Models\StockLevel;
use Illuminate\Support\Facades\DB;

class StockFifoController extends Controller
{
    // Przyjęcie towaru
    public function storeStockIn(StoreStockInRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $variant = ProductVariant::findOrFail($request->product_variant_id);
            $warehouse = Warehouse::findOrFail($request->warehouse_id);

            // Obsługa wspólnego stanu magazynowego
            if ($variant->product->variants_share_stock) {
                $variant = $variant->product->defaultVariant;
            }

            // Dodajemy nową partię
            StockBatch::create([
                'product_variant_id' => $variant->id,
                'warehouse_id' => $warehouse->id,
                'quantity_total' => $request->quantity,
                'quantity_available' => $request->quantity,
                'purchase_price' => $request->purchase_price,
                'purchase_date' => $request->purchase_date,
                'source_document_type' => $request->source_document_type,
                'source_document_id' => $request->source_document_id,
            ]);

            // Aktualizujemy stock_levels (agregat)
            StockLevel::change($variant, $warehouse, $request->quantity);

            return response()->json(['message' => 'Przyjęto towar do magazynu FIFO'], 201);
        });
    }

    // Rozchód FIFO
    public function issueStockOut(IssueStockOutRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $variant = ProductVariant::findOrFail($request->product_variant_id);
            $warehouse = Warehouse::findOrFail($request->warehouse_id);
            $quantity = $request->quantity;

            if ($variant->product->variants_share_stock) {
                $variant = $variant->product->defaultVariant;
            }

            // Weryfikacja dostępności
            $stockLevel = StockLevel::firstOrCreate([
                'product_variant_id' => $variant->id,
                'warehouse_id' => $warehouse->id
            ], [
                'quantity' => 0,
                'reserved_quantity' => 0,
                'incoming_quantity' => 0
            ]);

            if ($quantity > ($stockLevel->quantity - $stockLevel->reserved_quantity)) {
                return response()->json(['message' => 'Brak wystarczającej ilości towaru'], 422);
            }

            // Pobieramy partie FIFO
            $batches = StockBatch::where('product_variant_id', $variant->id)
                ->where('warehouse_id', $warehouse->id)
                ->where('quantity_available', '>', 0)
                ->orderBy('purchase_date')
                ->orderBy('id')
                ->get();

            $remaining = $quantity;
            foreach ($batches as $batch) {
                if ($remaining <= 0)
                    break;
                $avail = $batch->quantity_available;
                if ($avail <= 0)
                    continue;
                $toTake = min($avail, $remaining);

                $batch->quantity_available -= $toTake;
                $batch->save();

                $remaining -= $toTake;
            }

            if ($remaining > 0) {
                return response()->json(['message' => 'Brak partii do zdjęcia towaru (FIFO)'], 422);
            }

            StockLevel::change($variant, $warehouse, -$quantity);

            return response()->json(['message' => 'Towar wydany zgodnie z FIFO'], 200);
        });
    }
}
