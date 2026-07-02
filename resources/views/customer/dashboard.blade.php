<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-sm uppercase tracking-[0.25em] text-amber-700">Customer Panel</p>
                <h1 class="text-2xl font-semibold text-stone-900">Welcome back, {{ auth()->user()->name }}</h1>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('customer.consultations.create') }}" class="rounded-full bg-amber-500 px-5 py-2 text-sm font-semibold text-stone-950">Book Consultation</a>
                @if (!$activeSubscription)
                    <a href="{{ route('customer.subscriptions.create') }}" class="rounded-full border border-stone-300 px-5 py-2 text-sm font-semibold text-stone-700">Start Subscription</a>
                @else
                    <button disabled class="cursor-not-allowed rounded-full border border-stone-200 bg-stone-100 px-5 py-2 text-sm font-semibold text-stone-400">Start Subscription</button>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 space-y-6">
        @if (session('status'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-3 text-sm text-emerald-700">
                {{ session('status') }}
            </div>
        @endif

        <!-- Active Subscription Status Bar -->
        @if ($activeSubscription)
            <div class="rounded-2xl bg-amber-600 px-6 py-4 text-white shadow-lg">
                <div class="flex items-center justify-between flex-wrap gap-4">
                    <div class="flex items-center gap-6">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-amber-100">Active Plan</p>
                            <p class="text-xl font-bold mt-1">{{ str($activeSubscription->period)->replace('_', ' ')->title() }}</p>
                        </div>
                        <div class="hidden sm:block w-px h-12 bg-white/20"></div>
                        <div class="hidden sm:block">
                            <p class="text-xs font-semibold uppercase tracking-wider text-amber-100">Duration</p>
                            <p class="text-sm mt-1">{{ $activeSubscription->start_date->format('d M') }} - {{ $activeSubscription->end_date->format('d M Y') }}</p>
                        </div>
                        <div class="hidden sm:block w-px h-12 bg-white/20"></div>
                        <div class="hidden sm:block">
                            <p class="text-xs font-semibold uppercase tracking-wider text-amber-100">Pincode</p>
                            <p class="text-sm mt-1">{{ $activeSubscription->delivery_pincode }}</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-xs font-semibold uppercase tracking-wider text-amber-100">{{ $activeSubscription->template?->name ?? 'Awaiting assignment' }}</p>
                        <p class="text-sm mt-1 font-semibold">{{ $activeSubscription->status }}</p>
                    </div>
                </div>
            </div>
        @else
            <div class="rounded-2xl border-2 border-dashed border-amber-300 bg-amber-50 px-6 py-5">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-amber-900">Get Started With Your Nutrition Plan</h3>
                        <p class="text-sm text-amber-800 mt-1">Choose a subscription plan and start your meal delivery journey</p>
                    </div>
                    <a href="{{ route('customer.subscriptions.create') }}" class="rounded-full bg-amber-600 hover:bg-amber-700 px-6 py-2 text-sm font-semibold text-white transition-colors">
                        Start Now
                    </a>
                </div>
            </div>
        @endif

        <!-- Main Content: Meal Schedule -->
        @if ($activeSubscription)
            <section class="rounded-2xl bg-white shadow-sm ring-1 ring-stone-200 overflow-hidden">
                <div class="bg-gradient-to-r from-stone-50 to-white px-6 py-5 border-b border-stone-200 mt-5">
                    <h2 class="text-xl font-bold text-stone-900">Your Meal Schedule</h2>
                    <p class="text-sm text-stone-600 mt-1">Select and customize your meals for each day</p>
                </div>
                <div class="p-6">
                    <livewire:customer.meal-selection-manager :subscription="$activeSubscription" />
                </div>
            </section>
        @endif

        <!-- Bottom Section: Quick Links -->
        <section class="grid gap-6 lg:grid-cols-3">
            <!-- Consultations -->
            <div class="rounded-2xl bg-white shadow-sm ring-1 ring-stone-200 overflow-hidden">
                <div class="bg-gradient-to-r from-blue-50 to-white px-6 py-4 border-b border-stone-200 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-stone-900">Doctor Sessions</h3>
                    <a href="{{ route('customer.consultations.create') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-700 bg-blue-50 hover:bg-blue-100 px-3 py-1 rounded-full transition-colors">
                        + Book
                    </a>
                </div>
                <div class="divide-y divide-stone-200">
                    @forelse ($consultations->take(3) as $consultation)
                        <div class="px-6 py-4 hover:bg-stone-50 transition-colors">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-stone-900">{{ $consultation->preferred_slot_at?->format('d M Y') }}</p>
                                    <p class="text-xs text-stone-600 mt-1">{{ $consultation->doctor?->name ?? 'Pending' }}</p>
                                </div>
                                <span class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-semibold text-blue-700 whitespace-nowrap">{{ $consultation->status }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="px-6 py-8 text-center text-sm text-stone-500">No consultations booked</div>
                    @endforelse
                </div>
            </div>

            <!-- Payments -->
            <div class="rounded-2xl bg-white shadow-sm ring-1 ring-stone-200 overflow-hidden">
                <div class="bg-gradient-to-r from-emerald-50 to-white px-6 py-4 border-b border-stone-200">
                    <h3 class="text-lg font-bold text-stone-900">Transaction History</h3>
                </div>
                <div class="divide-y divide-stone-200">
                    @forelse ($payments->take(3) as $payment)
                        <div class="px-6 py-4 hover:bg-stone-50 transition-colors">
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-stone-900 truncate">{{ strtoupper($payment->gateway) }}</p>
                                    <p class="text-xs text-stone-600 mt-1">{{ $payment->paid_at?->format('d M Y') }}</p>
                                </div>
                                <p class="font-semibold text-stone-900 text-sm whitespace-nowrap">{{ $payment->currency }} {{ number_format($payment->amount, 2) }}</p>
                            </div>
                        </div>
                    @empty
                        <div class="px-6 py-8 text-center text-sm text-stone-500">No transactions yet</div>
                    @endforelse
                </div>
            </div>

            <!-- Quick Stats/Info -->
            <div class="rounded-2xl bg-white shadow-sm ring-1 ring-stone-200 overflow-hidden">
                <div class="bg-gradient-to-r from-stone-900 to-stone-800 px-6 py-4 border-b border-stone-800">
                    <h3 class="text-lg font-bold text-stone-100">Account Overview</h3>
                </div>
                <div class="px-6 py-4 space-y-4">
                    @if ($activeSubscription)
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">Days Remaining</p>
                            <p class="text-2xl font-bold mt-2 text-stone-900">{{ (int)ceil(now()->diffInSeconds($activeSubscription->end_date) / 86400) }}</p>
                        </div>
                        <div class="pt-4 border-t border-stone-200">
                            <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">Total Consultations</p>
                            <p class="text-2xl font-bold mt-2 text-stone-900">{{ $consultations->count() }}</p>
                        </div>
                    @else
                        <div class="py-4">
                            <p class="text-sm text-stone-600">Start a subscription to see your account stats and meal schedule.</p>
                            <a href="{{ route('customer.subscriptions.create') }}" class="mt-4 inline-block rounded-full bg-amber-500 hover:bg-amber-600 px-4 py-2 text-sm font-semibold text-stone-900 transition-colors">
                                Get Started
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </section>
    </div>
</x-app-layout>
