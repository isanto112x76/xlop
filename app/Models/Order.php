<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * ✅ POPRAWKA: Zaktualizowano listę pól, aby była zgodna z nową strukturą bazy danych.
     */
    protected $fillable = [
        'baselinker_order_id',
        'external_order_id',
        'order_source',
        'date_add',
        'date_confirmed',
        'date_in_status',
        'customer_id',
        'billing_address_id',
        'shipping_address_id',
        'customer_login',
        'customer_comments',
        'baselinker_status_id',
        'total_gross',
        'payment_method',
        'is_cod',
        'delivery_price',
        'delivery_method',
        'delivery_tracking_number',
        'want_invoice',
        'related_wz_id',
        'sync_status',
        'last_synced_at',
    ];

    /**
     * ✅ POPRAWKA: Zaktualizowano castowanie typów dla nowych kolumn.
     */
    protected function casts(): array
    {
        return [
            'date_add' => 'datetime',
            'date_confirmed' => 'datetime',
            'date_in_status' => 'datetime',
            'last_synced_at' => 'datetime',
            'total_gross' => 'decimal:2',
            'delivery_price' => 'decimal:2',
            'is_cod' => 'boolean',
            'want_invoice' => 'boolean',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relacje
    |--------------------------------------------------------------------------
    */

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * ✅ NOWA RELACJA: Powiązanie z klientem.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * ✅ NOWA RELACJA: Powiązanie z adresem do faktury.
     */
    public function billingAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'billing_address_id');
    }

    /**
     * ✅ NOWA RELACJA: Powiązanie z adresem do wysyłki.
     */
    public function shippingAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'shipping_address_id');
    }

    /**
     * Powiązanie z dokumentem WZ, który został wygenerowany dla tego zamówienia.
     */
    public function wzDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'related_wz_id');
    }
}
