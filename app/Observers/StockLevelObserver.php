<?php

namespace App\Observers;

use App\Jobs\Baselinker\UpdateProductStockJob;
use App\Models\StockLevel;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Log;

class StockLevelObserver
{
    /**
     * Handle the StockLevel "updated" event.
     *
     * @param \App\Models\StockLevel $stockLevel
     * @return void
     */
    public function updated(StockLevel $stockLevel): void
    {
        // Sprawdź, czy zmieniła się kolumna 'quantity'
        if ($stockLevel->isDirty('quantity')) {
            $this->dispatchStockUpdateJob($stockLevel);
        }
    }

    /**
     * Handle the StockLevel "created" event.
     *
     * @param \App\Models\StockLevel $stockLevel
     * @return void
     */
    public function created(StockLevel $stockLevel): void
    {
        $this->dispatchStockUpdateJob($stockLevel);
    }

    /**
     * Przygotowuje i wysyła zadanie aktualizacji stanu do kolejki.
     *
     * @param StockLevel $stockLevel
     * @return void
     */
    private function dispatchStockUpdateJob(StockLevel $stockLevel): void
    {
        // Pobierz mapowanie magazynów z konfiguracji
        $storageMappings = config('baselinker.storages');
        $warehouseId = $stockLevel->warehouse_id;

        // Sprawdź, czy dla danego magazynu istnieje mapowanie w Baselinker
        if (!isset($storageMappings[$warehouseId])) {
            Log::info("Brak mapowania magazynu Baselinker dla warehouse_id: {$warehouseId}. Pomijam synchronizację stanu.");
            return;
        }

        $storageId = $storageMappings[$warehouseId];
        $variant = $stockLevel->productVariant;

        // Sprawdź, czy produkt i wariant istnieją
        if (!$variant || !$variant->product) {
            Log::warning("Nie znaleziono wariantu lub produktu dla StockLevel ID: {$stockLevel->id}. Pomijam synchronizację.");
            return;
        }

        // Dodatkowa walidacja: synchronizuj tylko jeśli produkt jest aktywny i zarządza stanem
        if (!$variant->product->manage_stock || $variant->product->status !== 'active') {
            Log::info("Produkt ID: {$variant->product->id} nie zarządza stanem magazynowym lub jest nieaktywny. Pomijam synchronizację.");
            return;
        }

        Log::info("Wykryto zmianę stanu dla wariantu #{$variant->id}. Dodawanie zadania do kolejki.");

        UpdateProductStockJob::dispatch($variant, $storageId, $stockLevel->quantity);
    }
}
