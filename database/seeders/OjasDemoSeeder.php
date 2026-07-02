<?php

namespace Database\Seeders;

use App\Models\ConsultationFee;
use App\Models\CustomerMealPlan;
use App\Models\MealPlanTemplate;
use App\Models\ServiceablePincode;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\SubscriptionPeriodService;
use App\Support\MealTypes;
use Illuminate\Database\Seeder;

class OjasDemoSeeder extends Seeder
{
    public function run(): void
    {
        ServiceablePincode::upsert([
            ['pincode' => '400001', 'label' => 'South Mumbai', 'is_active' => true],
            ['pincode' => '560001', 'label' => 'Central Bengaluru', 'is_active' => true],
            ['pincode' => '110001', 'label' => 'Connaught Place', 'is_active' => true],
        ], ['pincode'], ['label', 'is_active']);

        ConsultationFee::updateOrCreate(
            ['currency' => 'INR'],
            ['amount' => 1499, 'is_active' => true],
        );

        Setting::updateOrCreate(['key' => 'daily_meal_cutoff'], ['value' => '18:00']);
        Setting::updateOrCreate(['key' => 'default_delivery_pincode'], ['value' => '560001']);

        $doctor = User::query()->where('role', 'doctor')->firstOrFail();
        $customer = User::query()->where('role', 'customer')->firstOrFail();

        $templates = collect([
            [
                'name' => 'Metabolic Reset',
                'description' => 'High-protein breakfast, balanced lunch, and lighter dinner to support steady energy.',
                'options' => [
                    ['meal_type' => 'shots', 'title' => 'Turmeric Ginger Shot', 'calories' => 60, 'is_default' => false, 'price' => 99, 'protein' => 1, 'carbs' => 12, 'fat' => 0, 'tag' => 'Immunity', 'ingredients' => ['Turmeric', 'Ginger', 'Lemon', 'Black pepper']],
                    ['meal_type' => 'breakfast', 'title' => 'Moringa Upma Bowl', 'calories' => 340, 'is_default' => true, 'price' => 249, 'protein' => 14, 'carbs' => 52, 'fat' => 8, 'tag' => 'Breakfast', 'ingredients' => ['Moringa', 'Semolina', 'Vegetables', 'Peanuts']],
                    ['meal_type' => 'breakfast', 'title' => 'Millet Idli Plate', 'calories' => 320, 'is_default' => false, 'price' => 229, 'protein' => 12, 'carbs' => 50, 'fat' => 7, 'tag' => 'Gut Friendly', 'ingredients' => ['Millet', 'Lentils', 'Coconut chutney', 'Sambar']],
                    ['meal_type' => 'lunch', 'title' => 'Red Rice Thali', 'calories' => 520, 'is_default' => true, 'price' => 349, 'protein' => 22, 'carbs' => 74, 'fat' => 14, 'tag' => 'Balanced', 'ingredients' => ['Red rice', 'Dal', 'Seasonal vegetables', 'Curd']],
                    ['meal_type' => 'lunch', 'title' => 'Paneer Quinoa Bowl', 'calories' => 480, 'is_default' => false, 'price' => 389, 'protein' => 30, 'carbs' => 42, 'fat' => 20, 'tag' => 'High Protein', 'ingredients' => ['Paneer', 'Quinoa', 'Greens', 'Yogurt dressing']],
                    ['meal_type' => 'dinner', 'title' => 'Khichdi Comfort Plate', 'calories' => 410, 'is_default' => true, 'price' => 299, 'protein' => 16, 'carbs' => 58, 'fat' => 12, 'tag' => 'Light', 'ingredients' => ['Rice', 'Moong dal', 'Ghee', 'Roasted vegetables']],
                    ['meal_type' => 'dinner', 'title' => 'Vegetable Stew Combo', 'calories' => 390, 'is_default' => false, 'price' => 319, 'protein' => 13, 'carbs' => 46, 'fat' => 15, 'tag' => 'Restorative', 'ingredients' => ['Vegetables', 'Coconut milk', 'Appam', 'Spices']],
                ],
            ],
            [
                'name' => 'Gut Calm Routine',
                'description' => 'A softer, lower-spice plan focused on digestibility and predictable meal timing.',
                'options' => [
                    ['meal_type' => 'breakfast', 'title' => 'Poha With Seeds', 'calories' => 300, 'is_default' => true, 'price' => 219, 'protein' => 10, 'carbs' => 48, 'fat' => 6, 'tag' => 'Gentle', 'ingredients' => ['Flattened rice', 'Seeds', 'Curry leaves', 'Lemon']],
                    ['meal_type' => 'lunch', 'title' => 'Moong Dal Lunch Box', 'calories' => 450, 'is_default' => true, 'price' => 329, 'protein' => 20, 'carbs' => 64, 'fat' => 10, 'tag' => 'Digestive', 'ingredients' => ['Moong dal', 'Rice', 'Bottle gourd', 'Curd']],
                    ['meal_type' => 'dinner', 'title' => 'Soft Phulka Dinner', 'calories' => 380, 'is_default' => true, 'price' => 289, 'protein' => 14, 'carbs' => 55, 'fat' => 9, 'tag' => 'Low Spice', 'ingredients' => ['Phulka', 'Dal', 'Steamed vegetables', 'Buttermilk']],
                ],
            ],
            [
                'name' => 'Family Balance',
                'description' => 'A broader menu with familiar comfort meals and simple alternates for each course.',
                'options' => [
                    ['meal_type' => 'breakfast', 'title' => 'Besan Chilla Wrap', 'calories' => 330, 'is_default' => true, 'price' => 239, 'protein' => 18, 'carbs' => 34, 'fat' => 11, 'tag' => 'Protein', 'ingredients' => ['Besan', 'Paneer', 'Mint chutney', 'Greens']],
                    ['meal_type' => 'lunch', 'title' => 'Dal Chawal Bowl', 'calories' => 510, 'is_default' => true, 'price' => 319, 'protein' => 19, 'carbs' => 78, 'fat' => 12, 'tag' => 'Comfort', 'ingredients' => ['Rice', 'Dal', 'Pickle', 'Salad']],
                    ['meal_type' => 'dinner', 'title' => 'Light Sabzi Dinner', 'calories' => 360, 'is_default' => true, 'price' => 279, 'protein' => 12, 'carbs' => 48, 'fat' => 10, 'tag' => 'Family', 'ingredients' => ['Phulka', 'Seasonal sabzi', 'Dal', 'Salad']],
                ],
            ],
        ])->map(function (array $templateData) use ($doctor) {
            $template = MealPlanTemplate::updateOrCreate(
                ['name' => $templateData['name']],
                [
                    'created_by' => $doctor->id,
                    'description' => $templateData['description'],
                    'is_active' => true,
                ],
            );

            foreach ($templateData['options'] as $option) {
                $template->mealOptions()->updateOrCreate(
                    ['meal_type' => $option['meal_type'], 'title' => $option['title']],
                    [
                        'category_slug' => $option['category_slug'] ?? $option['meal_type'],
                        'slug' => str($option['title'])->slug()->toString(),
                        'tag' => $option['tag'] ?? null,
                        'description' => $option['title'].' for '.$template->name,
                        'calories' => $option['calories'],
                        'price' => $option['price'] ?? 0,
                        'protein' => $option['protein'] ?? null,
                        'carbs' => $option['carbs'] ?? null,
                        'fat' => $option['fat'] ?? null,
                        'ingredients' => $option['ingredients'] ?? [],
                        'sort_order' => $option['sort_order'] ?? 0,
                        'is_default' => $option['is_default'],
                        'is_active' => true,
                    ],
                );
            }

            return $template->fresh('mealOptions');
        });

        $assignedTemplate = $templates->first();

        $marketingPlans = [
            [
                'name' => 'Day Pass',
                'description' => 'Try a full day of curated meals.',
                'period' => 'one_day',
                'price' => 399,
                'features' => ['3 meals + 1 shot', 'Free delivery', 'Cancel anytime'],
                'featured' => false,
            ],
            [
                'name' => 'Weekly',
                'description' => 'Most popular — 7 days of nourishment.',
                'period' => 'weekly',
                'price' => 1000,
                'features' => ['21 meals + 7 shots', 'Nutritionist check-in', 'Flexible swaps', 'Priority delivery'],
                'featured' => true,
                'badge' => 'Most loved',
            ],
            [
                'name' => 'Monthly',
                'description' => 'Personal plan — first month consult on us.',
                'period' => 'monthly',
                'price' => 0,
                'features' => ['Custom meal plan', '1:1 doctor consult', 'Health tracking', 'Unlimited swaps'],
                'featured' => false,
            ],
        ];

        $plans = collect($marketingPlans)->map(function (array $planData) use ($assignedTemplate, $doctor) {
            return SubscriptionPlan::updateOrCreate(
                ['name' => $planData['name']],
                [
                    'description' => $planData['description'],
                    'meal_plan_template_id' => $assignedTemplate->id,
                    'period' => $planData['period'],
                    'price' => $planData['price'],
                    'features' => $planData['features'],
                    'featured' => $planData['featured'] ?? false,
                    'badge' => $planData['badge'] ?? null,
                    'is_active' => true,
                    'created_by' => $doctor->id,
                ],
            );
        });

        CustomerMealPlan::updateOrCreate(
            ['user_id' => $customer->id, 'meal_plan_template_id' => $assignedTemplate->id],
            [
                'assigned_by' => $doctor->id,
                'assigned_on' => now()->toDateString(),
                'is_active' => true,
            ],
        );

        $planForSubscription = $plans->firstWhere('period', 'weekly') ?? $plans->first();

        $subscription = Subscription::query()->firstOrCreate(
            ['user_id' => $customer->id],
            [
                'subscription_plan_id' => $planForSubscription->id,
                'meal_plan_template_id' => $assignedTemplate->id,
                'period' => 'weekly',
                'status' => 'active',
                'delivery_pincode' => '400001',
                'start_date' => now()->toDateString(),
                'end_date' => app(SubscriptionPeriodService::class)->resolve('weekly', now())[1]->toDateString(),
                'activated_at' => now(),
            ],
        );

        foreach (MealTypes::ALL as $mealType) {
            $option = $assignedTemplate->mealOptions->firstWhere(fn ($mealOption) => $mealOption->meal_type === $mealType && $mealOption->is_default);

            $subscription->preferences()->updateOrCreate(
                ['meal_type' => $mealType],
                ['meal_option_id' => $option?->id],
            );

            $subscription->dailySelections()->updateOrCreate(
                ['meal_date' => now()->toDateString(), 'meal_type' => $mealType],
                ['meal_option_id' => $option?->id],
            );
        }
    }
}
