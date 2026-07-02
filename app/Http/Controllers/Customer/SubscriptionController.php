<?php

namespace App\Http\Controllers\Customer;

use App\Contracts\PaymentGatewayInterface;
use App\Http\Controllers\Controller;
use App\Models\MealOption;
use App\Models\SubscriptionPlan;
use App\Models\ServiceablePincode;
use App\Models\Setting;
use App\Models\Subscription;
use App\Services\SubscriptionPeriodService;
use App\Support\MealTypes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function create(): View
    {
        // Check if user already has an active subscription
        $activeSubscription = Subscription::query()
            ->where('user_id', auth()->id())
            ->where('status', 'active')
            ->where('end_date', '>=', now()->toDateString())
            ->first();

        if ($activeSubscription) {
            return redirect()->route('customer.dashboard')
                ->with('warning', 'You already have an active subscription. You can only manage your current meals.');
        }

        $plans = SubscriptionPlan::query()
            ->with('template.mealOptions')
            ->where('is_active', true)
            ->get();

        $defaultPincode = Setting::getValue('default_delivery_pincode', '560001');

        return view('customer.subscriptions.create', [
            'plans' => $plans,
            'defaultPincode' => $defaultPincode,
            'defaultPlan' => $plans->first(),
        ]);
    }

    public function store(
        Request $request,
        SubscriptionPeriodService $periodService,
        PaymentGatewayInterface $paymentGateway
    ): RedirectResponse {
        // Check if user already has an active subscription
        $activeSubscription = Subscription::query()
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->where('end_date', '>=', now()->toDateString())
            ->first();

        if ($activeSubscription) {
            return back()->withErrors([
                'subscription' => 'You already have an active subscription. Please complete or cancel it before starting a new one.',
            ])->withInput();
        }

        $validated = $request->validate([
            'subscription_plan_id' => ['required', 'exists:subscription_plans,id'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'delivery_pincode' => ['nullable', 'string', 'max:10'],
            'preferences' => ['array'],
            'preferences.*' => ['nullable', 'exists:meal_options,id'],
        ]);

        $plan = SubscriptionPlan::findOrFail($validated['subscription_plan_id']);

        // Use default pincode if not provided
        $deliveryPincode = $validated['delivery_pincode'] ?? Setting::getValue('default_delivery_pincode', '560001');

        $isServiceable = ServiceablePincode::query()
            ->where('pincode', $deliveryPincode)
            ->where('is_active', true)
            ->exists();

        if (! $isServiceable) {
            return back()->withErrors([
                'delivery_pincode' => 'This pincode is not serviceable yet.',
            ])->withInput();
        }

        [$startDate, $endDate] = $periodService->resolve($plan->period, $validated['start_date']);

        $subscription = Subscription::create([
            'user_id' => $request->user()->id,
            'subscription_plan_id' => $plan->id,
            'meal_plan_template_id' => $plan->meal_plan_template_id,
            'period' => $plan->period,
            'status' => 'active',
            'delivery_pincode' => $deliveryPincode,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'activated_at' => now(),
        ]);

        foreach (MealTypes::ALL as $mealType) {
            $optionId = $validated['preferences'][$mealType] ?? MealOption::query()
                ->where('meal_plan_template_id', $plan->meal_plan_template_id)
                ->where('meal_type', $mealType)
                ->where('is_default', true)
                ->value('id');

            $subscription->preferences()->create([
                'meal_type' => $mealType,
                'meal_option_id' => $optionId,
            ]);
        }

        $subscription->load('preferences');
        $this->seedDailySelections($subscription);

        $paymentGateway->charge($subscription, $plan->price, 'INR', [
            'type' => 'subscription',
            'period' => $plan->period,
        ]);

        return redirect()->route('customer.dashboard')->with('status', 'Subscription activated and payment recorded.');
    }

    protected function seedDailySelections(Subscription $subscription): void
    {
        $preferences = $subscription->preferences->keyBy('meal_type');
        $cutoffTime = Setting::getValue('daily_meal_cutoff', '18:00');

        for ($date = $subscription->start_date->copy(); $date->lte($subscription->end_date); $date->addDay()) {
            foreach (MealTypes::ALL as $mealType) {
                $subscription->dailySelections()->updateOrCreate(
                    [
                        'meal_date' => $date->toDateString(),
                        'meal_type' => $mealType,
                    ],
                    [
                        'meal_option_id' => optional($preferences->get($mealType))->meal_option_id,
                        'locked_at' => $this->shouldLock($date, $cutoffTime) ? Carbon::parse($date->toDateString().' '.$cutoffTime) : null,
                    ],
                );
            }
        }
    }

    protected function shouldLock(Carbon $date, string $cutoffTime): bool
    {
        return now()->greaterThanOrEqualTo(Carbon::parse($date->toDateString().' '.$cutoffTime));
    }
}
