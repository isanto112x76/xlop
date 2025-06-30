<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Models\Address;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\StockLevel;

use App\Models\SynchronizationLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class OrderSyncService
{
    protected DocumentService $documentService;
    protected BaselinkerService $baselinkerService;

    /**
     * Wstrzykujemy DocumentService, aby móc tworzyć dokumenty WZ.
     */
    public function __construct(DocumentService $documentService, BaselinkerService $baselinkerService)
    {
        $this->documentService = $documentService;
        $this->baselinkerService = $baselinkerService;
    }

    /**
     * Główna metoda synchronizująca pojedyncze zamówienie z Baselinkera.
     */
    /**
     * Główny dyspozytor zdarzeń z Journala.
     */
    public function handleJournalEvent(array $log): void
    {
        $orderId = $log['order_id'];
        $order = Order::firstWhere('baselinker_order_id', $orderId);

        // Jeśli zamówienie nie istnieje dla zdarzenia innego niż tworzenie, pobierz je
        if (!$order && $log['log_type'] != 1) {
            $orderData = $this->baselinkerService->getOrders([$orderId])[0] ?? null;
            if ($orderData) {
                $order = $this->syncOrderFromBaselinker($orderData);
            } else {
                Log::error("Could not find order #{$orderId} in Baselinker for event type {$log['log_type']}.");
                return;
            }
        }

        switch ($log['log_type']) {
            case 1: // Order creation
                $this->handleOrderCreation($orderId);
                break;
            case 2: // DOF download (order confirmation)
                if ($order)
                    $this->handleOrderConfirmation($order);
                break;
            case 12: // Adding a product to an order
            case 13: // Editing the product in the order
            case 14: // Removing the product from the order
            case 16: // Editing order data
                if ($order)
                    $this->handleOrderUpdate($order);
                break;
            case 9:  // Package creation
            case 11: // Editing delivery data
                if ($order)
                    $this->handleShippingUpdate($order);
                break;
            case 18: // Order status change
                if ($order)
                    $this->handleStatusChange($order, $log['object_id']);
                break;
            // Pozostałe zdarzenia na razie tylko logujemy
            default:
                $this->logSync('from_baselinker', 'unhandled_event', 'success', "Event type {$log['log_type']} received for order #{$orderId}.", $order?->id, $orderId);
                break;
        }
    }

    // --- HANDLERY ZDARZEŃ ---

    private function handleOrderCreation(int $baselinkerOrderId): void
    {
        $orderData = $this->baselinkerService->getOrders([$baselinkerOrderId])[0] ?? null;
        if ($orderData) {
            $this->syncOrderFromBaselinker($orderData);
        }
    }

    private function handleOrderConfirmation(Order $order): void
    {
        if ($order->related_wz_id) {
            Log::info("WZ document already exists for order #{$order->id}. Skipping creation.");
            return;
        }

        $items = $order->items->map(function ($item) {
            $priceNet = $item->variant->prices->where('type', 'purchase')->first()?->price_net ?? ($item->price_gross / 1.23);
            return [
                'product_variant_id' => $item->product_variant_id,
                'quantity' => $item->quantity,
                'unit_price' => $priceNet,
                'tax_rate_id' => $item->variant->prices->first()?->tax_rate_id ?? 1,
            ];
        })->toArray();

        $wzData = [
            'related_order_id' => $order->id,
            'warehouse_id' => 1,
            'document_date' => now()->format('Y-m-d'),
            'products' => $items,
            'customer_id' => $order->customer_id,
        ];

        $wzDocument = $this->documentService->createWz($wzData);
        $order->update(['related_wz_id' => $wzDocument->id]);
        $this->logSync('from_baselinker', 'document', 'success', "WZ document #{$wzDocument->number} created for confirmed order #{$order->baselinker_order_id}.", $order->id, $order->baselinker_order_id);
    }

    private function handleOrderUpdate(Order $order): void
    {
        $orderData = $this->baselinkerService->getOrders([$order->baselinker_order_id])[0] ?? null;
        if ($orderData) {
            $this->syncOrderFromBaselinker($orderData);

            // ✅ POPRAWKA: Pobieramy model WZ z relacji przed przekazaniem
            $wzDocument = $order->wzDocument;
            if ($wzDocument && !$wzDocument->closed_at) {
                // Przygotowujemy dane produktów w formacie oczekiwanym przez updateDocument
                $productsForUpdate = collect($orderData['products'])->map(function ($item) {
                    $variant = ProductVariant::where('baselinker_variant_id', $item['variant_id'])->first();
                    return [
                        'product_variant_id' => $variant->id,
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['price_brutto'] / 1.23, // Uproszczone, do dostosowania
                        'tax_rate_id' => $variant->prices->first()?->tax_rate_id ?? 1,
                    ];
                })->toArray();

                $this->documentService->updateDocument($wzDocument, ['products' => $productsForUpdate]);
            }
        }
    }

    private function handleShippingUpdate(Order $order): void
    {
        $orderData = $this->baselinkerService->getOrders([$order->baselinker_order_id])[0] ?? null;
        if ($orderData) {
            $order->update([
                'delivery_tracking_number' => $orderData['delivery_package'][0]['package_number'] ?? $order->delivery_tracking_number,
            ]);
            $this->syncShippingAddress($orderData, $order->customer);
        }
    }

    private function handleStatusChange(Order $order, int $newStatusId): void
    {
        $order->update(['baselinker_status_id' => $newStatusId]);
        $this->logSync('from_baselinker', 'status_change', 'success', "Order #{$order->baselinker_order_id} status changed to {$newStatusId}.", $order->id, $order->baselinker_order_id);

        // ✅ POPRAWKA: Pobieramy model WZ z relacji przed przekazaniem
        $wzDocument = $order->wzDocument;
        if ($newStatusId == config('baselinker.statuses.shipped') && $wzDocument && !$wzDocument->closed_at) {
            $this->documentService->closeDocument($wzDocument);
            $this->logSync('from_baselinker', 'document', 'success', "WZ document #{$wzDocument->number} closed due to order shipment.", $order->id, $order->baselinker_order_id);
        }
    }
    public function syncOrderFromBaselinker(array $orderData): ?Order
    {
        return DB::transaction(function () use ($orderData) {

            $customer = $this->syncCustomer($orderData);
            $billingAddress = $this->syncBillingAddress($orderData, $customer);
            $shippingAddress = $this->syncShippingAddress($orderData, $customer);

            $order = Order::updateOrCreate(
                ['baselinker_order_id' => $orderData['order_id']],
                [
                    'external_order_id' => $orderData['external_order_id'],
                    'external_informations' => json_encode([
                        'extra_field_1' => $orderData['extra_field_1'],
                        'extra_field_2' => $orderData['extra_field_2'],
                    ]),
                    'order_source' => $orderData['order_source'],
                    'date_add' => date('Y-m-d H:i:s', $orderData['date_add']),
                    'date_confirmed' => $orderData['date_confirmed'] ? date('Y-m-d H:i:s', $orderData['date_confirmed']) : null,
                    'date_in_status' => $orderData['date_in_status'] ? date('Y-m-d H:i:s', $orderData['date_in_status']) : null,
                    'customer_id' => $customer->id,
                    'customer_login' => $orderData['user_login'],
                    'billing_address_id' => $billingAddress->id,
                    'shipping_address_id' => $shippingAddress->id,
                    'baselinker_status_id' => $orderData['order_status_id'],
                    'total_gross' => $orderData['total_price_gross'],
                    'payment_method' => $orderData['payment_method'],
                    'is_cod' => $orderData['payment_method_cod'],
                    'delivery_price' => $orderData['delivery_price'],
                    'delivery_method' => $orderData['delivery_method'],
                    'delivery_tracking_number' => $orderData['delivery_package'][0]['package_number'] ?? null,
                    'want_invoice' => $orderData['want_invoice'],
                    'customer_comments' => $orderData['user_comments'],
                ]
            );

            $this->syncOrderItems($orderData['products'], $order);

            $this->logSync('from_baselinker', 'order', 'success', "Order #{$order->baselinker_order_id} synchronized successfully.", $order->id, $order->baselinker_order_id);

            return $order;
        });
    }

    /**
     * Tworzy i zatwierdza dokument WZ dla potwierdzonego zamówienia.
     */
    public function createWzForConfirmedOrder(Order $order): void
    {
        // Sprawdź, czy WZ już nie istnieje, aby uniknąć duplikatów
        if ($order->related_wz_id) {
            Log::info("WZ document already exists for order #{$order->id}. Skipping creation.");
            return;
        }

        $items = $order->items->map(function ($item) {
            return [
                'product_variant_id' => $item->product_variant_id,
                'quantity' => $item->quantity,
                'price_net' => $item->price_gross / (1 + ($item->variant->product->taxRate->rate / 100)), // Przykładowe obliczenie netto
                'price_gross' => $item->price_gross,
                'tax_rate_id' => $item->variant->product->taxRate->id,
            ];
        })->toArray();

        $wzData = [
            'type' => DocumentType::WZ,
            'customer_id' => $order->customer_id,
            'related_order_id' => $order->id,
            'source_warehouse_id' => 1, // Zakładamy główny magazyn o ID 1
            'user_id' => Auth::id() ?? 1, // ID użytkownika systemowego/admina
            'items' => $items,
        ];

        // Utwórz dokument WZ.
        // Zakładamy, że DocumentService->createDocument() teraz obsługuje tworzenie rezerwacji.
        $wzDocument = $this->documentService->createDocument($wzData);

        // Zatwierdź dokument, co zaktualizuje stany magazynowe
        $this->documentService->issueDocument($wzDocument);

        // Połącz WZ z zamówieniem
        $order->update(['related_wz_id' => $wzDocument->id]);

        $this->logSync('from_baselinker', 'document', 'success', "WZ document #{$wzDocument->number} created for order #{$order->baselinker_order_id}.", $order->id, $order->baselinker_order_id);
    }

    private function syncCustomer(array $orderData): Customer
    {
        return Customer::updateOrCreate(
            ['email' => $orderData['email']],
            [
                'name' => $orderData['delivery_fullname'],
                'phone' => $orderData['phone'],
                'company_name' => $orderData['delivery_company'],
                'tax_id' => $orderData['invoice_nip'],
                'baselinker_user_login' => $orderData['user_login'],
            ]
        );
    }

    private function syncBillingAddress(array $orderData, Customer $customer): Address
    {
        if (empty($orderData['invoice_fullname'])) {
            return $this->syncShippingAddress($orderData, $customer);
        }

        return Address::updateOrCreate(
            ['customer_id' => $customer->id, 'type' => 'billing'],
            [
                'full_name' => $orderData['invoice_fullname'],
                'company_name' => $orderData['invoice_company'],
                'address' => $orderData['invoice_address'],
                'postcode' => $orderData['invoice_postcode'],
                'city' => $orderData['invoice_city'],
                'country_code' => $orderData['invoice_country_code'],
                'phone' => $orderData['phone'],
                'email' => $orderData['email'],
            ]
        );
    }

    private function syncShippingAddress(array $orderData, Customer $customer): Address
    {
        return Address::updateOrCreate(
            ['customer_id' => $customer->id, 'type' => 'delivery'],
            [
                'full_name' => $orderData['delivery_fullname'],
                'company_name' => $orderData['delivery_company'],
                'address' => $orderData['delivery_address'],
                'postcode' => $orderData['delivery_postcode'],
                'city' => $orderData['delivery_city'],
                'country_code' => $orderData['delivery_country_code'],
                'phone' => $orderData['phone'],
                'email' => $orderData['email'],
            ]
        );
    }

    private function syncOrderItems(array $products, Order $order): void
    {
        $currentProductIds = [];
        foreach ($products as $productData) {
            $currentProductIds[] = $productData['order_product_id'];
            $variant = ProductVariant::where('baselinker_variant_id', $productData['variant_id'])->first();

            if (!$variant) {
                $variant = ProductVariant::whereHas('product', function ($query) use ($productData) {
                    $query->where('baselinker_id', $productData['product_id']);
                })->where('is_default', true)->first();
            }

            if ($variant) {
                OrderItem::updateOrCreate(
                    ['order_id' => $order->id, 'order_product_id' => $productData['order_product_id']],
                    [
                        'product_variant_id' => $variant->id,
                        'product_baselinker_id' => $productData['product_id'],
                        'original_product_id' => $productData['original_product_id'],
                        'quantity' => $productData['quantity'],
                        'price_gross' => $productData['price_brutto'],
                        'name' => $productData['name'],
                        'sku' => $productData['sku'],
                    ]
                );

                // Logika rezerwacji i zmiany stanów została przeniesiona do DocumentService, aby zachować spójność.

            } else {
                Log::warning("Product with Baselinker ID {$productData['product_id']} (SKU: {$productData['sku']}) not found. Skipping item for order #{$order->baselinker_order_id}.");
                $this->logSync('from_baselinker', 'order_item', 'failed', "Product not found in local DB (SKU: {$productData['sku']})", $order->id, $order->baselinker_order_id);
            }
        }

        $order->items()->whereNotIn('order_product_id', $currentProductIds)->delete();
    }

    private function logSync(string $direction, string $resourceType, string $status, ?string $message = null, ?int $localId = null, $externalId = null): void
    {
        SynchronizationLog::create([
            'direction' => $direction,
            'resource_type' => $resourceType,
            'status' => $status,
            'message' => $message,
            'local_id' => $localId,
            'external_id' => $externalId,
        ]);
    }
}
