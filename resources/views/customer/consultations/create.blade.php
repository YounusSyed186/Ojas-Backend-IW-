<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm uppercase tracking-[0.2em] text-amber-700">Nutrition Doctor</p>
            <h1 class="text-2xl font-semibold text-stone-900">Book a consultation</h1>
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="rounded-[2rem] bg-white p-8 shadow-sm ring-1 ring-stone-200">
            <div class="mb-8 flex items-end justify-between">
                <div>
                    <p class="text-sm uppercase tracking-[0.2em] text-amber-700">Consultation Fee</p>
                    <h2 class="mt-2 text-3xl font-semibold text-stone-900">{{ $fee?->currency ?? 'INR' }} {{ number_format($fee?->amount ?? 0, 2) }}</h2>
                </div>
                <p class="max-w-sm text-sm text-stone-600">This uses the dummy gateway for now and immediately records the booking as paid.</p>
            </div>

            <form method="POST" action="{{ route('customer.consultations.store') }}" class="space-y-6">
                @csrf
                <div>
                    <x-input-label for="preferred_slot_at" :value="__('Preferred Slot')" />
                    <x-text-input id="preferred_slot_at" class="mt-1 block w-full" type="datetime-local" name="preferred_slot_at" :value="old('preferred_slot_at')" required />
                    <x-input-error :messages="$errors->get('preferred_slot_at')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="request_notes" :value="__('Notes for the doctor')" />
                    <textarea id="request_notes" name="request_notes" rows="5" class="mt-1 block w-full rounded-2xl border-stone-300 focus:border-amber-500 focus:ring-amber-500">{{ old('request_notes') }}</textarea>
                    <x-input-error :messages="$errors->get('request_notes')" class="mt-2" />
                </div>
                <div class="flex justify-end">
                    <x-primary-button>Pay and Book Consultation</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
