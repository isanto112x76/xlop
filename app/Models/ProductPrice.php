<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPrice extends Model
{
    use HasFactory;

    protected $table = 'product_prices'; // Jawne określenie nazwy tabeli

    protected $fillable = [
        'variant_id',
        'type', // np. 'retail', 'wholesale', 'purchase', 'promo'
        'price_net',
        'price_gross',
        'currency',
        'valid_from',
        'valid_to',
        'tax_rate_id',
        'baselinker_price_group_id', // DODANE NOWE POLE
    ];

    protected $casts = [
        'price_net' => 'decimal:2',   // Dopasuj do definicji w migracji, np. decimal:4 jeśli potrzeba więcej miejsc po przecinku
        'price_gross' => 'decimal:2', // Dopasuj do definicji w migracji
        'valid_from' => 'date',
        'valid_to' => 'date',
        'tax_rate_id' => 'integer', // DODANE
        'baselinker_price_group_id' => 'integer', // DODANE CASTOWANIE
    ];

    /**
     * Pobierz wariant produktu, do którego należy ta cena.
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
    public function taxRate(): BelongsTo
    {
        // Laravel automatycznie użyje 'tax_rate_id' jako klucza obcego,
        // jeśli nazwa metody to 'taxRate', a powiązany model to 'TaxRate'.
        return $this->belongsTo(TaxRate::class);
    }
}
