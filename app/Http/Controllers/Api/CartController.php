<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private CartService $carts) {}
    public function show(Request $request): JsonResponse { return response()->json(['cart' => $this->carts->present($this->carts->forUser($request->user()))]); }
    public function add(Request $request): JsonResponse
    {
        $data = $request->validate(['meal_option_id' => 'required|integer|exists:meal_options,id', 'quantity' => 'required|integer|min:1|max:20', 'version' => 'required|integer|min:1']);
        return response()->json(['cart' => $this->carts->present($this->carts->add($request->user(), $data['meal_option_id'], $data['quantity'], $data['version']))], 201);
    }
    public function update(Request $request, int $item): JsonResponse
    {
        $data = $request->validate(['quantity' => 'sometimes|integer|min:1|max:20', 'delivery_date' => 'sometimes|nullable|date_format:Y-m-d', 'version' => 'required|integer|min:1']);
        $version = $data['version']; unset($data['version']);
        return response()->json(['cart' => $this->carts->present($this->carts->update($request->user(), $item, $data, $version))]);
    }
    public function remove(Request $request, int $item): JsonResponse
    {
        $data = $request->validate(['version' => 'required|integer|min:1']);
        return response()->json(['cart' => $this->carts->present($this->carts->remove($request->user(), $item, $data['version']))]);
    }
    public function clear(Request $request): JsonResponse
    {
        $data = $request->validate(['version' => 'required|integer|min:1']);
        return response()->json(['cart' => $this->carts->present($this->carts->clear($request->user(), $data['version']))]);
    }
}
