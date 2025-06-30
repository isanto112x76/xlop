<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tag extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        // Możesz dodać inne pola, jeśli są w migracji, np. 'description'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        // np. 'is_active' => 'boolean',
    ];

    /**
     * Get the products associated with the tag.
     */
    public function products(): BelongsToMany
    {
        // Nazwa tabeli pośredniczącej to 'product_tag' zgodnie z konwencją Laravela
        // (nazwy modeli w liczbie pojedynczej, w kolejności alfabetycznej, oddzielone podkreślnikiem).
        // Jeśli nazwa tabeli jest inna, musisz ją tutaj podać jako drugi argument.
        // Np. return $this->belongsToMany(Product::class, 'custom_product_tag_table');
        return $this->belongsToMany(Product::class);
    }

    // Możesz dodać inne relacje lub metody pomocnicze, jeśli są potrzebne.
}

