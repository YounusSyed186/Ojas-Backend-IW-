<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerMealPlan extends Model
{
    protected $fillable = [
        'user_id',
        'meal_plan_template_id',
        'assigned_by',
        'assigned_on',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'assigned_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(MealPlanTemplate::class, 'meal_plan_template_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
