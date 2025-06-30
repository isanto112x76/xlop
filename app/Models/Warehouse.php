<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'symbol',
        'address',
        'is_default',
        'baselinker_storage_id',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relacje
    |--------------------------------------------------------------------------
    */
    public function stockBatches()
    {
        return $this->hasMany(StockBatch::class, 'warehouse_id');
    }
    /**
     * Magazyn posiada wiele stanów magazynowych (po jednym dla każdego wariantu).
     */
    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    /**
     * Dokumenty, w których ten magazyn jest magazynem źródłowym (np. WZ, MM).
     */
    public function sourceDocuments(): HasMany
    {
        return $this->hasMany(Document::class, 'source_warehouse_id');
    }

    /**
     * Dokumenty, w których ten magazyn jest magazynem docelowym (np. PZ, MM).
     */
    public function targetDocuments(): HasMany
    {
        return $this->hasMany(Document::class, 'target_warehouse_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Zwraca domyślny magazyn.
     * Użycie: Warehouse::default()->first();
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}
