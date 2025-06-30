<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Category extends Model
{
    use HasFactory;
    use HasSlug;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'parent_id',
        'description',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }

    /**
     * Relacja do nadrzędnej kategorii.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * ✅ NOWA RELACJA: Bezpośrednie podkategorie (dzieci).
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /**
     * ✅ NOWA RELACJA: Rekurencyjne ładowanie wszystkich poziomów podkategorii.
     * To właśnie tej relacji używamy do budowy drzewa.
     */
    public function childrenRecursive(): HasMany
    {
        return $this->children()->with('childrenRecursive');
    }

    /**
     * Relacja do produktów w tej kategorii.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }


    /*
    |--------------------------------------------------------------------------
    | Scopes (Pomocnicze Zapytania)
    |--------------------------------------------------------------------------
    */

    /**
     * Zwraca tylko kategorie główne (bez rodzica).
     * Użycie: Category::root()->get();
     */
    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }
}
