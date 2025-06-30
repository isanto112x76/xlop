<?php

namespace App\Jobs\Baselinker;

use App\Models\Category;
use App\Services\BaselinkerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncCategoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $backoff = 120;

    protected Category $category;

    public function __construct(Category $category)
    {
        $this->category = $category;
    }

    public function handle(BaselinkerService $baselinkerService): void
    {
        // Wywołujemy jedną, uniwersalną metodę
        $baselinkerService->syncInventoryCategory($this->category);
    }
}
