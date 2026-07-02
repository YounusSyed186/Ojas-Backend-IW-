<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm uppercase tracking-[0.2em] text-amber-700">Meal Delivery</p>
            <h1 class="text-2xl font-semibold text-stone-900">Start your Ojas subscription</h1>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="grid gap-8 lg:grid-cols-[0.8fr,1.2fr]">
            <aside class="rounded-[2rem] bg-stone-900 p-8 text-stone-50">
                <p class="text-sm uppercase tracking-[0.2em] text-amber-300">Serviceability</p>
                <h2 class="mt-3 text-3xl font-semibold">Check your pincode first</h2>
                <p class="mt-3 text-stone-300">Subscriptions are allowed only for admin-approved serviceable pincodes.</p>
                <div class="mt-8">
                    <livewire:pincode-checker />
                </div>
            </aside>

            <div class="rounded-[2rem] bg-white p-8 shadow-sm ring-1 ring-stone-200">
                @if ($errors->has('subscription'))
                    <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                        {{ $errors->first('subscription') }}
                    </div>
                @endif
                <form method="POST" action="{{ route('customer.subscriptions.store') }}" class="space-y-8" x-data="{ 
                    selectedPlan: null,
                    plans: @json($plans),
                    updatePlan() {
                        this.selectedPlan = this.plans.find(p => p.id == document.querySelector('select[name=subscription_plan_id]').value);
                    }
                }" @change="updatePlan()">
                    @csrf

                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <x-input-label for="delivery_pincode" :value="__('Delivery Pincode (Optional)')" />
                            <x-text-input id="delivery_pincode" class="mt-1 block w-full" type="text" name="delivery_pincode" :value="old('delivery_pincode', $defaultPincode)" />
                            <p class="mt-1 text-xs text-stone-500">Leave empty to use default: {{ $defaultPincode }}</p>
                            <x-input-error :messages="$errors->get('delivery_pincode')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="start_date" :value="__('Start Date')" />
                            <x-text-input id="start_date" class="mt-1 block w-full" type="date" name="start_date" :value="old('start_date', now()->addDay()->toDateString())" required />
                            <x-input-error :messages="$errors->get('start_date')" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="subscription_plan_id" :value="__('Subscription Plan')" />
                        <select id="subscription_plan_id" name="subscription_plan_id" class="mt-1 block w-full rounded-2xl border-stone-300 focus:border-amber-500 focus:ring-amber-500" required>
                            <option value="">Select a subscription plan</option>
                            @foreach ($plans as $plan)
                                @php
                                    $period = str($plan->period)->replace('_', ' ')->title();
                                @endphp
                                <option value="{{ $plan->id }}" @selected(old('subscription_plan_id', optional($defaultPlan)->id) == $plan->id)>
                                    {{ $plan->name }} - {{ $period }} (INR {{ number_format($plan->price, 2) }})
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('subscription_plan_id')" class="mt-2" />
                    </div>

                    <div class="space-y-6">
                        <div>
                            <p class="text-sm uppercase tracking-[0.2em] text-amber-700">Meal Preferences</p>
                            <h2 class="mt-2 text-2xl font-semibold text-stone-900">Choose your default alternates</h2>
                        </div>
                        @foreach (['breakfast', 'lunch', 'dinner'] as $mealType)
                            <div>
                                <x-input-label :for="'pref_'.$mealType" :value="ucfirst($mealType)" />
                                <select id="pref_{{ $mealType }}" name="preferences[{{ $mealType }}]" class="mt-1 block w-full rounded-2xl border-stone-300 focus:border-amber-500 focus:ring-amber-500">
                                    <option value="">Use plan default</option>
                                    @foreach ($plans->flatMap(fn($plan) => optional($plan->template)->mealOptions)->where('meal_type', $mealType)->where('is_active', true)->unique('id') as $option)
                                        <option value="{{ $option->id }}" @selected(old("preferences.$mealType") == $option->id)>{{ $option->title }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex justify-end">
                        <x-primary-button>Activate Subscription</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
