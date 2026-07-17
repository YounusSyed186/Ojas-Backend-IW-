<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Order extends Model
{
    protected $fillable = [
        'order_number', 'user_id', 'cart_id', 'cart_version', 'status', 'payment_status',
        'currency', 'subtotal', 'discount_total', 'tax_total', 'delivery_fee', 'grand_total',
        'customer_name', 'customer_phone', 'delivery_address_line_1', 'delivery_address_line_2',
        'delivery_city', 'delivery_state', 'delivery_pincode', 'placed_at', 'paid_at',
        'abandoned_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2', 'discount_total' => 'decimal:2', 'tax_total' => 'decimal:2',
            'delivery_fee' => 'decimal:2', 'grand_total' => 'decimal:2', 'cart_version' => 'integer',
            'placed_at' => 'datetime', 'paid_at' => 'datetime', 'abandoned_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string { return 'order_number'; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function cart(): BelongsTo { return $this->belongsTo(Cart::class); }
    public function items(): HasMany { return $this->hasMany(OrderItem::class); }
    public function payments(): MorphMany { return $this->morphMany(Payment::class, 'payable'); }
}
