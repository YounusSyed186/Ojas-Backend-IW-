<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Consultation extends Model
{
    protected $fillable = [
        'user_id',
        'doctor_id',
        'consultation_fee_id',
        'status',
        'payment_status',
        'preferred_slot_at',
        'scheduled_for',
        'request_notes',
        'doctor_notes',
    ];

    protected function casts(): array
    {
        return [
            'preferred_slot_at' => 'datetime',
            'scheduled_for' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function fee(): BelongsTo
    {
        return $this->belongsTo(ConsultationFee::class, 'consultation_fee_id');
    }

    public function payment(): MorphOne
    {
        return $this->morphOne(Payment::class, 'payable');
    }
}
