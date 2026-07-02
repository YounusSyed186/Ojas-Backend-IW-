<?php

namespace App\Services;

use App\Models\DailyMealSelection;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class MealGenerationService
{
    private const MEAL_TYPES = ['breakfast', 'lunch', 'dinner'];

    /**
     * Generate daily meal selections for a subscription
     */
    public function generateMealsForSubscription(Subscription $subscription): Collection
    {
        // Get all default meal options for the template
        $mealOptions = $subscription->template->mealOptions()
            ->where('is_active', true)
            ->get()
            ->groupBy('meal_type');

        $meals = [];
        $currentDate = $subscription->start_date;

        // Generate meals for each day of subscription
        while ($currentDate <= $subscription->end_date) {
            foreach (self::MEAL_TYPES as $mealType) {
                // Get default meal option for this meal type, if available
                $defaultMeal = $mealOptions->get($mealType)?->first(fn($meal) => $meal->is_default);
                
                if ($defaultMeal) {
                    $meals[] = [
                        'subscription_id' => $subscription->id,
                        'meal_date' => $currentDate->copy(),
                        'meal_type' => $mealType,
                        'meal_option_id' => $defaultMeal->id,
                        'locked_at' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            $currentDate->addDay();
        }

        // Bulk insert all meals
        if (!empty($meals)) {
            DailyMealSelection::insert($meals);
        }

        // Return the created meals
        return $subscription->dailySelections()->get();
    }

    /**
     * Regenerate meals for a subscription (clear existing and create new)
     */
    public function regenerateMealsForSubscription(Subscription $subscription): Collection
    {
        // Delete existing meal selections
        $subscription->dailySelections()->delete();

        // Generate new meals
        return $this->generateMealsForSubscription($subscription);
    }

    /**
     * Get meal options for a specific date range and meal type
     */
    public function getMealsForDateRange(Subscription $subscription, Carbon $startDate, Carbon $endDate, ?string $mealType = null)
    {
        $query = $subscription->dailySelections()
            ->whereBetween('meal_date', [$startDate, $endDate]);

        if ($mealType) {
            $query->where('meal_type', $mealType);
        }

        return $query->get();
    }

    /**
     * Lock meals up to a certain date (prevent changes)
     */
    public function lockMealsUntilDate(Subscription $subscription, Carbon $lockDate): int
    {
        return $subscription->dailySelections()
            ->where('meal_date', '<=', $lockDate)
            ->whereNull('locked_at')
            ->update(['locked_at' => now()]);
    }

    /**
     * Update a specific meal selection
     */
    public function updateMealSelection(int $selectionId, int $mealOptionId): DailyMealSelection
    {
        $meal = DailyMealSelection::findOrFail($selectionId);

        if ($meal->locked_at) {
            throw new \Exception('This meal selection is locked and cannot be changed.');
        }

        $meal->update(['meal_option_id' => $mealOptionId]);

        return $meal->fresh();
    }

    /**
     * Get available meals for user selection
     */
    public function getAvailableMealsForType(Subscription $subscription, string $mealType)
    {
        return $subscription->template->mealOptions()
            ->where('meal_type', $mealType)
            ->where('is_active', true)
            ->get();
    }
}
