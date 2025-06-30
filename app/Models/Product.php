<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media; // <-- ten import

class Product extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;
    use SoftDeletes;
    public const DEFAULT_PRODUCT_LOAD = [
        'variants',
        'variants.prices',
        'variants.stockLevels',
        'variants.media',
        'defaultVariant',
        'defaultVariant.prices',
        'defaultVariant.stockLevels',
        'defaultVariant.media',
        'tags',
        'media',
        'manufacturer',
        'supplier',
        'category',
        'links',
        'bundleItems',
        'bundleItems.componentVariant',
        'bundleItems.componentVariant.prices',
        'bundleItems.componentVariant.stockLevels',
        'bundleItems.componentVariant.media',
    ];
    protected $fillable = [
        'name',
        'sku',
        'ean',
        'pos_code',
        'foreign_id',
        'description',
        'status',
        'product_type',
        'category_id',
        'manufacturer_id',
        'supplier_id',
        'weight',
        'dimensions',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'attributes',
        'marketplace_attributes',
        'manage_stock',
        'variants_share_stock',
    ];

    protected $casts = [
        'category_id' => 'integer',
        'manufacturer_id' => 'integer',
        'supplier_id' => 'integer',
        'weight' => 'decimal:3',
        'dimensions' => 'array',
        'description' => 'array',
        'attributes' => 'array',
        'marketplace_attributes' => 'array',
        'manage_stock' => 'boolean',
        'variants_share_stock' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relacje
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function manufacturer()
    {
        return $this->belongsTo(Manufacturer::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class)->orderBy('position');
    }

    public function defaultVariant()
    {
        return $this->hasOne(ProductVariant::class)->where('is_default', true)->whereNull('deleted_at');
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'product_tag', 'product_id', 'tag_id');
    }

    public function links()
    {
        return $this->hasMany(ProductLink::class, 'product_id');
    }

    public function bundleItems()
    {
        return $this->hasMany(ProductBundleItem::class, 'bundle_product_id');
    }

    // Media
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('product_images')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
    }

    public function registerMediaConversions(Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(300) // lub dowolny rozmiar miniatury
            ->height(300)
            ->sharpen(10)
            ->nonQueued(); // Jeśli chcesz od razu, bez kolejki
    }

    // Akcesory - dla podglądu/summary stock/ceny (opcjonalnie)
    public function getShortDescriptionAttribute()
    {
        return (is_array($this->description) && isset($this->description['short'])) ? (string) $this->description['short'] : null;
    }

    public function getFullDescriptionAttribute()
    {
        if (is_array($this->description) && isset($this->description['full'])) {
            return (string) $this->description['full'];
        }
        if (is_string($this->description) && !is_null(json_decode($this->description))) {
            $decoded = json_decode($this->description, true);
            return $decoded['full'] ?? (is_string($this->description) ? $this->description : null);
        }
        return is_string($this->description) ? $this->description : null;
    }

    public function getIsBundleAttribute(): bool
    {
        return $this->product_type === 'bundle';
    }

    // Akcesory stockowe (agregacja)
    public function getTotalStockAttribute(): int
    {
        $this->loadMissing(['defaultVariant.stockLevels', 'variants.stockLevels']);

        if ($this->getIsBundleAttribute()) {
            return $this->getAvailableStockAttribute();
        }

        $defaultVariant = $this->relationLoaded('defaultVariant') ? $this->defaultVariant : $this->defaultVariant()->first();

        if (!$defaultVariant)
            return 0;

        if (!$this->manage_stock || ($this->manage_stock && $this->variants_share_stock)) {
            return (int) $defaultVariant->total_stock_individual;
        }

        $total = 0;
        foreach ($this->variants()->whereNull('deleted_at')->get() as $variant) {
            $total += $variant->total_stock_individual;
        }
        return (int) $total;
    }

    public function getAvailableStockAttribute(): int
    {
        $this->loadMissing(['defaultVariant.stockLevels', 'variants.stockLevels', 'bundleItems.componentVariant.stockLevels']);

        if ($this->getIsBundleAttribute()) {
            $bundleItems = $this->bundleItems()->with(['componentVariant.stockLevels'])->get();
            if ($bundleItems->isEmpty())
                return 0;

            $possibleSets = [];
            foreach ($bundleItems as $item) {
                if (!$item->componentVariant || $item->componentVariant->trashed())
                    return 0;
                $componentAvailableStock = $item->componentVariant->total_available_stock_individual;
                if ($item->quantity <= 0)
                    return 0;
                $possibleSets[] = floor($componentAvailableStock / $item->quantity);
            }
            return empty($possibleSets) ? 0 : (int) min($possibleSets);
        }

        $defaultVariant = $this->relationLoaded('defaultVariant') ? $this->defaultVariant : $this->defaultVariant()->first();
        if (!$defaultVariant)
            return 0;

        if (!$this->manage_stock || ($this->manage_stock && $this->variants_share_stock)) {
            return (int) $defaultVariant->total_available_stock_individual;
        }

        $totalAvailable = 0;
        foreach ($this->variants()->whereNull('deleted_at')->get() as $variant) {
            $totalAvailable += $variant->total_available_stock_individual;
        }
        return (int) $totalAvailable;
    }

    public function getReservedStockAttribute(): int
    {
        $this->loadMissing(['defaultVariant.stockLevels', 'variants.stockLevels']);
        if ($this->getIsBundleAttribute())
            return 0;

        $defaultVariant = $this->relationLoaded('defaultVariant') ? $this->defaultVariant : $this->defaultVariant()->first();
        if (!$defaultVariant)
            return 0;

        if (!$this->manage_stock || ($this->manage_stock && $this->variants_share_stock)) {
            return (int) $defaultVariant->total_reserved_stock_individual;
        }

        $totalReserved = 0;
        foreach ($this->variants()->whereNull('deleted_at')->get() as $variant) {
            $totalReserved += $variant->total_reserved_stock_individual;
        }
        return (int) $totalReserved;
    }

    public function getIncomingStockAttribute(): int
    {
        $this->loadMissing(['defaultVariant.stockLevels', 'variants.stockLevels']);
        if ($this->getIsBundleAttribute())
            return 0;

        $defaultVariant = $this->relationLoaded('defaultVariant') ? $this->defaultVariant : $this->defaultVariant()->first();
        if (!$defaultVariant)
            return 0;

        if (!$this->manage_stock || ($this->manage_stock && $this->variants_share_stock)) {
            return (int) $defaultVariant->total_incoming_stock_individual;
        }

        $totalIncoming = 0;
        foreach ($this->variants()->whereNull('deleted_at')->get() as $variant) {
            $totalIncoming += $variant->total_incoming_stock_individual;
        }
        return (int) $totalIncoming;
    }
}
