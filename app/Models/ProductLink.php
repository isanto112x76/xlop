<?php
// app/Models/ProductLink.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductLink extends Model
{
    protected $fillable = [
        'product_id',
        'url',
        'label',
        // 'type', // Usunięte, bo nie ma takiej kolumny w obecnej tabeli
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
