<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Ojas Cuisine') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="bg-[var(--sand)] text-stone-900 antialiased">
        <div class="relative overflow-hidden">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(217,119,6,0.18),_transparent_32%),radial-gradient(circle_at_80%_20%,_rgba(21,128,61,0.18),_transparent_25%),linear-gradient(180deg,_rgba(255,251,235,0.8),_rgba(245,245,244,1))]"></div>
            <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <header class="flex items-center justify-between py-8">
                    <a href="/" class="flex items-center gap-3">
                        <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-500 font-black text-stone-950">O</span>
                        <span>
                            <span class="block text-xs uppercase tracking-[0.3em] text-amber-700">Ojas Cuisine</span>
                            <span class="block text-sm text-stone-600">Doctor-guided meal subscriptions</span>
                        </span>
                    </a>
                    <div class="flex gap-3">
                        <a href="{{ route('login') }}" class="rounded-full border border-stone-300 px-5 py-2 text-sm font-semibold text-stone-700">Login</a>
                        <a href="{{ route('register') }}" class="rounded-full bg-stone-900 px-5 py-2 text-sm font-semibold text-white">Register</a>
                    </div>
                </header>

                <section class="grid gap-10 pb-20 pt-10 lg:grid-cols-[1.05fr,0.95fr] lg:items-center">
                    <div class="max-w-2xl">
                        <p class="text-sm uppercase tracking-[0.3em] text-amber-700">Nutrition. Routine. Delivery.</p>
                        <h1 class="mt-5 text-5xl font-semibold leading-[1.05] sm:text-6xl">A meal plan that starts with a doctor and arrives at your doorstep.</h1>
                        <p class="mt-6 max-w-xl text-lg leading-8 text-stone-600">Ojas Cuisine combines nutrition consultations with breakfast, lunch, and dinner subscriptions tailored to each customer and delivered only to approved pincodes.</p>
                        <div class="mt-8 flex flex-wrap gap-4">
                            <a href="{{ route('register') }}" class="rounded-full bg-amber-500 px-6 py-3 font-semibold text-stone-950">Start your plan</a>
                            <a href="{{ route('phone-login') }}" class="rounded-full border border-stone-300 px-6 py-3 font-semibold text-stone-700">Try dummy OTP login</a>
                        </div>
                        <div class="mt-10 grid gap-4 sm:grid-cols-3">
                            <div class="rounded-3xl bg-white/80 p-5 shadow-sm ring-1 ring-stone-200">
                                <p class="text-3xl font-semibold text-stone-900">{{ $serviceableCount }}</p>
                                <p class="mt-2 text-sm text-stone-600">serviceable pincodes configured in admin</p>
                            </div>
                            <div class="rounded-3xl bg-white/80 p-5 shadow-sm ring-1 ring-stone-200">
                                <p class="text-3xl font-semibold text-stone-900">4</p>
                                <p class="mt-2 text-sm text-stone-600">prepaid subscription periods</p>
                            </div>
                            <div class="rounded-3xl bg-white/80 p-5 shadow-sm ring-1 ring-stone-200">
                                <p class="text-3xl font-semibold text-stone-900">{{ $consultationFee?->currency ?? 'INR' }} {{ number_format($consultationFee?->amount ?? 0, 0) }}</p>
                                <p class="mt-2 text-sm text-stone-600">doctor consultation fee</p>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-6">
                        <div class="rounded-[2rem] bg-stone-900 p-8 text-white shadow-[0_35px_90px_rgba(28,25,23,0.18)]">
                            <p class="text-sm uppercase tracking-[0.25em] text-amber-300">Check delivery availability</p>
                            <div class="mt-6">
                                <livewire:pincode-checker />
                            </div>
                        </div>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div class="rounded-[2rem] bg-white p-6 shadow-sm ring-1 ring-stone-200">
                                <p class="text-sm uppercase tracking-[0.25em] text-amber-700">Consultation</p>
                                <h2 class="mt-3 text-2xl font-semibold">Book a nutrition doctor</h2>
                                <p class="mt-3 text-stone-600">Customers register, pay the consultation fee, and request their preferred slot.</p>
                            </div>
                            <div class="rounded-[2rem] bg-white p-6 shadow-sm ring-1 ring-stone-200">
                                <p class="text-sm uppercase tracking-[0.25em] text-amber-700">Subscriptions</p>
                                <h2 class="mt-3 text-2xl font-semibold">Breakfast, lunch, dinner</h2>
                                <p class="mt-3 text-stone-600">Doctors assign the default template and customers refine each meal with allowed alternatives.</p>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="pb-20">
                    <div class="flex items-end justify-between gap-6">
                        <div>
                            <p class="text-sm uppercase tracking-[0.25em] text-amber-700">Featured templates</p>
                            <h2 class="mt-3 text-4xl font-semibold">Meals built around structured daily routines.</h2>
                        </div>
                    </div>
                    <div class="mt-10 grid gap-6 lg:grid-cols-3">
                        @forelse ($plans as $plan)
                            <article class="rounded-[2rem] bg-white p-8 shadow-sm ring-1 ring-stone-200">
                                <p class="text-sm uppercase tracking-[0.25em] text-amber-700">{{ $plan->mealOptions->count() }} meal options</p>
                                <h3 class="mt-3 text-2xl font-semibold text-stone-900">{{ $plan->name }}</h3>
                                <p class="mt-3 text-stone-600">{{ $plan->description ?: 'Balanced meals with rotating breakfast, lunch, and dinner choices.' }}</p>
                                <div class="mt-6 flex flex-wrap gap-2">
                                    @foreach ($plan->mealOptions->take(6) as $option)
                                        <span class="rounded-full bg-stone-100 px-3 py-1 text-sm text-stone-700">{{ ucfirst($option->meal_type) }}: {{ $option->title }}</span>
                                    @endforeach
                                </div>
                            </article>
                        @empty
                            <article class="rounded-[2rem] bg-white p-8 shadow-sm ring-1 ring-stone-200 lg:col-span-3">
                                <h3 class="text-2xl font-semibold text-stone-900">Templates will appear here after admin setup</h3>
                                <p class="mt-3 text-stone-600">Seeders create example plans automatically so the public site has meaningful content on first boot.</p>
                            </article>
                        @endforelse
                    </div>
                </section>
            </div>
        </div>
        @livewireScripts
    </body>
</html>
