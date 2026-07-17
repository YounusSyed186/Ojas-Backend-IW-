<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private OrderCheckoutService $checkout) {}
    public function checkout(Request $request): JsonResponse
    {
        $data = $request->validate(['version' => 'required|integer|min:1', 'delivery_address_line_1' => 'required|string|max:255',
            'delivery_address_line_2' => 'nullable|string|max:255', 'delivery_city' => 'required|string|max:120',
            'delivery_state' => 'required|string|max:120', 'delivery_pincode' => 'required|string|size:6']);
        $version = $data['version']; unset($data['version']);
        return response()->json($this->checkout->checkout($request->user(), $data, $version), 201);
    }
    public function verify(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate(['razorpay_order_id' => 'required|string', 'razorpay_payment_id' => 'required|string', 'razorpay_signature' => 'required|string']);
        $order = $this->checkout->verify($request->user(), $order, $data);
        return response()->json(['message' => 'Payment verified.', 'order' => $this->checkout->present($order)]);
    }
    public function abandon(Request $request, Order $order): JsonResponse
    {
        $order = $this->checkout->abandon($request->user(), $order);
        return response()->json(['message' => 'Checkout abandoned and cart restored.', 'order' => $this->checkout->present($order)]);
    }
    public function index(Request $request): JsonResponse
    {
        return response()->json(['orders' => Order::with('items')->where('user_id', $request->user()->id)->latest()->paginate(min((int) $request->input('per_page', 20), 50))]);
    }
    public function show(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404);
        return response()->json(['order' => $this->checkout->present($order)]);
    }
}
