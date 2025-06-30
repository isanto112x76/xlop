<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

class OrderController extends Controller
{
    /**
     * Wyświetla listę zamówień z paginacją i filtrowaniem.
     */
    // app/Http/Controllers/Api/V1/OrderController.php
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Order::query()->with(['customer', 'shippingAddress', 'billingAddress', 'wzDocument', 'items.productVariant.product.media', 'items.productVariant.stockLevels', 'items.productVariant.product.defaultVariant.stockLevels']);

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request->input('sort_by'), $request->input('order_by', 'desc'));

        return OrderResource::collection($query->paginate($request->input('per_page', 10)));
    }



    /**
     * Uruchamia synchronizację zamówień z Baselinkerem w tle.
     */
    public function sync(): JsonResponse
    {
        try {
            // Uruchamiamy komendę synchronizacji w kolejce, aby nie blokować odpowiedzi HTTP
            Artisan::queue('baselinker:sync-orders');

            return response()->json([
                'message' => 'Synchronizacja zamówień z Baselinker została zainicjowana w tle. Odśwież listę za chwilę.',
            ]);
        } catch (Throwable $e) {
            Log::error("Błąd OrderController@sync: " . $e->getMessage());
            return response()->json(['message' => 'Błąd podczas uruchamiania synchronizacji zamówień: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Wyświetla szczegóły pojedynczego zamówienia.
     */
    public function show(Order $order): OrderResource
    {
        $order->load([
            'customer',
            'shippingAddress',
            'billingAddress',
            'items.productVariant.media',             // ✅ media dla wariantu
            'items.productVariant.product.media',     // ✅ media dla produktu głównego
            'wzDocument',
        ]);
        return new OrderResource($order);
    }

    /**
     * Metoda do ręcznej aktualizacji zamówienia (do implementacji w przyszłości).
     */
    public function update(Request $request, Order $order): OrderResource
    {
        // Tutaj w przyszłości można dodać logikę do ręcznej edycji zamówienia, np. zmiany statusu
        // $updatedOrder = $this->orderService->updateOrder($order, $request->validated());
        // return new OrderResource($updatedOrder);

        return new OrderResource($order);
    }

    /**
     * Metoda do usuwania zamówienia.
     */
    public function destroy(Order $order): JsonResponse
    {
        // Należy rozważyć, czy usuwanie zamówień jest dozwolone.
        // Jeśli tak, trzeba obsłużyć powiązane dokumenty, płatności etc.
        // $this->orderService->deleteOrder($order);

        $order->delete();
        return response()->json(null, 204);
    }
    private function applyFilters(Builder $query, Request $request): void
    {
        // Wyszukiwanie ogólne
        $query->when($request->filled('q'), function ($q) use ($request) {
            $search = $request->input('q');
            $q->where(fn($sub) => $sub->where('baselinker_order_id', 'like', "%{$search}%")->orWhereHas('customer', fn($cq) => $cq->where('name', 'like', "%{$search}%")));
        });

        // Filtry podstawowe
        $query->when($request->filled('status_ids'), fn($q) => $q->whereIn('baselinker_status_id', $request->input('status_ids')));
        $query->when($request->filled('product_id'), fn($q) => $q->whereHas('items', fn($iq) => $iq->where('product_variant_id', $request->input('product_id'))));
        $query->when($request->filled('location'), fn($q) => $q->whereHas('items.productVariant.stockLevels', fn($slq) => $slq->where('location', 'like', '%' . $request->input('location') . '%')));

        // Filtry zaawansowane
        $query->when($request->filled('date_from'), fn($q) => $q->whereDate('date_add', '>=', $request->input('date_from')));
        $query->when($request->filled('date_to'), fn($q) => $q->whereDate('date_add', '<=', $request->input('date_to')));
        $query->when($request->filled('total_from'), fn($q) => $q->where('total_gross', '>=', $request->input('total_from')));
        $query->when($request->filled('total_to'), fn($q) => $q->where('total_gross', '<=', $request->input('total_to')));
        $query->when($request->filled('is_cod'), fn($q) => $q->where('is_cod', $request->boolean('is_cod')));
        $query->when($request->filled('delivery_method'), fn($q) => $q->where('delivery_method', 'like', '%' . $request->input('delivery_method') . '%'));

        // Filtry po danych klienta
        $query->when($request->filled('customer_name'), fn($q) => $q->whereHas('customer', fn($cq) => $cq->where('name', 'like', '%' . $request->input('customer_name') . '%')));
        $query->when($request->filled('customer_email'), fn($q) => $q->whereHas('customer', fn($cq) => $cq->where('email', 'like', '%' . $request->input('customer_email') . '%')));
    }

    /**
     * ✅ NOWA METODA: Aplikuje sortowanie do zapytania.
     */
    private function applySorting(Builder $query, ?string $sortBy, string $direction): void
    {
        $direction = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';

        if ($sortBy === 'customer') {
            $query->leftJoin('customers', 'orders.customer_id', '=', 'customers.id')
                ->orderBy('customers.name', $direction)
                ->select('orders.*'); // Unikamy konfliktu kolumn ID
        } elseif (in_array($sortBy, ['baselinker_order_id', 'date_add', 'total_gross'])) {
            $query->orderBy($sortBy, $direction);
        } else {
            // Domyślne sortowanie
            $query->orderBy('date_add', 'desc');
        }
    }
}
