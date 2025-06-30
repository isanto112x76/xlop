<?php

use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\ManufacturerController;
use App\Http\Controllers\Api\V1\MediaController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ProductBundleItemController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProductVariantController;
use App\Http\Controllers\Api\V1\SelectOptionsController;
use App\Http\Controllers\Api\V1\SupplierController;
use App\Http\Controllers\Api\V1\TaxRateController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\WarehouseController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\V1\StockFifoController;
use App\Services\BaselinkerService;
use App\Http\Controllers\Api\V1\SynchronizationLogController;
use Illuminate\Support\Facades\Route;



/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// =================================================================
// Publiczne trasy uwierzytelniania
// =================================================================
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
});

Route::get('/test-baselinker', function (BaselinkerService $baselinker) {
    $storages = $baselinker->getInventories();
    if ($storages) {
        return response()->json($storages);
    }
    return response()->json(['error' => 'Błąd połączenia z API Baselinker. Sprawdź logi.'], 500);
});
// =================================================================
// Trasy chronione (wymagają uwierzytelnienia przez Sanctum)
// =================================================================
Route::middleware('auth:sanctum')->group(function () {



    // Trasy uwierzytelniania dla zalogowanego użytkownika
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']); // Zmieniono na POST
        Route::get('user', [AuthController::class, 'user']);
    });

    // Główne trasy API v1
    Route::prefix('v1')->group(function () {
        Route::apiResource('synchronization-logs', SynchronizationLogController::class)->only(['index', 'show']);
        // --- API Resources (standardowe operacje CRUD) ---
        Route::get('products/search', [ProductController::class, 'search'])->name('products.search');
        Route::get('categories/tree', [CategoryController::class, 'getTree']);
        Route::apiResource('products', ProductController::class);
        Route::apiResource('categories', CategoryController::class);
        Route::apiResource('manufacturers', ManufacturerController::class);
        Route::apiResource('suppliers', SupplierController::class);
        Route::apiResource('tax-rates', TaxRateController::class);
        Route::apiResource('documents', DocumentController::class);
        Route::apiResource('orders', OrderController::class);
        Route::apiResource('expenses', ExpenseController::class);
        Route::apiResource('users', UserController::class); // Poprawiono na liczbę mnogą 'users'
        Route::apiResource('warehouses', WarehouseController::class); // Poprawiono na liczbę mnogą 'warehouses'
        // ✅ NOWA TRASA do tworzenia powiązanego dokumentu finansowego
        Route::post('documents/{document}/link-financial', [DocumentController::class, 'linkFinancial'])->name('documents.linkFinancial');

        Route::post('documents/{document}/close', [DocumentController::class, 'close'])->name('documents.close');
        Route::apiResource('documents', DocumentController::class);


        // Zasoby zagnieżdżone
        Route::apiResource('products.variants', ProductVariantController::class)->shallow();
        Route::apiResource('products.bundle-items', ProductBundleItemController::class)->only(['store', 'update', 'destroy'])->shallow();

        // --- Trasy dla mediów ---
        Route::post('/media/upload', [MediaController::class, 'store'])->name('media.store');
        Route::delete('/media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');
        Route::post('/media/reorder', [MediaController::class, 'reorder'])->name('media.reorder');
        //Route::post('/stock/fifo/in', [StockFifoController::class, 'storeStockIn']);
        //Route::post('/stock/fifo/out', [StockFifoController::class, 'issueStockOut']);

        // --- Trasy dla Dashboardu ---
        Route::get('dashboard/document-stats', [DashboardController::class, 'documentStats']);
        Route::get('dashboard/order-stats', [DashboardController::class, 'orderStats']);

        // --- Trasy dla opcji w formularzach (selecty, autocomplete) ---
        Route::prefix('select-options')->controller(SelectOptionsController::class)->group(function () {
            Route::get('categories', 'categories');
            Route::get('manufacturers', 'manufacturers');
            Route::get('suppliers', 'suppliers');
            Route::get('warehouses', 'warehouses');
            Route::get('tax-rates', 'taxRates');
            Route::get('users', 'users');
            Route::get('document-types', 'documentTypes');
            Route::get('product-variants', 'productVariants');
            Route::get('tags', 'tags');
            Route::get('products', 'products');
            // Endpoint potrzebny dla strony listy dokumentów
            Route::get('document-mappings', 'getDocumentMappings');
        });
    });
});
