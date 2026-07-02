<x-guest-layout>
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <div class="space-y-6">
        <form method="POST" action="{{ route('phone-login.send') }}" class="space-y-4">
            @csrf
            <div>
                <x-input-label for="phone" :value="__('Phone')" />
                <x-text-input id="phone" class="mt-1 block w-full" type="text" name="phone" :value="old('phone')" required autofocus />
                <x-input-error :messages="$errors->get('phone')" class="mt-2" />
            </div>
            <x-primary-button>{{ __('Send OTP') }}</x-primary-button>
        </form>

        <form method="POST" action="{{ route('phone-login.verify') }}" class="space-y-4 border-t border-stone-200 pt-6">
            @csrf
            <div>
                <x-input-label for="verify_phone" :value="__('Phone')" />
                <x-text-input id="verify_phone" class="mt-1 block w-full" type="text" name="phone" :value="old('phone')" required />
            </div>
            <div>
                <x-input-label for="name" :value="__('Name (for first login)')" />
                <x-text-input id="name" class="mt-1 block w-full" type="text" name="name" :value="old('name')" />
            </div>
            <div>
                <x-input-label for="code" :value="__('OTP Code')" />
                <x-text-input id="code" class="mt-1 block w-full" type="text" name="code" required />
                <x-input-error :messages="$errors->get('code')" class="mt-2" />
                <p class="mt-2 text-sm text-stone-500">Use <strong>123456</strong> for local development.</p>
            </div>
            <div class="flex items-center justify-between">
                <a class="text-sm text-stone-600 underline" href="{{ route('login') }}">Back to password login</a>
                <x-primary-button>{{ __('Verify and Continue') }}</x-primary-button>
            </div>
        </form>
    </div>
</x-guest-layout>
