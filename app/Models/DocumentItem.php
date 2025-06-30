<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    // POPRAWKA: Upewniamy się, że tax_rate_id jest fillable, a tax_rate nie.
    protected $fillable = [
        'document_id',
        'product_id',
        'product_variant_id',
        'quantity',
        'purchase_price_net',
        'price_net',
        'price_gross',
        'tax_rate_id', // Dodano
    ];

    // Upewniamy się, że nie ma już odwołania do 'tax_rate'
    // Jeśli miałeś tutaj $casts dla tax_rate, należy je usunąć.

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }
}
