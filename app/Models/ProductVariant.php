<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use App\Models\ProductPrice;
use Illuminate\Support\Str;
use App\Models\StockLevel;
use Spatie\MediaLibrary\MediaCollections\Models\Media; // <-- ten import



class ProductVariant extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;
    use SoftDeletes;

    protected $fillable = [
        'product_id',
        'name',
        'sku',
        'ean',
        'barcode',
        'position',
        'is_default',
        'description_override',
        'weight_override',
        'attributes_override',
        'marketplace_attributes_override',
        'override_product_description',
        'override_product_weight',
        'override_product_attributes',
        'override_product_marketplace_attributes',
        'has_own_media',
    ];

    protected $casts = [
        'product_id' => 'integer',
        'position' => 'integer',
        'is_default' => 'boolean',
        'override_product_description' => 'boolean',
        'override_product_weight' => 'boolean',
        'override_product_attributes' => 'boolean',
        'override_product_marketplace_attributes' => 'boolean',
        'has_own_media' => 'boolean',
        'description_override' => 'array',
        'weight_override' => 'decimal:3',
        'attributes_override' => 'array',
        'marketplace_attributes_override' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relacje
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    public function stockBatches()
    {
        return $this->hasMany(StockBatch::class, 'product_variant_id');
    }
    public function prices()
    {
        return $this->hasMany(ProductPrice::class, 'variant_id');
    }

    public function stockLevels()
    {
        return $this->hasMany(StockLevel::class, 'product_variant_id');
    }

    public function media(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(\Spatie\MediaLibrary\MediaCollections\Models\Media::class, 'model');
    }

    // Effective fields (jeśli chcesz pobrać z produktu, jeśli nie nadpisane)
    public function getEffectiveDescriptionAttribute()
    {
        if ($this->override_product_description && !empty($this->description_override)) {
            return $this->description_override;
        }
        return $this->product ? $this->product->description : null;
    }

    public function getEffectiveWeightAttribute()
    {
        if ($this->override_product_weight && !is_null($this->weight_override)) {
            return $this->weight_override;
        }
        return $this->product ? $this->product->weight : null;
    }
    public function getThumbnailAttribute(): ?string
    {
        // 1. Sprawdź, czy wariant ma własne media i czy są załadowane.
        // Używamy `media` - domyślnej kolekcji ze Spatie Media Library, lub 'variant_images', jeśli taką zdefiniowałeś.
        if ($this->has_own_media && $this->relationLoaded('media') && $this->media->isNotEmpty()) {
            // Zwróć konwersję 'thumb', jeśli istnieje, w przeciwnym razie oryginalny plik.
            return $this->getFirstMediaUrl('default', 'thumb') ?: $this->getFirstMediaUrl();
        }

        // 2. Jeśli wariant nie ma własnych mediów, sięgnij do produktu nadrzędnego.
        // Upewnij się, że relacja 'product' z jego mediami jest załadowana.
        $this->loadMissing('product.media');

        if ($this->product && $this->product->media->isNotEmpty()) {
            // Zwróć miniaturkę z produktu głównego.
            return $this->product->getFirstMediaUrl('images', 'thumb') ?: $this->product->getFirstMediaUrl('images');
        }

        // 3. Jeśli nigdzie nie ma zdjęcia, zwróć null.
        return null;
    }
    public function getEffectiveAttributesAttribute()
    {
        if ($this->override_product_attributes && !empty($this->attributes_override)) {
            return $this->attributes_override;
        }
        return $this->product ? $this->product->attributes : [];
    }

    public function getEffectiveMarketplaceAttributesAttribute()
    {
        if ($this->override_product_marketplace_attributes && !empty($this->marketplace_attributes_override)) {
            return $this->marketplace_attributes_override;
        }
        return $this->product ? $this->product->marketplace_attributes : [];
    }

    // Stock summary dla pojedynczego wariantu
    public function getTotalStockIndividualAttribute(): int
    {
        return $this->stockLevels()->sum('quantity');
    }

    public function getTotalAvailableStockIndividualAttribute(): int
    {
        return $this->stockLevels()->sum(\DB::raw('quantity - reserved_quantity'));
    }

    public function getTotalReservedStockIndividualAttribute(): int
    {
        return $this->stockLevels()->sum('reserved_quantity');
    }

    public function getTotalIncomingStockIndividualAttribute(): int
    {
        return $this->stockLevels()->sum('incoming_quantity');
    }

    // Media
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('variant_images')
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


    /**
     * Boot a new instance of the model.
     * Automatyczne generowanie sluga.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($variant) {
            $variant->slug = self::generateUniqueSlug($variant);
        });

        static::updating(function ($variant) {
            // Sprawdź, czy nazwa produktu lub wariantu się zmieniła, lub czy slug jest pusty
            if ($variant->isDirty('name') || $variant->product->isDirty('name') || empty($variant->slug)) {
                $variant->slug = self::generateUniqueSlug($variant);
            }
        });
    }

    /**
     * Generuje unikalny slug dla wariantu.
     * Podstawą jest product.name + variant.name.
     */
    protected static function generateUniqueSlug(ProductVariant $variant): string
    {
        // Załaduj relację product, jeśli nie jest załadowana, aby uniknąć dodatkowego zapytania w pętli
        $variant->loadMissing('product');
        $productName = $variant->product ? $variant->product->name : 'produkt';
        $variantName = $variant->name;

        $baseSlug = Str::slug($productName . '-' . $variantName);
        $slug = $baseSlug;
        $count = 1;

        // Sprawdź unikalność i dodaj licznik, jeśli to konieczne
        // Ignoruj bieżący wariant, jeśli aktualizujemy
        $query = static::where('slug', $slug);
        if ($variant->exists) {
            $query->where('id', '!=', $variant->id);
        }

        while ($query->clone()->exists()) { // Użyj clone(), aby uniknąć modyfikacji oryginalnego zapytania
            $slug = $baseSlug . '-' . $count++;
            $query = static::where('slug', $slug);
            if ($variant->exists) {
                $query->where('id', '!=', $variant->id);
            }
        }

        return $slug;
    }
    // W app/Models/ProductVariant.php

}
