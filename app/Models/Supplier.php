<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'tax_id',
        'email',
        'phone',
        'address',
        'notes',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relacje
    |--------------------------------------------------------------------------
    */

    /**
     * Dostawca może dostarczać wiele produktów.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Dostawca może być powiązany z wieloma dokumentami (głównie PZ i FVZ).
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Z dostawcą mogą być powiązane koszty (faktury).
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
