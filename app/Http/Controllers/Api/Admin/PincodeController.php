<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Concerns\AppliesListQuery;
use App\Http\Controllers\Controller;
use App\Models\ServiceablePincode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PincodeController extends Controller
{
    use AppliesListQuery;

    public function index(Request $request): JsonResponse
    {
        $pincodes = $this->applyListQuery(
            ServiceablePincode::query(),
            $request,
            ['pincode', 'label'],
            ['is_active'],
        );

        return response()->json(['pincodes' => $pincodes]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pincode' => 'required|string|size:6|unique:serviceable_pincodes,pincode',
            'label' => 'required|string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        $pincode = ServiceablePincode::create($validated);

        return response()->json([
            'message' => 'Pincode created successfully.',
            'pincode' => $pincode,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'label' => 'sometimes|string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        $pincode = ServiceablePincode::findOrFail($id);
        $pincode->update($validated);

        return response()->json([
            'message' => 'Pincode updated successfully.',
            'pincode' => $pincode->fresh(),
        ]);
    }
}
