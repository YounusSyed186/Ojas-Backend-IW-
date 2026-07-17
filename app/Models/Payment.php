<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Payment extends Model
{
    protected $fillable = [
        'gateway',
        'reference',
        'gateway_order_id',
        'gateway_payment_id',
        'attempt_number',
        'idempotency_key',
        'amount',
        'currency',
        'status',
        'failure_code',
        'failure_description',
        'payload',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function refunds()
    {
        return $this->hasMany(PaymentRefund::class);
    }
}
