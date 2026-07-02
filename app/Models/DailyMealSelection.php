<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyMealSelection extends Model
{
    protected $fillable = [
        'subscription_id',
        'meal_date',
        'meal_type',
        'meal_option_id',
        'locked_at',
    ];

    protected function casts(): array
    {
        return [
            'meal_date' => 'date',
            'locked_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function mealOption(): BelongsTo
    {
        return $this->belongsTo(MealOption::class);
    }
}
