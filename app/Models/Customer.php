<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'company_name',
        'tax_id',
        'baselinker_user_login',
    ];

    /**
     * Relacja do adresów klienta.
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /**
     * Relacja do zamówień klienta.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
