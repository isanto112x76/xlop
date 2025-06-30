<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxRate extends Model
{
    use HasFactory;

    protected $table = 'tax_rates';

    // Ta tabela nie potrzebuje znaczników czasu created_at/updated_at
    public $timestamps = false;

    protected $fillable = [
        'name',
        'rate',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'is_default' => 'boolean',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relacje
    |--------------------------------------------------------------------------
    */

    /**
     * Jedna stawka VAT może być przypisana do wielu produktów.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Zwraca domyślną stawkę VAT.
     * Użycie: TaxRate::default()->first();
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}
