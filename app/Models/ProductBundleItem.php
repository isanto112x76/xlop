<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductBundleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'bundle_product_id',   // ID produktu typu bundle
        'component_variant_id',// ID wariantu wchodzącego w skład
        'quantity',            // ilość sztuk danego wariantu w zestawie
    ];

    public function bundleProduct()
    {
        return $this->belongsTo(Product::class, 'bundle_product_id');
    }

    public function componentVariant()
    {
        return $this->belongsTo(ProductVariant::class, 'component_variant_id');
    }
}
