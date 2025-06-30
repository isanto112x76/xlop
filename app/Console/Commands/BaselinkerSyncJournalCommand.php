<?php

namespace App\Console\Commands;

use App\Jobs\Baselinker\ProcessOrderEventJob;
use App\Models\BaselinkerJournalCheckpoint;
use App\Services\BaselinkerService;
use Illuminate\Console\Command;


class BaselinkerSyncJournalCommand extends Command
{
    protected $signature = 'baselinker:sync-journal';
    protected $description = 'Fetches the latest events from Baselinker journal and dispatches jobs to process them.';

    public function handle(BaselinkerService $baselinkerService)
    {
        $this->info('Starting Baselinker journal synchronization...');

        $lastLogId = BaselinkerJournalCheckpoint::latest('processed_at')->first()->last_log_id ?? 0;
        $this->info("Fetching events starting from log ID: {$lastLogId}");

        $journal = $baselinkerService->getJournalList($lastLogId);

        if (!$journal || empty($journal['logs'])) {
            $this->info('No new events found.');
            return 0;
        }

        $logs = $journal['logs'];
        $newestLogId = $lastLogId;

        foreach ($logs as $log) {
            $this->line("Dispatching job for event type: {$log['log_type']}, order ID: {$log['order_id']}");
            ProcessOrderEventJob::dispatch($log);
            if ($log['log_id'] > $newestLogId) {
                $newestLogId = $log['log_id'];
            }
        }

        // Zapisz ID ostatniego przetworzonego logu
        BaselinkerJournalCheckpoint::create(['last_log_id' => $newestLogId]);

        $this->info("Successfully dispatched " . count($logs) . " jobs. Last processed log ID: {$newestLogId}");
        return 0;
    }
}
