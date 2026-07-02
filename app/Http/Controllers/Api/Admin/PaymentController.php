<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Concerns\AppliesListQuery;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    use AppliesListQuery;

    public function index(Request $request): JsonResponse
    {
        $payments = $this->applyListQuery(
            Payment::query()->with('payable'),
            $request,
            ['reference', 'gateway', 'status'],
            ['status', 'gateway'],
        );

        return response()->json(['payments' => $payments]);
    }
}
