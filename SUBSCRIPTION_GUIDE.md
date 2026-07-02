# Subscription System Implementation Guide

## Overview

This subscription system allows users to subscribe to meal plans with the following features:

### Subscription Periods
- **Day**: Single day meal plan (breakfast, lunch, dinner) - NO doctor included
- **Week**: 7-day meal plan (breakfast, lunch, dinner) - Includes FREE doctor consultation
- **Month**: 30-day meal plan (breakfast, lunch, dinner) - Includes FREE doctor consultation
- **Quarterly**: 90-day meal plan (breakfast, lunch, dinner) - Includes FREE doctor consultation

### Key Features
✅ Pincode validation for delivery availability
✅ Automatic daily meal generation (breakfast, lunch, dinner)
✅ Doctor assignment for paid plans
✅ Meal customization with optional items
✅ Meal locking to prevent changes
✅ Subscription lifecycle management (pending → active → paused → cancelled)

---

## Database Schema

### New Migrations
1. **add_period_to_meal_plan_templates**: Adds `period` enum field
2. **add_doctor_id_to_subscriptions**: Adds doctor relationship

### Updated Tables
- `meal_plan_templates`: Now has `period` field (day/week/month)
- `subscriptions`: Now has `doctor_id` foreign key

---

## Models

### Subscription Model
```php
// Relationships
$subscription->customer();           // User who subscribed
$subscription->doctor();              // Assigned doctor (if applicable)
$subscription->template();            // Meal plan template
$subscription->preferences();         // Meal preferences
$subscription->dailySelections();    // Daily meal selections

// Useful methods
$subscription->includesDoctor();      // Check if subscription has doctor included
```

### MealPlanTemplate Model
```php
// Relationships
$template->creator();                 // User who created the template
$template->mealOptions();             // All meal options (breakfast/lunch/dinner)
$template->subscriptions();           // All subscriptions using this template

// Available periods: day, week, month (quarterly uses month template)
```

### ServiceablePincode Model
```php
// Stores all pincodes where delivery is available
// Must be active (is_active = true) for users to subscribe
```

---

## Services

### SubscriptionService

#### Create Subscription
```php
use App\Services\SubscriptionService;

$service = app(SubscriptionService::class);

// For day plan (no doctor)
$subscription = $service->createSubscription(
    user: $user,
    template: $mealTemplate,
    period: 'day',
    deliveryPincode: '110001',
    doctor: null
);

// For week/month/quarterly (with doctor)
$subscription = $service->createSubscription(
    user: $user,
    template: $mealTemplate,
    period: 'month',
    deliveryPincode: '110001',
    doctor: $doctor  // Required for paid plans
);
```

#### Manage Subscription Lifecycle
```php
// Activate (generates meals automatically)
$service->activateSubscription($subscription);

// Pause
$service->pauseSubscription($subscription);

// Resume
$service->resumeSubscription($subscription);

// Cancel
$service->cancelSubscription($subscription);
```

#### Change Meal Plan Template
```php
// Change to different meal plan (must have same period)
$service->changeMealPlanTemplate($subscription, $newTemplate);
```

#### Assign Doctor
```php
// Assign doctor to existing subscription
$service->assignDoctor($subscription, $doctor);
```

### PincodeService

```php
use App\Services\PincodeService;

$pincodeService = app(PincodeService::class);

// Check if pincode is serviceable
if ($pincodeService->isServiceable('110001')) {
    // User can subscribe
}

// Add new serviceable pincode
$pincodeService->addPincode('110001', 'Delhi Central');

// Deactivate pincode
$pincodeService->deactivatePincode('110001');

// Get all serviceable pincodes
$pincodes = $pincodeService->getServiceablePincodes();

// Validate user pincode
$validation = $pincodeService->validateUserPincode($userPincode);
// Returns: ['is_valid' => true/false, 'message' => '...']
```

### MealGenerationService

```php
use App\Services\MealGenerationService;

$mealService = app(MealGenerationService::class);

// Generate daily meals (breakfast, lunch, dinner for each day)
$meals = $mealService->generateMealsForSubscription($subscription);

// Regenerate meals (clears existing, creates new)
$meals = $mealService->regenerateMealsForSubscription($subscription);

// Get meals for date range
$meals = $mealService->getMealsForDateRange($subscription, $startDate, $endDate);
$breakfasts = $mealService->getMealsForDateRange($subscription, $startDate, $endDate, 'breakfast');

// Lock meals (prevent changes after certain date)
$lockedCount = $mealService->lockMealsUntilDate($subscription, $lockDate);

// Update a specific meal
$meal = $mealService->updateMealSelection($mealSelectionId, $newMealOptionId);

// Get available meals for user to choose from
$breakfastOptions = $mealService->getAvailableMealsForType($subscription, 'breakfast');
```

---

## Usage Examples

### Complete Subscription Flow

