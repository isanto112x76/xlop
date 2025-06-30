<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryItem extends Model
{
    use HasFactory;

    // Ta tabela nie potrzebuje znaczników czasu created_at/updated_at
    public $timestamps = false;

    protected $fillable = [
        'document_id',
        'product_variant_id',
        'expected_quantity',
        'counted_quantity',
    ];

    // Kolumna 'difference' jest generowana w bazie danych, więc nie ma potrzeby jej definiować tutaj

    protected function casts(): array
    {
        return [
            'expected_quantity' => 'decimal:2',
            'counted_quantity' => 'decimal:2',
            'difference' => 'decimal:2',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relacje
    |--------------------------------------------------------------------------
    */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
