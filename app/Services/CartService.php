<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\MealOption;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartService
{
    public const MAX_LINE_QUANTITY = 20;
    public const MAX_LINES = 50;
    public const MAX_TOTAL_QUANTITY = 100;
    private const TIMEZONE = 'Asia/Kolkata';

    public function forUser(User $user): Cart
    {
        return Cart::firstOrCreate(['user_id' => $user->id], ['status' => 'active', 'currency' => 'INR', 'version' => 1]);
    }

    public function add(User $user, int $mealOptionId, int $quantity, int $version): Cart
    {
        return DB::transaction(function () use ($user, $mealOptionId, $quantity, $version) {
            $cart = $this->lockedCart($user, $version);
            $meal = MealOption::query()->where('is_active', true)->where('price', '>', 0)->findOrFail($mealOptionId);
            $key = $this->lineKey($meal->id, null);
            $item = $cart->items()->where('line_key', $key)->lockForUpdate()->first();
            $newQuantity = ($item?->quantity ?? 0) + $quantity;
            if ($newQuantity > self::MAX_LINE_QUANTITY) throw ValidationException::withMessages(['quantity' => 'A cart line cannot exceed 20 units.']);
            if (! $item && $cart->items()->count() >= self::MAX_LINES) throw ValidationException::withMessages(['cart' => 'A cart cannot contain more than 50 lines.']);
            if ($cart->items()->sum('quantity') + $quantity > self::MAX_TOTAL_QUANTITY) throw ValidationException::withMessages(['cart' => 'A cart cannot contain more than 100 total units.']);
            $item ? $item->update(['quantity' => $newQuantity]) : $cart->items()->create(['meal_option_id' => $meal->id, 'line_key' => $key, 'quantity' => $quantity]);
            return $this->bump($cart);
        });
    }

    public function update(User $user, int $itemId, array $values, int $version): Cart
    {
        return DB::transaction(function () use ($user, $itemId, $values, $version) {
            $cart = $this->lockedCart($user, $version);
            $item = $cart->items()->lockForUpdate()->findOrFail($itemId);
            $quantity = $values['quantity'] ?? $item->quantity;
            $date = array_key_exists('delivery_date', $values)
                ? ($values['delivery_date'] ? Carbon::parse($values['delivery_date'])->toDateString() : null)
                : $item->delivery_date?->toDateString();
            if ($date) $this->validateDeliveryDate($date);
            if ($quantity < 1 || $quantity > self::MAX_LINE_QUANTITY) throw ValidationException::withMessages(['quantity' => 'Quantity must be between 1 and 20.']);
            $key = $this->lineKey($item->meal_option_id, $date);
            $collision = $cart->items()->where('line_key', $key)->whereKeyNot($item->id)->lockForUpdate()->first();
            if ($collision) {
                $merged = $collision->quantity + $quantity;
                if ($merged > self::MAX_LINE_QUANTITY) throw ValidationException::withMessages(['quantity' => 'Merged quantity would exceed 20 units.']);
                $collision->update(['quantity' => $merged]);
                $item->delete();
            } else {
                $item->update(['quantity' => $quantity, 'delivery_date' => $date, 'line_key' => $key]);
            }
            if ($cart->items()->sum('quantity') > self::MAX_TOTAL_QUANTITY) throw ValidationException::withMessages(['cart' => 'A cart cannot contain more than 100 total units.']);
            return $this->bump($cart);
        });
    }

    public function remove(User $user, int $itemId, int $version): Cart
    {
        return DB::transaction(function () use ($user, $itemId, $version) {
            $cart = $this->lockedCart($user, $version);
            $cart->items()->findOrFail($itemId)->delete();
            return $this->bump($cart);
        });
    }

    public function clear(User $user, int $version): Cart
    {
        return DB::transaction(function () use ($user, $version) {
            $cart = $this->lockedCart($user, $version);
            $cart->items()->delete();
            return $this->bump($cart);
        });
    }

    public function present(Cart $cart): array
    {
        $cart->load(['items.mealOption']);
        $issues = [];
        $subtotalPaise = 0;
        $items = $cart->items->map(function (CartItem $item) use (&$issues, &$subtotalPaise) {
            $meal = $item->mealOption;
            $unitPaise = $meal ? (int) round(((float) $meal->price) * 100) : 0;
            $linePaise = $unitPaise * $item->quantity;
            $subtotalPaise += $linePaise;
            $itemIssues = [];
            if (! $meal || ! $meal->is_active || $unitPaise < 1) $itemIssues[] = 'Meal is unavailable.';
            if (! $item->delivery_date) $itemIssues[] = 'Delivery date is required.';
            if ($item->delivery_date && ! $this->deliveryDateIsValid($item->delivery_date->toDateString())) $itemIssues[] = 'Delivery date is outside the available window.';
            foreach ($itemIssues as $issue) $issues[] = ['item_id' => $item->id, 'message' => $issue];
            return [
                'id' => $item->id, 'meal_option_id' => $item->meal_option_id, 'name' => $meal?->title,
                'slug' => $meal?->slug, 'category' => $meal?->category_slug ?: $meal?->meal_type,
                'quantity' => $item->quantity, 'delivery_date' => $item->delivery_date?->toDateString(),
                'unit_price' => $unitPaise / 100, 'line_total' => $linePaise / 100, 'issues' => $itemIssues,
            ];
        })->values();
        [$minimum, $maximum] = $this->deliveryWindow();
        return [
            'id' => $cart->id, 'status' => $cart->status, 'currency' => $cart->currency, 'version' => $cart->version,
            'items' => $items, 'count' => $cart->items->sum('quantity'), 'subtotal' => $subtotalPaise / 100,
            'discount_total' => 0, 'tax_total' => 0, 'delivery_fee' => 0, 'grand_total' => $subtotalPaise / 100,
            'issues' => $issues, 'checkout_ready' => $items->isNotEmpty() && $issues === [] && $cart->status === 'active',
            'delivery_window' => ['minimum' => $minimum, 'maximum' => $maximum, 'timezone' => self::TIMEZONE],
        ];
    }

    public function validateDeliveryDate(string $date): void
    {
        if (! $this->deliveryDateIsValid($date)) {
            [$minimum, $maximum] = $this->deliveryWindow();
            throw ValidationException::withMessages(['delivery_date' => "Delivery date must be between {$minimum} and {$maximum}."]);
        }
    }

    public function deliveryWindow(): array
    {
        $now = Carbon::now(self::TIMEZONE);
        $cutoff = Setting::getValue('daily_meal_cutoff', '18:00');
        [$hour, $minute] = array_pad(array_map('intval', explode(':', $cutoff)), 2, 0);
        $minimum = $now->copy()->addDay();
        if ($now->greaterThanOrEqualTo($now->copy()->setTime($hour, $minute))) $minimum->addDay();
        $maximum = $now->copy()->addDays((int) Setting::getValue('meal_order_horizon_days', 30));
        return [$minimum->toDateString(), $maximum->toDateString()];
    }

    private function lockedCart(User $user, int $version): Cart
    {
        $this->forUser($user);
        $cart = Cart::query()->where('user_id', $user->id)->lockForUpdate()->firstOrFail();
        if ($cart->version !== $version) throw new HttpResponseException(response()->json(['message' => 'Cart changed in another request.', 'cart' => $this->present($cart)], 409));
        if ($cart->status !== 'active') throw new HttpResponseException(response()->json(['message' => 'Cart is currently locked for checkout.'], 423));
        return $cart;
    }

    private function bump(Cart $cart): Cart { $cart->increment('version'); return $cart->fresh(); }
    private function deliveryDateIsValid(string $date): bool { [$minimum, $maximum] = $this->deliveryWindow(); return $date >= $minimum && $date <= $maximum; }
    private function lineKey(int $mealOptionId, ?string $date): string { return hash('sha256', $mealOptionId.'|'.($date ?: 'unscheduled')); }
}
