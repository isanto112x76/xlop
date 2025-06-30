<?php

namespace App\Models;

use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class Document extends Model implements HasMedia
{
    use HasFactory;
    use SoftDeletes;
    use InteractsWithMedia;

    /**
     * Pola, które można masowo przypisywać.
     */
    protected $fillable = [
        'number',
        'foreign_number',
        'type',
        'name',
        'document_date',
        'issue_date',
        'delivery_date',
        'payment_date',
        'user_id',
        'responsible_id',
        'supplier_id',
        'customer_id',
        'source_warehouse_id',
        'target_warehouse_id',
        'related_document_id',
        'related_order_id',
        'total_net',
        'total_gross',
        'currency',
        'payment_method',
        'paid',
        'paid_amount',
        'delivery_method',
        'delivery_tracking_number',
        'notes_internal',
        'notes_print',
        'status',
        'closed_at',
    ];

    /**
     * Rzutowanie atrybutów modelu na określone typy.
     */
    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'document_date' => 'date:Y-m-d',
            'issue_date' => 'date:Y-m-d',
            'delivery_date' => 'date:Y-m-d',
            'payment_date' => 'date:Y-m-d',
            'closed_at' => 'datetime',
            'total_net' => 'decimal:2',
            'total_gross' => 'decimal:2',
            'paid' => 'boolean',
            'paid_amount' => 'decimal:2',
        ];
    }

    /**
     * Rejestracja kolekcji mediów.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments');
    }

    /*
    |--------------------------------------------------------------------------
    | Relacje
    |--------------------------------------------------------------------------
    */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }
    public function childDocuments(): HasMany
    {
        return $this->hasMany(Document::class, 'related_document_id');
    }
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function customer(): BelongsTo
    {
        // Zakładając, że `customer_id` odnosi się do tabeli `users` lub dedykowanej `customers`
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }

    public function targetWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'target_warehouse_id');
    }

    public function parentDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'related_document_id');
    }

    public function relatedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'related_order_id');
    }

    public function childDocument(): HasOne
    {
        return $this->hasOne(Document::class, 'related_document_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DocumentItem::class);
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Atrybuty (Accessors & Mutators)
    |--------------------------------------------------------------------------
    */
    protected function warehouse(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->sourceWarehouse ?? $this->targetWarehouse,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes (Pomocnicze Zapytania)
    |--------------------------------------------------------------------------
    |
    | Te metody pozwalają na tworzenie reużywalnych zapytań, które można
    | łatwo łączyć i wykorzystywać w kontrolerach, zwłaszcza z QueryBuilder.
    |
    */

    /**
     * Scope dla wyszukiwania po typie dokumentu.
     * Zachowuje oryginalną nazwę `scopeType` dla kompatybilności wstecznej.
     *
     * @param Builder $query
     * @param DocumentType|string $type
     * @return Builder
     */
    public function scopeType(Builder $query, $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope do filtrowania dokumentów na podstawie SKU produktu w pozycjach dokumentu.
     * Wyszukuje częściowe dopasowanie (LIKE).
     *
     * @param Builder $query
     * @param string $sku
     * @return Builder
     */
    public function scopeWhereProductSku(Builder $query, string $sku): Builder
    {
        return $query->whereHas('items.product', function (Builder $q) use ($sku) {
            $q->where('sku', 'like', "%{$sku}%");
        });
    }

    /**
     * NOWY: Scope do filtrowania dokumentów na podstawie NAZWY produktu w pozycjach.
     * Wyszukuje częściowe dopasowanie (LIKE).
     *
     * @param Builder $query
     * @param string $name
     * @return Builder
     */
    public function scopeWhereProductName(Builder $query, string $name): Builder
    {
        return $query->whereHas('items.product', function (Builder $q) use ($name) {
            $q->where('name', 'like', "%{$name}%");
        });
    }

    /**
     * NOWY: Scope do filtrowania dokumentów na podstawie kodu EAN w pozycjach.
     *
     * @param Builder $query
     * @param string $ean
     * @return Builder
     */
    public function scopeWhereProductEan(Builder $query, string $ean): Builder
    {
        // Zakładamy, że model ProductVariant ma kolumnę 'barcode' lub 'ean'
        return $query->whereHas('items.variant', function (Builder $q) use ($ean) {
            $q->where('barcode', $ean);
        });
    }

    /**
     * NOWY: Scope do filtrowania dokumentów, które są nieopłacone i po terminie płatności.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeUnpaidAndOverdue(Builder $query): Builder
    {
        return $query->where('paid', false)
            ->whereNotNull('payment_date')
            ->where('payment_date', '<', Carbon::today());
    }

    /**
     * NOWY: Scope do filtrowania dokumentów dla określonego kontrahenta,
     * niezależnie czy jest dostawcą, czy klientem.
     *
     * @param Builder $query
     * @param int $contractorId ID kontrahenta (z tabeli users lub suppliers)
     * @return Builder
     */
    public function scopeWhereContractor(Builder $query, int $contractorId): Builder
    {
        return $query->where(function (Builder $q) use ($contractorId) {
            $q->where('supplier_id', $contractorId)
                ->orWhere('customer_id', $contractorId);
        });
    }

    /**
     * NOWY: Scope do filtrowania dokumentów na podstawie tego, czy mają powiązane zamówienie.
     *
     * @param Builder $query
     * @param bool $hasOrder
     * @return Builder
     */
    public function scopeHasRelatedOrder(Builder $query, bool $hasOrder = true): Builder
    {
        if ($hasOrder) {
            return $query->whereNotNull('related_order_id');
        }
        return $query->whereNull('related_order_id');
    }

    // --- Dedykowane scope'y dla każdego typu dla wygody (zachowane dla kompatybilności) ---
    public function scopePz(Builder $query): Builder
    {
        return $query->where('type', DocumentType::PZ);
    }
    public function scopeWz(Builder $query): Builder
    {
        return $query->where('type', DocumentType::WZ);
    }
    public function scopeMm(Builder $query): Builder
    {
        return $query->where('type', DocumentType::MM);
    }
    public function scopeRw(Builder $query): Builder
    {
        return $query->where('type', DocumentType::RW);
    }
    public function scopePw(Builder $query): Builder
    {
        return $query->where('type', DocumentType::PW);
    }
    public function scopeFs(Builder $query): Builder
    {
        return $query->where('type', DocumentType::FS);
    }
    public function scopeFvz(Builder $query): Builder
    {
        return $query->where('type', DocumentType::FVZ);
    }
    public function scopeZw(Builder $query): Builder
    {
        return $query->where('type', DocumentType::ZW);
    }
    public function scopeZrw(Builder $query): Builder
    {
        return $query->where('type', DocumentType::ZRW);
    }
    public function scopeInw(Builder $query): Builder
    {
        return $query->where('type', DocumentType::INW);
    }
}
