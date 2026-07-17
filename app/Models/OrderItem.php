<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'meal_option_id', 'meal_name', 'meal_slug', 'meal_type', 'category_slug',
        'unit_price', 'quantity', 'line_total', 'delivery_date', 'fulfillment_status', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2', 'line_total' => 'decimal:2', 'quantity' => 'integer',
            'delivery_date' => 'date', 'cancelled_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function mealOption(): BelongsTo { return $this->belongsTo(MealOption::class); }
    public function refunds(): HasMany { return $this->hasMany(PaymentRefund::class); }
}
