<?php

namespace App\Jobs\Baselinker;

use App\Services\OrderSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessOrderEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 20; // Czekaj 3 minuty przed ponowną próbą

    protected array $log;

    /**
     * Create a new job instance.
     * @param array $log Pojedynczy wpis z getJournalList
     */
    public function __construct(array $log)
    {
        $this->log = $log;
    }

    /**
     * Execute the job.
     */
    public function handle(OrderSyncService $orderSyncService): void
    {
        $orderSyncService->handleJournalEvent($this->log);
    }
}
