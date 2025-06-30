<?php

namespace App\Jobs\Baselinker;

use App\Services\BaselinkerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DeleteProductJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    protected int $baselinkerProductId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $baselinkerProductId)
    {
        $this->baselinkerProductId = $baselinkerProductId;
    }

    /**
     * Execute the job.
     */
    public function handle(BaselinkerService $baselinkerService): void
    {
        Log::info("Dispatching task to DELETE product from Baselinker, ID: {$this->baselinkerProductId}");
        $baselinkerService->deleteInventoryProduct($this->baselinkerProductId);
    }
}
