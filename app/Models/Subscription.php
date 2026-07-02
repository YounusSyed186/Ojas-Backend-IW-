<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Subscription extends Model
{
    protected $fillable = [
        'user_id',
        'subscription_plan_id',
        'meal_plan_template_id',
        'doctor_id',
        'period',
        'status',
        'delivery_pincode',
        'health_details',
        'delivery_address_line_1',
        'delivery_address_line_2',
        'delivery_city',
        'delivery_state',
        'start_date',
        'end_date',
        'activated_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'activated_at' => 'datetime',
            'health_details' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(MealPlanTemplate::class, 'meal_plan_template_id');
    }

    public function preferences(): HasMany
    {
        return $this->hasMany(SubscriptionMealPreference::class);
    }

    public function dailySelections(): HasMany
    {
        return $this->hasMany(DailyMealSelection::class);
    }

    public function payment(): MorphOne
    {
        return $this->morphOne(Payment::class, 'payable');
    }

    /**
     * Check if subscription includes doctor consultation
     */
    public function includesDoctor(): bool
    {
        return in_array($this->period, ['week', 'month', 'quarterly']);
    }
}
