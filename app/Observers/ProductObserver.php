<?php

namespace App\Observers;

use App\Jobs\Baselinker\DeleteProductJob;
use App\Jobs\Baselinker\SyncProductJob;
use App\Models\Product;

class ProductObserver
{
    public function saved(Product $product): void
    {
        // Zleć synchronizację (dodanie/aktualizację) po każdym zapisaniu produktu.
        // Dajemy małe opóźnienie, aby upewnić się, że wszystkie powiązane dane (warianty, ceny) zostały zapisane.
        SyncProductJob::dispatch($product)->delay(now()->addSeconds(5));
    }

    public function deleted(Product $product): void
    {
        // Jeśli produkt miał ID w Baselinkerze, zleć jego usunięcie.
        if ($product->baselinker_id) {
            DeleteProductJob::dispatch($product->baselinker_id);
        }
    }
}
