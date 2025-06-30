<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use Illuminate\Support\Facades\File;

class MigrateProductImages extends Command
{
    protected $signature = 'migrate:product-images';
    protected $description = 'Migruje stare obrazy produktów do Media Library na podstawie SKU';

    public function handle()
    {
        $directory = public_path('old-images');

        if (!File::isDirectory($directory)) {
            $this->error("Folder nie istnieje: $directory");
            return;
        }

        $products = Product::all();

        foreach ($products as $product) {
            $sku = $product->sku;
            $matchingFiles = File::glob("{$directory}/{$sku}_*.jpg");

            if (empty($matchingFiles)) {
                $this->warn("Brak zdjęć dla SKU: {$sku}");
                continue;
            }

            foreach ($matchingFiles as $filePath) {
                $product
                    ->addMedia($filePath)
                    ->preservingOriginal()
                    ->toMediaCollection('images');

                $this->info("Dodano {$filePath} do produktu ID {$product->id}");
            }
        }

        $this->info("✔ Migracja zakończona.");
    }
}
