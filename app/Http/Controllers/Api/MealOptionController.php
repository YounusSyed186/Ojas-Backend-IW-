<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MealPlanTemplate;
use App\Models\MealOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MealOptionController extends Controller
{
    /**
     * Get all active meal plan templates with their options
     */
    public function templates(): JsonResponse
    {
        $templates = MealPlanTemplate::with(['mealOptions' => function ($query) {
            $query->where('is_active', true);
        }])
            ->where('is_active', true)
            ->get();

        return response()->json(['templates' => $templates]);
    }

    /**
     * Get a specific template with its options
     */
    public function templateShow(int $id): JsonResponse
    {
        $template = MealPlanTemplate::with(['mealOptions' => function ($query) {
            $query->where('is_active', true);
        }])
            ->where('is_active', true)
            ->findOrFail($id);

        return response()->json(['template' => $template]);
    }

    /**
     * Get meal options grouped by meal type for a template
     */
    public function optionsByTemplate(int $templateId): JsonResponse
    {
        $template = MealPlanTemplate::where('is_active', true)->findOrFail($templateId);

        $options = MealOption::where('meal_plan_template_id', $templateId)
            ->where('is_active', true)
            ->get()
            ->groupBy('meal_type');

        return response()->json([
            'template' => $template,
            'options_by_type' => $options,
        ]);
    }
}
