<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MealOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MealController extends Controller
{
    private const CATEGORIES = [
        'shots' => [
            'slug' => 'shots',
            'title' => 'Early Morning Shots',
            'desc' => 'Cold-pressed wellness',
            'time' => '6 - 8 AM',
            'intro' => 'Tiny bottles, big intent. Cold-pressed at dawn to wake your system, gently.',
        ],
        'breakfast' => [
            'slug' => 'breakfast',
            'title' => 'Breakfast',
            'desc' => 'Slow-burning energy',
            'time' => '8 - 10 AM',
            'intro' => 'Plates engineered for steady energy - protein-forward, fibre-rich, never heavy.',
        ],
        'lunch' => [
            'slug' => 'lunch',
            'title' => 'Lunch',
            'desc' => 'Balanced & satisfying',
            'time' => '12 - 2 PM',
            'intro' => 'A proper midday reset. Balanced macros, vivid flavour, zero post-meal slump.',
        ],
        'dinner' => [
            'slug' => 'dinner',
            'title' => 'Dinner',
            'desc' => 'Light & restorative',
            'time' => '7 - 9 PM',
            'intro' => 'Light, warming, easy on digestion - designed to help you wind down well.',
        ],
    ];

    public function index(): JsonResponse
    {
        return response()->json(['meals' => $this->catalogMeals()]);
    }

    public function show(string $slug): JsonResponse
    {
        $meal = $this->catalogMeals()->firstWhere('slug', $slug);

        if (! $meal) {
            return response()->json(['message' => 'Meal not found.'], 404);
        }

        return response()->json(['meal' => $meal]);
    }

    public function categories(): JsonResponse
    {
        return response()->json(['categories' => $this->catalogCategories()]);
    }

    public function categoryMeals(string $slug): JsonResponse
    {
        $category = $this->catalogCategories()->firstWhere('slug', $slug);

        if (! $category) {
            return response()->json(['message' => 'Category not found.'], 404);
        }

        $meals = $this->catalogMeals()->where('category', $slug)->values();

        return response()->json(['category' => $category, 'meals' => $meals]);
    }

    private function catalogMeals(): Collection
    {
        return MealOption::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->map(fn (MealOption $option) => $this->mealOptionToCatalogItem($option));
    }

    private function catalogCategories(): Collection
    {
        return $this->catalogMeals()
            ->pluck('category')
            ->unique()
            ->values()
            ->map(fn (string $slug) => self::CATEGORIES[$slug] ?? [
                'slug' => $slug,
                'title' => Str::headline($slug),
                'desc' => 'Fresh daily meals',
                'time' => '',
                'intro' => 'Freshly prepared meals from the OJAS kitchen.',
            ])
            ->values();
    }

    private function mealOptionToCatalogItem(MealOption $option): array
    {
        $category = $option->category_slug ?: $option->meal_type;
        $calories = (int) ($option->calories ?? 0);

        return [
            'slug' => $option->slug ?: Str::slug($option->title.'-'.$option->id),
            'name' => $option->title,
            'tag' => $option->tag ?: Str::headline($category),
            'kcal' => $calories,
            'price' => (float) $option->price,
            'protein' => (int) ($option->protein ?? max(1, round($calories * 0.08))),
            'carbs' => (int) ($option->carbs ?? max(1, round($calories * 0.12))),
            'fat' => (int) ($option->fat ?? max(0, round($calories * 0.04))),
            'desc' => $option->description ?: $option->title,
            'ingredients' => $option->ingredients ?: [],
            'category' => $category,
        ];
    }
}
