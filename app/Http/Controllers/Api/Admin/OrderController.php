<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\OrderCheckoutService;
use App\Services\OrderRefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function __construct(private OrderCheckoutService $checkout, private OrderRefundService $refunds) {}
    public function index(Request $request): JsonResponse
    {
        $query = Order::with(['user:id,name,phone', 'items']);
        if ($request->filled('status')) $query->where('status', $request->string('status'));
        if ($request->filled('payment_status')) $query->where('payment_status', $request->string('payment_status'));
        if ($request->filled('delivery_date')) $query->whereHas('items', fn ($q) => $q->whereDate('delivery_date', $request->date('delivery_date')));
        if ($request->filled('search')) {
            $term = '%'.$request->string('search').'%';
            $query->where(fn ($q) => $q->where('order_number', 'like', $term)->orWhere('customer_phone', 'like', $term));
        }
        return response()->json(['orders' => $query->latest()->paginate(min((int) $request->input('per_page', 20), 100))]);
    }
    public function show(Order $order): JsonResponse { return response()->json(['order' => $this->checkout->present($order)]); }
    public function updateItem(Request $request, Order $order, OrderItem $item): JsonResponse
    {
        abort_unless($item->order_id === $order->id, 404);
        $data = $request->validate(['fulfillment_status' => ['required', Rule::in(['confirmed', 'preparing', 'out_for_delivery', 'delivered'])]]);
        $allowed = ['confirmed' => ['preparing'], 'preparing' => ['out_for_delivery'], 'out_for_delivery' => ['delivered']];
        if (! in_array($data['fulfillment_status'], $allowed[$item->fulfillment_status] ?? [], true)) throw ValidationException::withMessages(['fulfillment_status' => 'Invalid fulfillment transition.']);
        $item->update($data);
        if ($order->items()->whereNotIn('fulfillment_status', ['delivered', 'cancelled'])->doesntExist()) $order->update(['status' => 'completed']);
        elseif ($data['fulfillment_status'] !== 'confirmed') $order->update(['status' => 'fulfilling']);
        return response()->json(['order' => $this->checkout->present($order->fresh())]);
    }
    public function cancel(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate(['order_item_id' => 'nullable|integer', 'reason' => 'required|string|max:255']);
        $item = isset($data['order_item_id']) ? $order->items()->findOrFail($data['order_item_id']) : null;
        $refund = $this->refunds->refund($order, $item, $request->user(), $data['reason']);
        return response()->json(['message' => 'Cancellation recorded and refund initiated.', 'refund' => $refund]);
    }
}
