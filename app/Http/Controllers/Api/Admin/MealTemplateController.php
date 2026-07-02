<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Concerns\AppliesListQuery;
use App\Http\Controllers\Controller;
use App\Models\MealPlanTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MealTemplateController extends Controller
{
    use AppliesListQuery;

    public function index(Request $request): JsonResponse
    {
        $templates = $this->applyListQuery(
            MealPlanTemplate::query()->withCount('mealOptions')->with('creator:id,name'),
            $request,
            ['name', 'description'],
            ['is_active'],
        );

        return response()->json(['templates' => $templates]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $template = MealPlanTemplate::findOrFail($id);
        $template->update($validated);

        return response()->json([
            'message' => 'Meal template updated successfully.',
            'template' => $template->fresh()->loadCount('mealOptions'),
        ]);
    }
}
