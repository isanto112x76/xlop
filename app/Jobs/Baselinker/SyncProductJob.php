<?php

namespace App\Jobs\Baselinker;

use App\Models\Product;
use App\Services\BaselinkerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncProductJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $backoff = 120;

    protected Product $product;

    public function __construct(Product $product)
    {
        $this->product = $product;
    }

    public function handle(BaselinkerService $baselinkerService): void
    {
        // Wywołujemy jedną, uniwersalną metodę
        $baselinkerService->syncInventoryProduct($this->product);
    }
}