```php
use App\Services\SubscriptionService;
use App\Services\MealGenerationService;

// 1. Create meal plan template (by doctor/admin)
$template = MealPlanTemplate::create([
    'created_by' => $admin->id,
    'name' => 'Healthy Weight Loss Monthly Plan',
    'description' => 'Balanced diet for weight loss',
    'period' => 'month',
    'is_active' => true,
]);

// Add meal options (breakfast, lunch, dinner)
$template->mealOptions()->createMany([
    [
        'meal_type' => 'breakfast',
        'title' => 'Oats with Berries',
        'description' => 'Healthy oatmeal breakfast',
        'calories' => 300,
        'is_default' => true,
        'is_active' => true,
    ],
    [
        'meal_type' => 'lunch',
        'title' => 'Grilled Chicken Salad',
        'description' => 'Protein-rich lunch',
        'calories' => 450,
        'is_default' => true,
        'is_active' => true,
    ],
    [
        'meal_type' => 'dinner',
        'title' => 'Vegetable Soup',
        'description' => 'Light dinner',
        'calories' => 250,
        'is_default' => true,
        'is_active' => true,
    ],
]);

// 2. User subscribes
$subscriptionService = app(SubscriptionService::class);
$subscription = $subscriptionService->createSubscription(
    user: $customer,
    template: $template,
    period: 'month',
    deliveryPincode: '110001',
    doctor: $assignedDoctor  // Must have doctor for monthly
);

// 3. Admin/Doctor activates subscription
$subscription = $subscriptionService->activateSubscription($subscription);
// This automatically generates all daily meals!

// 4. User can view meals and customize
$breakfasts = $mealService->getAvailableMealsForType($subscription, 'breakfast');

// User changes a meal
$mealService->updateMealSelection($mealSelectionId, $differentMealOptionId);

// 5. Lock meals 1 day before delivery
$lockDate = now()->addDays(1);
$mealService->lockMealsUntilDate($subscription, $lockDate);
```

### Day Plan (without doctor)
```php
// Create day plan template (no doctor included)
$dayTemplate = MealPlanTemplate::create([
    'created_by' => $admin->id,
    'name' => 'Single Day Meal Pack',
    'period' => 'day',
]);

// User subscribes for single day
$subscription = $subscriptionService->createSubscription(
    user: $customer,
    template: $dayTemplate,
    period: 'day',
    deliveryPincode: '110001',
    doctor: null  // No doctor for day plans!
);
```

---

## Support Classes

### SubscriptionPeriods Helper
```php
use App\Support\SubscriptionPeriods;

// Get all periods
SubscriptionPeriods::all();  // ['day', 'week', 'month', 'quarterly']

// Get paid plans (with doctor)
SubscriptionPeriods::paidPlans();  // ['week', 'month', 'quarterly']

// Get free periods (no doctor)
SubscriptionPeriods::freePeriods();  // ['day']

// Check if period includes doctor
SubscriptionPeriods::includesDoctor('week');  // true
SubscriptionPeriods::includesDoctor('day');   // false

// Get human-readable label
SubscriptionPeriods::label('week');  // 'Weekly'
```

---

## Validation Rules

### Subscription Creation
- ✅ Pincode must be in serviceable pincodes list
- ✅ Period must match meal plan template period
- ✅ Doctor is REQUIRED for week/month/quarterly plans
- ✅ Doctor is NOT allowed for day plans
- ✅ Doctor must have role = 'doctor'

### Meal Plan Template
- ✅ Must have period specified (day/week/month)
- ✅ Must have meal options for breakfast, lunch, dinner
- ✅ At least one meal option per meal type must be marked as default

### Meal Selection
- ✅ Cannot change locked meals
- ✅ Meal option must belong to the subscription's template

---

## API Endpoints (Suggested)

```
POST   /subscriptions                  - Create subscription
GET    /subscriptions/{id}             - View subscription details
PATCH  /subscriptions/{id}/activate    - Activate subscription
PATCH  /subscriptions/{id}/pause       - Pause subscription
PATCH  /subscriptions/{id}/resume      - Resume subscription
DELETE /subscriptions/{id}             - Cancel subscription

GET    /subscriptions/{id}/meals       - List daily meals
PATCH  /subscriptions/{id}/meals/{id}  - Update meal selection
POST   /subscriptions/{id}/meals/lock  - Lock meals

GET    /pincodes                       - List serviceable pincodes
POST   /pincodes                       - Add pincode (admin only)
DELETE /pincodes/{id}                  - Deactivate pincode (admin only)
```

---

## Status Flow

```
pending ──→ active ──→ paused ──→ active
              ↓         ↓
           cancelled  cancelled
```

- **pending**: Subscription created, awaiting payment/activation
- **active**: Subscription is active, meals being delivered
- **paused**: Subscription temporarily paused
- **cancelled**: Subscription ended

---

## Meal Locking Example

Lock all meals up to tomorrow (so deliveries can be prepared):
```php
$mealService->lockMealsUntilDate($subscription, now()->addDay());
```

After locking:
- Users cannot change these meals
- System can prepare deliveries
- Meals not yet locked can still be customized

---

## Error Handling

```php
try {
    $subscription = $subscriptionService->createSubscription(...);
} catch (ValidationException $e) {
    // Handle validation errors
    $errors = $e->errors();
    // Example: ['delivery_pincode' => 'Delivery not available in this area']
} catch (Exception $e) {
    // Handle other errors
}
```

---

## Next Steps

1. Run migrations: `php artisan migrate`
2. Create meal plan templates via Filament admin panel
3. Implement API endpoints for subscription management
4. Create notification system for subscription events
5. Add payment gateway integration
