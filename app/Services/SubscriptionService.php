<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\MealPlanTemplate;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class SubscriptionService
{
    public function __construct(
        private PincodeService $pincodeService,
        private MealGenerationService $mealGenerationService,
    ) {}

    /**
     * Create a new subscription from a subscription plan
     */
    public function createSubscriptionFromPlan(
        User $user,
        SubscriptionPlan $plan,
        string $deliveryPincode = '',
        ?User $doctor = null,
    ): Subscription {
        // Use default pincode if not provided
        if (empty($deliveryPincode)) {
            $deliveryPincode = Setting::getValue('default_delivery_pincode', '560001');
        }

        // Validate pincode
        $pincodeValidation = $this->pincodeService->validateUserPincode($deliveryPincode);
        if (!$pincodeValidation['is_valid']) {
            throw ValidationException::withMessages([
                'delivery_pincode' => $pincodeValidation['message'],
            ]);
        }

        // Validate no doctor for day plan
        if ($plan->period === 'one_day' && $doctor) {
            throw ValidationException::withMessages([
                'doctor_id' => "Day plans cannot include doctor consultation",
            ]);
        }

        // Calculate subscription dates
        $startDate = Carbon::today();
        $endDate = $this->calculateEndDate($startDate, $plan->period);

        // Create subscription
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'meal_plan_template_id' => $plan->meal_plan_template_id,
            'doctor_id' => $doctor?->id,
            'period' => $plan->period,
            'status' => 'pending',
            'delivery_pincode' => $deliveryPincode,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        return $subscription;
    }

    /**
     * Create a new subscription with validation (legacy method for backward compatibility)
     */
    public function createSubscription(
        User $user,
        MealPlanTemplate $template,
        string $period,
        string $deliveryPincode,
        ?User $doctor = null,
    ): Subscription {
        // Validate pincode
        $pincodeValidation = $this->pincodeService->validateUserPincode($deliveryPincode);
        if (!$pincodeValidation['is_valid']) {
            throw ValidationException::withMessages([
                'delivery_pincode' => $pincodeValidation['message'],
            ]);
        }

        // Validate period matches template period
        if ($template->period !== $period) {
            throw ValidationException::withMessages([
                'period' => "Template period ({$template->period}) does not match requested period ($period)",
            ]);
        }

        // Validate no doctor for day plan
        if ($period === 'day' && $doctor) {
            throw ValidationException::withMessages([
                'doctor_id' => "Day plans cannot include doctor consultation",
            ]);
        }

        // Calculate subscription dates
        $startDate = Carbon::today();
        $endDate = $this->calculateEndDate($startDate, $period);

        // Create subscription
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'meal_plan_template_id' => $template->id,
            'doctor_id' => $doctor?->id,
            'period' => $period,
            'status' => 'pending',
            'delivery_pincode' => $deliveryPincode,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        return $subscription;
    }

    /**
     * Activate a subscription and generate meals
     */
    public function activateSubscription(Subscription $subscription): Subscription
    {
        if ($subscription->status === 'active') {
            throw new \Exception('Subscription is already active');
        }

        // Generate daily meal selections
        $this->mealGenerationService->generateMealsForSubscription($subscription);

        // Update subscription status
        $subscription->update([
            'status' => 'active',
            'activated_at' => now(),
        ]);

        return $subscription->fresh();
    }

    /**
     * Cancel a subscription
     */
    public function cancelSubscription(Subscription $subscription, ?string $reason = null): Subscription
    {
        $subscription->update([
            'status' => 'cancelled',
        ]);

        return $subscription->fresh();
    }

    /**
     * Pause a subscription
     */
    public function pauseSubscription(Subscription $subscription): Subscription
    {
        if ($subscription->status !== 'active') {
            throw new \Exception('Only active subscriptions can be paused');
        }

        $subscription->update([
            'status' => 'paused',
        ]);

        return $subscription->fresh();
    }

    /**
     * Resume a paused subscription
     */
    public function resumeSubscription(Subscription $subscription): Subscription
    {
        if ($subscription->status !== 'paused') {
            throw new \Exception('Only paused subscriptions can be resumed');
        }

        $subscription->update([
            'status' => 'active',
        ]);

        return $subscription->fresh();
    }

    /**
     * Change meal plan template
     */
    public function changeMealPlanTemplate(Subscription $subscription, MealPlanTemplate $newTemplate): Subscription
    {
        // Validate new template period matches subscription period
        if ($newTemplate->period !== $subscription->period) {
            throw ValidationException::withMessages([
                'template' => "New template period ({$newTemplate->period}) must match subscription period ({$subscription->period})",
            ]);
        }

        // If subscription is active, regenerate meals
        if ($subscription->status === 'active') {
            $this->mealGenerationService->regenerateMealsForSubscription($subscription);
        }

        $subscription->update([
            'meal_plan_template_id' => $newTemplate->id,
        ]);

        return $subscription->fresh();
    }

    /**
     * Assign a doctor to subscription
     */
    public function assignDoctor(Subscription $subscription, User $doctor): Subscription
    {
        // Only paid plans can have doctors
        if (!in_array($subscription->period, ['week', 'month', 'quarterly', 'weekly', 'monthly', 'quarterly'])) {
            throw ValidationException::withMessages([
                'doctor_id' => "Cannot assign doctor to {$subscription->period} plan",
            ]);
        }

        // Verify doctor is a valid doctor user
        if ($doctor->role !== 'doctor') {
            throw ValidationException::withMessages([
                'doctor_id' => 'Selected user is not a doctor',
            ]);
        }

        $subscription->update([
            'doctor_id' => $doctor->id,
        ]);

        return $subscription->fresh();
    }

    /**
     * Get subscription with doctor (if available)
     */
    public function getSubscriptionDetails(Subscription $subscription)
    {
        return [
            'id' => $subscription->id,
            'user' => $subscription->customer,
            'doctor' => $subscription->includesDoctor() ? $subscription->doctor : null,
            'template' => $subscription->template,
            'period' => $subscription->period,
            'status' => $subscription->status,
            'delivery_pincode' => $subscription->delivery_pincode,
            'start_date' => $subscription->start_date,
            'end_date' => $subscription->end_date,
            'activated_at' => $subscription->activated_at,
            'includes_doctor' => $subscription->includesDoctor(),
            'meals_generated' => $subscription->dailySelections()->count(),
        ];
    }

    /**
     * Calculate end date based on period
     */
    private function calculateEndDate(Carbon $startDate, string $period): Carbon
    {
        return match ($period) {
            'day', 'one_day' => $startDate->copy(),
            'week', 'weekly' => $startDate->copy()->addWeek()->subDay(),
            'month', 'monthly' => $startDate->copy()->addMonth()->subDay(),
            'quarterly' => $startDate->copy()->addMonths(3)->subDay(),
            default => throw new \InvalidArgumentException("Invalid period: $period"),
        };
    }
}
