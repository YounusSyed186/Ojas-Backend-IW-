<?php

use App\Models\MealOption;
use App\Models\Setting;
use App\Models\Subscription;
use Illuminate\Support\Carbon;
use Livewire\Component;

new class extends Component
{
    public Subscription $subscription;

    public string $selectedDate = '';

    public array $mealSelections = [];

    public function mount(Subscription $subscription): void
    {
        $this->subscription = $subscription->load(['template.mealOptions', 'dailySelections.mealOption', 'preferences']);
        $this->selectedDate = now()->between($subscription->start_date, $subscription->end_date)
            ? now()->toDateString()
            : $subscription->start_date->toDateString();

        $this->syncSelections();
    }

    public function updatedSelectedDate(): void
    {
        $this->subscription->load(['preferences', 'dailySelections.mealOption']);
        $this->syncSelections();
    }

    public function save(): void
    {
        $cutoffTime = Setting::getValue('daily_meal_cutoff', '18:00');
        $cutoff = Carbon::parse($this->selectedDate.' '.$cutoffTime);

        if ($cutoff->isPast()) {
            $this->addError('selectedDate', 'This meal date is already locked by the cutoff time.');

            return;
        }

        $selectedDateFormatted = Carbon::parse($this->selectedDate)->toDateString();

        foreach ($this->mealSelections as $mealType => $mealOptionId) {
            $this->subscription->dailySelections()->updateOrCreate(
                [
                    'meal_date' => $selectedDateFormatted,
                    'meal_type' => $mealType,
                ],
                [
                    'meal_option_id' => $mealOptionId ?: null,
                ],
            );
        }

        $this->subscription->refresh()->load(['template.mealOptions', 'dailySelections.mealOption', 'preferences']);
        $this->syncSelections();
        session()->flash('meal-status', 'Meal selections updated.');
    }

    protected function syncSelections(): void
    {
        $selectedDateFormatted = Carbon::parse($this->selectedDate)->toDateString();
        
        // Filter daily selections for the selected date using closure to properly compare dates
        $selectionMap = $this->subscription->dailySelections
            ->filter(fn($selection) => $selection->meal_date->toDateString() === $selectedDateFormatted)
            ->keyBy('meal_type');

        // Load preferences for defaults
        $preferencesMap = $this->subscription->preferences->keyBy('meal_type');

        $this->mealSelections = collect(['breakfast', 'lunch', 'dinner'])->mapWithKeys(
            function (string $mealType) use ($selectionMap, $preferencesMap) {
                // Use selected meal option if it exists, otherwise use default preference
                $selectedMealOptionId = optional($selectionMap->get($mealType))->meal_option_id;
                
                if ($selectedMealOptionId) {
                    return [$mealType => $selectedMealOptionId];
                }
                
                // Fallback to preference/default
                return [$mealType => optional($preferencesMap->get($mealType))->meal_option_id];
            }
        )->all();
    }

    public function optionsFor(string $mealType)
    {
        return MealOption::query()
            ->where('meal_plan_template_id', $this->subscription->meal_plan_template_id)
            ->where('meal_type', $mealType)
            ->where('is_active', true)
            ->orderBy('title')
            ->get();
    }
};
?>

<div class="space-y-5">
    @if (session('meal-status'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ session('meal-status') }}
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-[1fr,2fr]">
        <!-- Date Selector -->
        <div class="rounded-2xl border border-stone-200 bg-white p-6">
            <label for="selectedDate" class="text-sm font-semibold text-stone-700">Edit Meals for Date</label>
            <input id="selectedDate" wire:model.live="selectedDate" type="date" min="{{ $subscription->start_date->toDateString() }}" max="{{ $subscription->end_date->toDateString() }}" class="mt-3 block w-full rounded-2xl border-stone-300 focus:border-amber-500 focus:ring-amber-500">
            @error('selectedDate')
                <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
            @enderror

            <!-- Meal Selectors -->
            <div class="mt-6 space-y-4 border-t border-stone-200 pt-6">
                @foreach (['breakfast', 'lunch', 'dinner'] as $mealType)
                    <div>
                        <label for="meal_{{ $mealType }}" class="text-sm font-semibold capitalize text-stone-700">{{ $mealType }}</label>
                        <select id="meal_{{ $mealType }}" wire:model="mealSelections.{{ $mealType }}" wire:key="meal-{{ $mealType }}-{{ $selectedDate }}" class="mt-2 block w-full rounded-2xl border-stone-300 focus:border-amber-500 focus:ring-amber-500">
                            <option value="">No selection</option>
                            @foreach ($this->optionsFor($mealType) as $option)
                                <option value="{{ $option->id }}" @selected((int)($mealSelections[$mealType] ?? 0) === $option->id)>{{ $option->title }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach

                <button wire:click="save" type="button" class="mt-6 w-full rounded-full bg-stone-900 px-5 py-3 text-sm font-semibold text-white hover:bg-stone-800">Save meal choices</button>
            </div>
        </div>

        <!-- Meals Table -->
        <div class="rounded-2xl border border-stone-200 bg-white overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-stone-100 border-b border-stone-200">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-stone-700">Date</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-stone-700">Breakfast</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-stone-700">Lunch</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-stone-700">Dinner</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-200">
                        @php
                            $allDates = collect();
                            $current = $subscription->start_date->copy();
                            while ($current <= $subscription->end_date) {
                                $allDates->push($current->toDateString());
                                $current->addDay();
                            }
                        @endphp

                        @forelse ($allDates as $date)
                            @php
                                $dateFormatted = \Illuminate\Support\Carbon::parse($date)->toDateString();
                                $meals = $subscription->dailySelections
                                    ->filter(fn($s) => $s->meal_date->toDateString() === $dateFormatted)
                                    ->keyBy('meal_type');
                            @endphp
                            <tr class="hover:bg-stone-50 transition-colors @if($date === $selectedDate) bg-amber-50 @endif">
                                <td class="px-6 py-4">
                                    <button 
                                        wire:click="$set('selectedDate', '{{ $date }}')" 
                                        type="button"
                                        class="text-sm font-semibold @if($date === $selectedDate) text-amber-700 @else text-stone-900 hover:text-amber-700 @endif">
                                        {{ \Illuminate\Support\Carbon::parse($date)->format('M d, Y') }}
                                    </button>
                                </td>
                                <td class="px-6 py-4 text-sm text-stone-600">
                                    {{ optional($meals->get('breakfast'))->mealOption?->title ?? '—' }}
                                </td>
                                <td class="px-6 py-4 text-sm text-stone-600">
                                    {{ optional($meals->get('lunch'))->mealOption?->title ?? '—' }}
                                </td>
                                <td class="px-6 py-4 text-sm text-stone-600">
                                    {{ optional($meals->get('dinner'))->mealOption?->title ?? '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-8 text-center text-sm text-stone-500">
                                    No meal dates available
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
