<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\ProductVariant; // Dodaj import ProductVariant
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        // Obliczanie total_stock_value z uwzględnieniem poprawnej nazwy relacji
        // Używamy 'productVariant.prices' zamiast 'variant.prices'
        $stockLevels = StockLevel::with('productVariant.prices')->get();
        $totalStockValue = 0;

        foreach ($stockLevels as $stockLevel) {
            // Używamy productVariant zamiast variant
            if ($stockLevel->productVariant) {
                // Użyj akcesora z modelu ProductVariant do pobrania aktualnej ceny zakupu netto
                $purchasePriceNet = $stockLevel->productVariant->current_purchase_price_net;
                if ($purchasePriceNet !== null) {
                    $totalStockValue += $stockLevel->quantity * $purchasePriceNet;
                }
            }
        }

        $stats = [
            'products_count' => Product::count(),
            'active_products_count' => Product::whereHas('variants.stockLevels', function ($query) {
                $query->where('quantity', '>', 0);
            })->count(), // Ta logika może wymagać dostosowania w zależności od definicji "aktywnego produktu"
            'orders_pending_count' => Order::whereNull('related_wz_id')
                ->whereNotIn('status', ['Anulowane', 'Zrealizowane']) // Uproszczony warunek
                ->count(),
            'documents_today_count' => Document::whereDate('document_date', today())->count(),

            'total_stock_value' => (float) $totalStockValue,

            'low_stock_products_count' => Product::whereHas('variants.stockLevels', function ($query) {
                $query->select(DB::raw('product_variant_id, SUM(quantity - reserved_quantity) as total_available_stock'))
                    ->groupBy('product_variant_id')
                    ->having('total_available_stock', '<', 10); // Załóżmy, że 10 to próg
            })->count(),
            'synced_products_count' => Product::whereNotNull('baselinker_id')->count(),
            // 'best_selling_product' => null, // Wymaga implementacji
        ];

        return response()->json($stats);
    }

    public function orderStats(): JsonResponse
    {
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        $ordersInMonth = Order::whereBetween('date_add', [$startOfMonth, $endOfMonth])->count();
        $valueInMonth = Order::whereBetween('date_add', [$startOfMonth, $endOfMonth])->sum('total_gross');

        // Statusy oznaczające paczki do obsłużenia
        $statusesForPacking = [37540, 37563]; // Do wysłania, Spakowane
        $totalToHandle = Order::whereIn('baselinker_status_id', $statusesForPacking)->count();
        $packed = Order::where('baselinker_status_id', 37563)->count(); // Spakowane

        return response()->json([
            'ordersInMonth' => $ordersInMonth,
            'valueInMonth' => (float) $valueInMonth,
            'readyToSend' => $totalToHandle, // Zmieniamy na "Do obsłużenia"
            'packed' => $packed,
        ]);
    }
    public function documentStats(): JsonResponse
    {
        // Ta metoda jest teraz poprawnie nazwana i będzie działać z trasą:
        // Route::get('dashboard/document-stats', [DashboardController::class, 'documentStats']);

        $monthlyIncome = Document::where('type', 'PZ')->whereMonth('created_at', now()->month)->sum('total_net');
        $monthlyOutcome = Document::where('type', 'WZ')->whereMonth('created_at', now()->month)->sum('total_net');

        // Przykładowe dane dla widgetów - dostosuj logikę do swoich potrzeb
        $data = [
            [
                'title' => 'Wartość przyjęcia (miesiąc)',
                'value' => $monthlyIncome,
                'icon' => 'tabler-arrow-bar-down',
                'color' => 'success',
                'percent' => 100, // Możesz dodać logikę procentową
                'unit' => 'currency',
            ],
            [
                'title' => 'Wartość rozchodu (miesiąc)',
                'value' => $monthlyOutcome,
                'icon' => 'tabler-arrow-bar-up',
                'color' => 'error',
                'percent' => 100,
                'unit' => 'currency',
            ],
            [
                'title' => 'Liczba dokumentów',
                'value' => Document::count(),
                'icon' => 'tabler-file',
                'color' => 'primary',
                'percent' => 100,
                'unit' => 'integer',
            ],
            [
                'title' => 'Liczba produktów',
                'value' => Product::count(),
                'icon' => 'tabler-packages',
                'color' => 'info',
                'percent' => 100,
                'unit' => 'integer',
            ]
        ];

        return response()->json($data);
    }

    public function index(Request $request): JsonResponse
    {
        $summary = [
            'categories_count' => Category::count(),
            'products_count' => Product::count(),
            'orders_count' => Order::count(),
            'suppliers_count' => Supplier::count(),
            'users_count' => User::count(),
            'warehouses_count' => Warehouse::count(),
        ];

        return response()->json($summary);
    }
}

