<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentRefund extends Model
{
    protected $fillable = [
        'payment_id', 'order_item_id', 'requested_by', 'gateway_refund_id', 'idempotency_key',
        'amount', 'status', 'reason', 'payload', 'processed_at',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'payload' => 'array', 'processed_at' => 'datetime'];
    }

    public function payment(): BelongsTo { return $this->belongsTo(Payment::class); }
    public function orderItem(): BelongsTo { return $this->belongsTo(OrderItem::class); }
    public function requestedBy(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
}
