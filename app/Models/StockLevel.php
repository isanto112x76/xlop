<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Throwable;

class StockLevel extends Model
{
    use HasFactory;

    protected $table = 'stock_levels';

    protected $fillable = [
        'product_variant_id',
        'warehouse_id',
        'quantity',
        'reserved_quantity', // Dodane w migracji 2025_05_30_215750
        'incoming_quantity', // Dodane w migracji 2025_05_30_215750
        'location',
        'last_stocktake_date',
        'notes',
    ];

    protected $casts = [
        'last_stocktake_date' => 'date',
        'quantity' => 'integer',
        'reserved_quantity' => 'integer',
        'incoming_quantity' => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relacje
    |--------------------------------------------------------------------------
    */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Akcesory
    |--------------------------------------------------------------------------
    */
    /**
     * Zwraca dostępny (wolny) stan magazynowy = quantity - reserved_quantity
     */
    // Akcesory (jeśli są potrzebne bezpośrednio w tym modelu)
    public function getAvailableQuantityAttribute(): int
    {
        return $this->quantity - $this->reserved_quantity;
    }

    public function getExpectedQuantityAttribute(): int
    {
        return $this->quantity + $this->incoming_quantity;
    }

    /*
    |--------------------------------------------------------------------------
    | Logika Biznesowa (zarządzanie stanami)
    |--------------------------------------------------------------------------
    */

    /**
     * Uniwersalna metoda do modyfikacji fizycznego stanu magazynowego.
     */
    public static function change(
        ProductVariant $variant,
        Warehouse $warehouse,
        float $quantity,
        string $location = null
    ): StockLevel {
        return DB::transaction(function () use ($variant, $warehouse, $quantity, $location) {
            $stockLevel = self::firstOrCreate(
                [
                    'product_variant_id' => $variant->id,
                    'warehouse_id' => $warehouse->id,
                ],
                [
                    'quantity' => 0,
                    'reserved_quantity' => 0,
                    'incoming_quantity' => 0,
                    'location' => $location ?? $variant->sku . '-' . $warehouse->symbol,
                ]
            );

            // Aktualizacja lokalizacji jeśli podano inną niż obecna
            if ($location !== null && $stockLevel->location !== $location) {
                $stockLevel->location = $location;
            }

            $newQuantity = (float) $stockLevel->quantity + $quantity;

            if ($newQuantity < 0) {
                throw new \Exception("Stan magazynowy dla SKU {$variant->sku} w magazynie {$warehouse->name} nie może być ujemny. Próbowano odjąć: {$quantity}, obecny stan: {$stockLevel->quantity}.");
            }

            $stockLevel->quantity = $newQuantity;
            $stockLevel->save();

            return $stockLevel;
        });
    }

    /**
     * Modyfikuje rezerwację dla danego stanu magazynowego.
     */
    public static function changeReservation(
        ProductVariant $variant,
        Warehouse $warehouse,
        float $quantity
    ): StockLevel {
        return DB::transaction(function () use ($variant, $warehouse, $quantity) {
            $stockLevel = self::firstOrCreate(
                [
                    'product_variant_id' => $variant->id,
                    'warehouse_id' => $warehouse->id,
                ],
                [
                    'quantity' => 0,
                    'reserved_quantity' => 0,
                    'incoming_quantity' => 0,
                    'location' => $variant->sku . '-' . $warehouse->symbol,
                ]
            );

            $newReservedQuantity = (float) $stockLevel->reserved_quantity + $quantity;

            // Blokada zbyt dużej rezerwacji
            if ($quantity > 0 && $quantity > ((float) $stockLevel->quantity - (float) $stockLevel->reserved_quantity)) {
                throw new \Exception("Niewystarczający stan dostępny (ilość: {$stockLevel->quantity}, zarezerwowane: {$stockLevel->reserved_quantity}) dla SKU {$variant->sku} do utworzenia rezerwacji {$quantity} szt.");
            }

            if ($newReservedQuantity < 0) {
                throw new \Exception("Rezerwacja dla SKU {$variant->sku} nie może być ujemna. Próbowano odjąć: " . abs($quantity) . ", obecna rezerwacja: {$stockLevel->reserved_quantity}.");
            }

            $stockLevel->reserved_quantity = $newReservedQuantity;
            $stockLevel->save();

            return $stockLevel;
        });
    }

    /**
     * Zmienia oczekiwany stan (incoming_quantity).
     * Używaj tej metody przy zamawianiu dostawy/odnotowaniu oczekiwań.
     */
    public static function changeIncoming(
        ProductVariant $variant,
        Warehouse $warehouse,
        float $quantity
    ): StockLevel {
        return DB::transaction(function () use ($variant, $warehouse, $quantity) {
            $stockLevel = self::firstOrCreate(
                [
                    'product_variant_id' => $variant->id,
                    'warehouse_id' => $warehouse->id,
                ],
                [
                    'quantity' => 0,
                    'reserved_quantity' => 0,
                    'incoming_quantity' => 0,
                    'location' => $variant->sku . '-' . $warehouse->symbol,
                ]
            );

            $newIncomingQuantity = (float) $stockLevel->incoming_quantity + $quantity;

            if ($newIncomingQuantity < 0) {
                throw new \Exception("Stan oczekiwany (incoming) dla SKU {$variant->sku} nie może być ujemny. Próbowano odjąć: " . abs($quantity) . ", obecny oczekiwany: {$stockLevel->incoming_quantity}.");
            }

            $stockLevel->incoming_quantity = $newIncomingQuantity;
            $stockLevel->save();

            return $stockLevel;
        });
    }
}
