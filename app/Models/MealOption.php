<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealOption extends Model
{
    protected $fillable = [
        'meal_plan_template_id',
        'meal_type',
        'category_slug',
        'title',
        'slug',
        'tag',
        'description',
        'calories',
        'price',
        'protein',
        'carbs',
        'fat',
        'ingredients',
        'sort_order',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'ingredients' => 'array',
            'price' => 'decimal:2',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function mealPlanTemplate(): BelongsTo
    {
        return $this->belongsTo(MealPlanTemplate::class);
    }
}
