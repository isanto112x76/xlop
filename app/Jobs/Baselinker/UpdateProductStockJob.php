<?php

namespace App\Jobs\Baselinker;

use App\Models\ProductVariant;
use App\Services\BaselinkerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateProductStockJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Liczba prób wykonania zadania.
     *
     * @var int
     */
    public int $tries = 5;

    /**
     * Liczba sekund oczekiwania przed ponowną próbą wykonania zadania.
     *
     * @var int
     */
    public int $backoff = 60;

    protected ProductVariant $variant;
    protected string $storageId;
    protected int $quantity;

    /**
     * Create a new job instance.
     *
     * @param ProductVariant $variant Wariant produktu, którego stan jest aktualizowany.
     * @param string $storageId ID magazynu w Baselinker (np. 'bl_1').
     * @param int $quantity Nowa ilość produktu w magazynie.
     */
    public function __construct(ProductVariant $variant, string $storageId, int $quantity)
    {
        $this->variant = $variant;
        $this->storageId = $storageId;
        $this->quantity = $quantity;
    }

    /**
     * Execute the job.
     *
     * @param BaselinkerService $baselinkerService
     * @return void
     */
    public function handle(BaselinkerService $baselinkerService): void
    {
        Log::info("Rozpoczynanie zadania aktualizacji stanu dla wariantu #{$this->variant->id} w Baselinker.");

        $baselinkerService->updateProductsStock(
            $this->storageId,
            $this->variant->id,
            $this->quantity
        );

        Log::info("Zakończono zadanie aktualizacji stanu dla wariantu #{$this->variant->id}.");
    }

    /**
     * Obsługa niepowodzenia zadania.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Nie udało się zaktualizować stanu dla wariantu #{$this->variant->id} w Baselinker.", [
            'exception_message' => $exception->getMessage(),
            'variant_id' => $this->variant->id,
            'storage_id' => $this->storageId,
        ]);
    }
}
