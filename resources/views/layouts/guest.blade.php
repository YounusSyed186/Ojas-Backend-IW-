<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Ojas Cuisine') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="bg-[var(--sand)] text-stone-900 antialiased">
        <div class="relative min-h-screen overflow-hidden">
            <div class="absolute inset-x-0 top-0 h-[420px] bg-[radial-gradient(circle_at_top_left,_rgba(217,119,6,0.18),_transparent_45%),radial-gradient(circle_at_top_right,_rgba(22,101,52,0.14),_transparent_30%)]"></div>
            <div class="relative mx-auto flex min-h-screen max-w-6xl items-center px-4 py-10 sm:px-6 lg:px-8">
                <div class="grid w-full gap-10 lg:grid-cols-[1.1fr,0.9fr] lg:items-center">
                    <div class="space-y-6">
                        <a href="/" class="inline-flex items-center gap-3">
                            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-500 text-lg font-black text-stone-950">O</span>
                            <span>
                                <span class="block text-xs uppercase tracking-[0.35em] text-amber-700">Ojas Cuisine</span>
                                <span class="block text-sm text-stone-600">Meals designed with doctor-guided nutrition.</span>
                            </span>
                        </a>
                        <div class="max-w-xl space-y-4">
                            <p class="text-sm uppercase tracking-[0.25em] text-amber-700">Healthy daily living</p>
                            <h1 class="text-4xl font-semibold leading-tight sm:text-5xl">Consult, subscribe, and shape every meal around your plan.</h1>
                            <p class="text-lg leading-8 text-stone-600">Ojas combines a nutrition doctor workflow with daily prepared breakfast, lunch, and dinner delivery in approved pincodes.</p>
                        </div>
                    </div>

                    <div class="rounded-[2rem] bg-white/90 p-8 shadow-[0_30px_80px_rgba(28,25,23,0.12)] ring-1 ring-stone-200/80 backdrop-blur">
                        {{ $slot }}
                    </div>
                </div>
            </div>
        </div>
        @livewireScripts
    </body>
</html>
