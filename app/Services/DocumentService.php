<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\DocumentItem;
use App\Models\InventoryItem;
use App\Models\ProductVariant;
use App\Models\StockBatch;
use App\Models\StockLevel;
use App\Models\TaxRate;
use App\Models\Warehouse;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DocumentService
{
    // --- GŁÓWNE METODY PUBLICZNE ---

    public function createPz(array $data): Document
    {
        return $this->createWarehouseDocument(DocumentType::PZ, $data);
    }
    public function createWz(array $data): Document
    {
        return $this->createWarehouseDocument(DocumentType::WZ, $data);
    }
    public function createMm(array $data): Document
    {
        return $this->createWarehouseDocument(DocumentType::MM, $data);
    }
    public function createRw(array $data): Document
    {
        return $this->createWarehouseDocument(DocumentType::RW, $data);
    }
    public function createPw(array $data): Document
    {
        return $this->createWarehouseDocument(DocumentType::PW, $data);
    }
    public function createZw(array $data): Document
    {
        return $this->createWarehouseDocument(DocumentType::ZW, $data);
    }
    public function createZrw(array $data): Document
    {
        return $this->createWarehouseDocument(DocumentType::ZRW, $data);
    }
    public function createFs(array $data): Document
    {
        return $this->createFinancialDocument(DocumentType::FS, $data);
    }
    public function createFvz(array $data): Document
    {
        return $this->createFinancialDocument(DocumentType::FVZ, $data);
    }

    public function closeDocument(Document $document): Document
    {
        return DB::transaction(function () use ($document) {
            if ($document->closed_at) {
                throw new Exception("Dokument jest już zamknięty.");
            }
            foreach ($document->items as $item) {
                $this->updateProvisionalStock($document, $item, true);
                $this->finalizeStockMovement($document, $item);
            }
            $document->update(['closed_at' => now()]);
            return $document;
        });
    }

    public function linkFinancialDocument(Document $parentDocument, array $invoiceData): Document
    {
        return DB::transaction(function () use ($parentDocument, $invoiceData) {
            $financialType = match ($parentDocument->type) {
                DocumentType::WZ => DocumentType::FS,
                DocumentType::PZ => DocumentType::FVZ,
                default => throw new InvalidArgumentException('Można tworzyć dokumenty finansowe tylko do WZ lub PZ.')
            };
            $productsData = $parentDocument->items->map(fn($item) => ['product_variant_id' => $item->product_variant_id, 'quantity' => $item->quantity, 'price_net' => $item->price_net, 'tax_rate_id' => $item->tax_rate_id])->toArray();

            $baseData = array_merge($invoiceData, ['related_document_id' => $parentDocument->id, 'products' => $productsData, 'customer_id' => $parentDocument->customer_id, 'supplier_id' => $parentDocument->supplier_id, 'related_order_id' => $parentDocument->related_order_id]);

            return $this->createFinancialDocument($financialType, $baseData);
        });
    }

    public function processInventory(array $data): Document
    {
        $this->validateDataForType(DocumentType::INW, $data);
        return DB::transaction(function () use ($data) {
            $warehouse = Warehouse::findOrFail($data['warehouse_id']);
            $document = $this->createBaseDocument(DocumentType::INW, $data, 0, 0, false);

            foreach ($data['products'] as $itemData) {
                $variant = ProductVariant::findOrFail($itemData['product_variant_id']);
                $countedQuantity = (float) $itemData['counted_quantity'];
                $stockLevel = StockLevel::firstOrCreate(['product_variant_id' => $variant->id, 'warehouse_id' => $warehouse->id]);
                $expectedQuantity = (float) $stockLevel->quantity;
                $document->inventoryItems()->create(['product_variant_id' => $variant->id, 'expected_quantity' => $expectedQuantity, 'counted_quantity' => $countedQuantity]);
                $difference = $countedQuantity - $expectedQuantity;
                if ($difference !== 0.0) {
                    $this->finalizeStockMovementForInventory($document, $variant->id, $warehouse->id, $difference, $itemData['purchase_price'] ?? 0);
                }
            }
            $document->update(['closed_at' => now()]);
            return $document->load('inventoryItems.productVariant');
        });
    }

    public function deleteDocument(Document $document)
    {
        DB::transaction(function () use ($document) {
            if (!$document->closed_at) {
                foreach ($document->items as $item) {
                    $this->updateProvisionalStock($document, $item, true);
                }
            }
            $document->clearMediaCollection('attachments');
            $document->delete();
        });
    }

    // --- METODY WEWNĘTRZNE ---

    private function createWarehouseDocument(DocumentType $type, array $data): Document
    {
        $this->validateDataForType($type, $data);
        return DB::transaction(function () use ($type, $data) {
            $taxRates = TaxRate::all()->keyBy('id');
            [$totalNet, $totalGross] = $this->calculateTotals($data['products'], $taxRates);
            $document = $this->createBaseDocument($type, $data, $totalNet, $totalGross);

            foreach ($data['products'] as $itemData) {
                $taxRate = $taxRates->get($itemData['tax_rate_id']);
                $item = $this->createDocumentItem($document, $itemData, $taxRate);
                $this->updateProvisionalStock($document, $item);
            }

            if (isset($data['new_attachments']) && is_array($data['new_attachments'])) {
                $this->handleAttachments($document, $data['new_attachments']);
            }

            return $document;
        });
    }

    private function createFinancialDocument(DocumentType $type, array $data): Document
    {
        $this->validateDataForType($type, $data);
        return DB::transaction(function () use ($type, $data) {
            $taxRates = TaxRate::all()->keyBy('id');
            [$totalNet, $totalGross] = $this->calculateTotals($data['products'], $taxRates);
            $document = $this->createBaseDocument($type, $data, $totalNet, $totalGross);

            foreach ($data['products'] as $itemData) {
                $taxRate = $taxRates->get($itemData['tax_rate_id']);
                $this->createDocumentItem($document, $itemData, $taxRate);
            }

            if (isset($data['new_attachments']) && is_array($data['new_attachments'])) {
                $this->handleAttachments($document, $data['new_attachments']);
            }

            return $document;
        });
    }

    protected function handleAttachments(Document $document, array $files): void
    {
        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $document->addMedia($file)->toMediaCollection('attachments');
            }
        }
    }

    private function createBaseDocument(DocumentType $type, array $data, float $totalNet = 0, float $totalGross = 0, bool $withItems = false): Document
    {
        $notes = trim(($data['notes_internal'] ?? '') . "\n" . ($data['notes_print'] ?? ''));

        $documentData = [
            'number' => (isset($data['number']) && $data['number'] !== 'AUTONUMER') ? $data['number'] : $this->generateDocumentNumber($type),
            'type' => $type,
            'issue_date' => $data['document_date'],
            'document_date' => $data['document_date'],
            'user_id' => auth()->id(),
            'related_document_id' => $data['related_document_id'] ?? null,
            'related_order_id' => $data['related_order_id'] ?? null,
            'total_net' => $totalNet,
            'total_gross' => $totalGross,
            'notes' => $notes ?: null,
            'closed_at' => null,
        ];

        if (isset($data['contractor_id'])) {
            if (in_array($type, [DocumentType::PZ, DocumentType::FVZ, DocumentType::ZRW])) {
                $documentData['supplier_id'] = $data['contractor_id'];
            } elseif (in_array($type, [DocumentType::WZ, DocumentType::FS, DocumentType::ZW])) {
                $documentData['customer_id'] = $data['contractor_id'];
            }
        }

        if (isset($data['warehouse_id'])) {
            if (in_array($type, [DocumentType::PZ, DocumentType::PW, DocumentType::ZW, DocumentType::MM])) {
                $documentData['target_warehouse_id'] = $data['warehouse_id'];
            }
            if (in_array($type, [DocumentType::WZ, DocumentType::RW, DocumentType::ZRW, DocumentType::MM])) {
                $documentData['source_warehouse_id'] = $data['warehouse_id'];
            }
        }

        return Document::create($documentData);
    }

    private function createDocumentItem(Document $document, array $itemData, ?TaxRate $taxRate): DocumentItem
    {
        $priceNet = $itemData['unit_price'] ?? 0;
        $rateValue = $taxRate ? $taxRate->rate : 0;
        $priceGross = $priceNet * (1 + ($rateValue / 100));

        return $document->items()->create([
            'product_variant_id' => $itemData['product_variant_id'],
            'quantity' => $itemData['quantity'] ?? 0,
            'price_net' => $priceNet,
            'tax_rate_id' => $itemData['tax_rate_id'],
            'price_gross' => $priceGross,
        ]);
    }

    private function updateProvisionalStock(Document $document, DocumentItem $item, bool $reverse = false): void
    {
        $quantity = $reverse ? -$item->quantity : $item->quantity;

        if (in_array($document->type, [DocumentType::PZ, DocumentType::PW, DocumentType::FVZ, DocumentType::ZW, DocumentType::MM]) && $document->target_warehouse_id) {
            $stockLevel = StockLevel::firstOrCreate(['product_variant_id' => $item->product_variant_id, 'warehouse_id' => $document->target_warehouse_id], ['quantity' => 0, 'reserved_quantity' => 0, 'incoming_quantity' => 0]);
            $stockLevel->increment('incoming_quantity', $quantity);
        }
        if (in_array($document->type, [DocumentType::WZ, DocumentType::RW, DocumentType::FS, DocumentType::ZRW, DocumentType::MM]) && $document->source_warehouse_id) {
            $stockLevel = StockLevel::firstOrCreate(['product_variant_id' => $item->product_variant_id, 'warehouse_id' => $document->source_warehouse_id], ['quantity' => 0, 'reserved_quantity' => 0, 'incoming_quantity' => 0]);
            if (!$reverse && ($stockLevel->quantity - $stockLevel->reserved_quantity) < $item->quantity) {
                throw new Exception("Niewystarczająca ilość dostępnego towaru do rezerwacji.");
            }
            $stockLevel->increment('reserved_quantity', $quantity);
        }
    }

    private function finalizeStockMovement(Document $document, DocumentItem $item): void
    {
        if (in_array($document->type, [DocumentType::PZ, DocumentType::PW, DocumentType::FVZ, DocumentType::ZW, DocumentType::MM]) && $document->target_warehouse_id) {
            StockLevel::where(['product_variant_id' => $item->product_variant_id, 'warehouse_id' => $document->target_warehouse_id])->increment('quantity', $item->quantity);
            StockBatch::create(['product_variant_id' => $item->product_variant_id, 'warehouse_id' => $document->target_warehouse_id, 'source_document_id' => $document->id, 'source_document_type' => $document->type->value, 'quantity_available' => $item->quantity, 'quantity_total' => $item->quantity, 'purchase_price' => $item->price_net, 'purchase_date' => $document->document_date,]);
        }
        if (in_array($document->type, [DocumentType::WZ, DocumentType::RW, DocumentType::FS, DocumentType::ZRW, DocumentType::MM]) && $document->source_warehouse_id) {
            StockLevel::where(['product_variant_id' => $item->product_variant_id, 'warehouse_id' => $document->source_warehouse_id])->decrement('quantity', $item->quantity);
            $this->issueStockByFifo($item->product_variant_id, $document->source_warehouse_id, $item->quantity);
        }
    }

    private function finalizeStockMovementForInventory(Document $inventoryDocument, int $productVariantId, int $warehouseId, float $difference, float $purchasePrice): void
    {
        if ($difference > 0) {
            StockLevel::where(['product_variant_id' => $productVariantId, 'warehouse_id' => $warehouseId])->increment('quantity', $difference);
            StockBatch::create(['product_variant_id' => $productVariantId, 'warehouse_id' => $warehouseId, 'source_document_id' => $inventoryDocument->id, 'source_document_type' => $inventoryDocument->type->value, 'quantity_available' => $difference, 'quantity_total' => $difference, 'purchase_price' => $purchasePrice, 'purchase_date' => $inventoryDocument->document_date,]);
        } elseif ($difference < 0) {
            $quantityToIssue = abs($difference);
            StockLevel::where(['product_variant_id' => $productVariantId, 'warehouse_id' => $warehouseId])->decrement('quantity', $quantityToIssue);
            $this->issueStockByFifo($productVariantId, $warehouseId, $quantityToIssue);
        }
    }

    private function issueStockByFifo(int $productVariantId, int $warehouseId, float $quantityToIssue)
    {
        $totalStockInBatches = StockBatch::where(['product_variant_id' => $productVariantId, 'warehouse_id' => $warehouseId])->sum('quantity_available');
        if ($totalStockInBatches < $quantityToIssue)
            throw new Exception("Niewystarczający stan w partiach (StockBatch) dla produktu ID: $productVariantId.");
        $batches = StockBatch::where(['product_variant_id' => $productVariantId, 'warehouse_id' => $warehouseId])->where('quantity_available', '>', 0)->orderBy('purchase_date', 'asc')->orderBy('id', 'asc')->get();
        foreach ($batches as $batch) {
            if ($quantityToIssue <= 0)
                break;
            $quantityFromThisBatch = min($batch->quantity_available, $quantityToIssue);
            $batch->quantity_available -= $quantityFromThisBatch;
            $batch->save();
            $quantityToIssue -= $quantityFromThisBatch;
        }
    }

    private function calculateTotals(array $products, $taxRates): array
    {
        $totalNet = 0;
        $totalGross = 0;
        foreach ($products as $item) {
            $priceNet = $item['unit_price'] ?? ($item['price_net'] ?? 0);
            if (!isset($item['quantity'], $item['tax_rate_id']))
                continue;

            $taxRate = $taxRates->get($item['tax_rate_id']);
            $rateValue = $taxRate ? $taxRate->rate : 0;

            $itemNet = $item['quantity'] * $priceNet;
            $totalNet += $itemNet;
            $totalGross += $itemNet * (1 + ($rateValue / 100));
        }
        return [round($totalNet, 2), round($totalGross, 2)];
    }

    private function generateDocumentNumber(DocumentType $type): string
    {
        $year = date('Y');
        $month = date('m');
        $prefix = $type->value;

        $latest = Document::where('type', $type)
            ->whereYear('document_date', $year)
            ->whereMonth('document_date', $month)
            ->latest('id')
            ->first();

        $nextNumber = 1;
        if ($latest) {
            $parts = explode('-', $latest->number);
            $lastNumber = end($parts);
            if (is_numeric($lastNumber)) {
                $nextNumber = intval($lastNumber) + 1;
            }
        }

        return sprintf('%s-%s-%s-%05d', $prefix, $year, $month, $nextNumber);
    }

    private function validateDataForType(DocumentType $type, array $data): void
    {
        $rules = match ($type) {
            DocumentType::PZ => ['contractor_id', 'warehouse_id', 'products'],
            DocumentType::WZ => ['related_order_id', 'warehouse_id', 'products'],
            DocumentType::MM => ['source_warehouse_id', 'target_warehouse_id', 'products'],
            DocumentType::PW => ['warehouse_id', 'products'],
            DocumentType::RW => ['warehouse_id', 'products'],
            DocumentType::ZW => ['related_order_id', 'warehouse_id', 'products'],
            DocumentType::ZRW => ['contractor_id', 'warehouse_id', 'products'],
            DocumentType::FS => ['related_order_id', 'products'],
            DocumentType::FVZ => ['contractor_id', 'products'],
            DocumentType::INW => ['warehouse_id'],
        };
        $this->validateData($data, $rules, $type !== DocumentType::INW);
    }

    private function validateData(array $data, array $requiredKeys = [], bool $productsRequired = true): void
    {
        $defaultKeys = ['document_date'];
        if ($productsRequired)
            $defaultKeys[] = 'products';

        $allRequiredKeys = array_merge($defaultKeys, $requiredKeys);
        foreach ($allRequiredKeys as $key) {
            if (!isset($data[$key]))
                throw new InvalidArgumentException("Brak wymaganego klucza w danych: '{$key}'");
        }
    }

    public function createDocument(array $data): Document
    {
        $type = DocumentType::from($data['type']);

        $document = match ($type) {
            DocumentType::PZ, DocumentType::WZ, DocumentType::MM, DocumentType::RW, DocumentType::PW, DocumentType::ZW, DocumentType::ZRW => $this->createWarehouseDocument($type, $data),
            DocumentType::FS, DocumentType::FVZ => $this->createFinancialDocument($type, $data),
            DocumentType::INW => $this->processInventory($data),
            default => throw new InvalidArgumentException("Nieobsługiwany typ dokumentu: {$type->value}"),
        };

        return $document;
    }

    public function updateDocument(Document $document, array $data): Document
    {
        if ($document->closed_at) {
            throw new Exception("Nie można edytować zamkniętego dokumentu.");
        }

        return DB::transaction(function () use ($document, $data) {
            // ✅ POPRAWKA: Przekazujemy do aktualizacji tylko te klucze, które istnieją w modelu Document.
            // Używamy $document->getFillable() aby dynamicznie określić, które pola zapisać.
            $fillableData = Arr::only($data, (new Document)->getFillable());
            $document->update($fillableData);

            if (isset($data['products'])) {
                $this->synchronizeDocumentItems($document, $data['products']);
            }

            if (!empty($data['new_attachments'])) {
                $this->handleAttachments($document, $data['new_attachments']);
            }
            if (!empty($data['deleted_media_ids'])) {
                $document->media()->whereIn('id', $data['deleted_media_ids'])->each(fn($media) => $media->delete());
            }

            return $document->fresh(['items.productVariant', 'items.taxRate', 'supplier', 'customer', 'media']);
        });
    }

    protected function synchronizeDocumentItems(Document $document, array $productsData): void
    {
        $existingItemIds = $document->items()->pluck('id')->toArray();
        $inputItemIds = array_filter(Arr::pluck($productsData, 'id'));

        $itemsToDelete = array_diff($existingItemIds, $inputItemIds);
        if (!empty($itemsToDelete)) {
            $document->items()->whereIn('id', $itemsToDelete)->delete();
        }

        $taxRates = TaxRate::all()->keyBy('id');

        foreach ($productsData as $itemData) {
            $priceNet = $itemData['unit_price'] ?? 0;
            $taxRateId = $itemData['tax_rate_id'] ?? null;
            $taxRate = $taxRates->get($taxRateId);
            $rateValue = $taxRate ? $taxRate->rate : 0;
            $priceGross = $itemData['price_gross'] ?? ($priceNet * (1 + ($rateValue / 100)));

            // ✅ OSTATECZNA POPRAWKA: Używamy `tax_rate_id` w `updateOrCreate`
            $document->items()->updateOrCreate(
                ['id' => $itemData['id'] ?? null],
                [
                    'product_variant_id' => $itemData['product_variant_id'],
                    'quantity' => $itemData['quantity'],
                    'price_net' => $priceNet,
                    'price_gross' => $priceGross,
                    'tax_rate_id' => $taxRateId, // Zapisujemy ID stawki VAT
                ]
            );
        }

        $this->recalculateDocumentTotals($document);
    }

    protected function recalculateDocumentTotals(Document $document): void
    {
        $totals = $document->items()->with('taxRate')->get()->reduce(function ($carry, $item) {
            $carry['net'] += $item->price_net * $item->quantity;
            $rateValue = $item->taxRate ? $item->taxRate->rate : 0;
            $carry['gross'] += ($item->price_net * (1 + $rateValue / 100)) * $item->quantity;
            return $carry;
        }, ['net' => 0, 'gross' => 0]);

        $document->update([
            'total_net' => round($totals['net'], 2),
            'total_gross' => round($totals['gross'], 2),
        ]);
    }
}
