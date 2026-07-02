<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PincodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PincodeController extends Controller
{
    public function __construct(
        private PincodeService $pincodeService
    ) {}

    /**
     * Validate a pincode for delivery serviceability
     */
    public function validate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pincode' => 'required|string|size:6',
        ]);

        $result = $this->pincodeService->validateUserPincode($validated['pincode']);

        return response()->json([
            'is_valid' => $result['is_valid'],
            'message' => $result['message'],
            'pincode' => $validated['pincode'],
        ]);
    }

    /**
     * Get all serviceable pincodes
     */
    public function serviceable(): JsonResponse
    {
        $pincodes = $this->pincodeService->getServiceablePincodes();

        return response()->json([
            'pincodes' => $pincodes,
        ]);
    }
}
