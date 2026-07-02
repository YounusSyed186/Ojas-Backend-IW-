<?php

use App\Models\ServiceablePincode;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component
{
    #[Validate('required|string|max:10')]
    public string $pincode = '';

    public ?bool $isAvailable = null;

    public function check(): void
    {
        $this->validate();

        $this->isAvailable = ServiceablePincode::query()
            ->where('pincode', $this->pincode)
            ->where('is_active', true)
            ->exists();
    }
};
?>

<div class="space-y-4">
    <div class="flex gap-3">
        <input wire:model="pincode" type="text" placeholder="Enter pincode" class="w-full rounded-full border border-white/20 bg-white/10 px-5 py-3 text-white placeholder:text-stone-300 focus:border-amber-300 focus:ring-amber-300">
        <button wire:click="check" type="button" class="rounded-full bg-amber-400 px-5 py-3 font-semibold text-stone-950">Check</button>
    </div>

    @error('pincode')
        <p class="text-sm text-rose-300">{{ $message }}</p>
    @enderror

    @if ($isAvailable === true)
        <div class="rounded-3xl bg-emerald-500/15 px-5 py-4 text-sm text-emerald-200 ring-1 ring-emerald-400/30">
            Great, this pincode is serviceable.
        </div>
    @elseif ($isAvailable === false)
        <div class="rounded-3xl bg-rose-500/15 px-5 py-4 text-sm text-rose-200 ring-1 ring-rose-400/30">
            This pincode is not active yet. Please try another area.
        </div>
    @endif
</div>
