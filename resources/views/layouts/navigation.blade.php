<nav x-data="{ open: false }" class="border-b border-stone-200 bg-white/90 backdrop-blur">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-20 justify-between">
            <div class="flex items-center gap-10">
                <a href="{{ route('customer.dashboard') }}" class="flex items-center gap-3">
                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-amber-500 font-black text-stone-950">O</span>
                    <span>
                        <span class="block text-xs uppercase tracking-[0.3em] text-amber-700">Ojas Cuisine</span>
                        <span class="block text-sm text-stone-500">Customer Panel</span>
                    </span>
                </a>

                <div class="hidden items-center gap-6 sm:flex">
                    <x-nav-link :href="route('customer.dashboard')" :active="request()->routeIs('customer.dashboard')">Dashboard</x-nav-link>
                    <x-nav-link :href="route('customer.consultations.create')" :active="request()->routeIs('customer.consultations.*')">Consultation</x-nav-link>
                    <x-nav-link :href="route('customer.subscriptions.create')" :active="request()->routeIs('customer.subscriptions.*')">Subscription</x-nav-link>
                </div>
            </div>

            <div class="hidden items-center sm:flex sm:gap-3">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-full border border-stone-300 px-4 py-2 text-sm font-semibold text-stone-700 hover:bg-stone-100">
                        Log Out
                    </button>
                </form>
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center gap-3 rounded-full border border-stone-300 bg-white px-4 py-2 text-sm font-medium text-stone-700">
                            <span>{{ Auth::user()->name }}</span>
                            <span class="text-stone-400">{{ Auth::user()->phone ?? Auth::user()->email }}</span>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">Profile</x-dropdown-link>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">
                                Log Out
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center rounded-md p-2 text-stone-500 hover:bg-stone-100 hover:text-stone-700">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <div :class="{'block': open, 'hidden': ! open}" class="hidden border-t border-stone-200 sm:hidden">
        <div class="space-y-1 px-4 py-4">
            <x-responsive-nav-link :href="route('customer.dashboard')" :active="request()->routeIs('customer.dashboard')">Dashboard</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('customer.consultations.create')" :active="request()->routeIs('customer.consultations.*')">Consultation</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('customer.subscriptions.create')" :active="request()->routeIs('customer.subscriptions.*')">Subscription</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('profile.edit')">Profile</x-responsive-nav-link>
            <form method="POST" action="{{ route('logout') }}" class="border-t border-stone-200 pt-4 mt-4">
                @csrf
                <x-responsive-nav-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">
                    Log Out
                </x-responsive-nav-link>
            </form>
        </div>
    </div>
</nav>
