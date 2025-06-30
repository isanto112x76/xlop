<?php

namespace App\Observers;

use App\Jobs\Baselinker\DeleteCategoryJob;
use App\Jobs\Baselinker\SyncCategoryJob;
use App\Models\Category;

class CategoryObserver
{
    public function saved(Category $category): void
    {
        SyncCategoryJob::dispatch($category)->delay(now()->addSeconds(5));
    }

    public function deleted(Category $category): void
    {
        if ($category->baselinker_category_id) {
            DeleteCategoryJob::dispatch($category->baselinker_category_id);
        }
    }
}
