<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_variant_id',
        'warehouse_id',
        'quantity_total',
        'quantity_available',
        'purchase_price',
        'purchase_date',
        'source_document_type',
        'source_document_id',
    ];

    protected $casts = [
        'quantity_total' => 'float',
        'quantity_available' => 'float',
        'purchase_price' => 'float',
        'purchase_date' => 'date',
    ];

    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function sourceDocument()
    {
        return $this->morphTo();
    }
}
