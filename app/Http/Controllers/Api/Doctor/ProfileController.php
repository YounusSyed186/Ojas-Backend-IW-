<?php

namespace App\Http\Controllers\Api\Doctor;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $doctor = $request->user();

        return response()->json([
            'profile' => $doctor->makeHidden(['password', 'remember_token']),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $doctor = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'specialization' => 'sometimes|string|max:255',
            'experience' => 'sometimes|integer|min:0',
            'bio' => 'sometimes|string',
            'focus_areas' => 'sometimes|array',
            'focus_areas.*' => 'string',
            'address_line_1' => 'sometimes|string|max:255',
            'address_line_2' => 'sometimes|string|max:255',
            'city' => 'sometimes|string|max:100',
            'state' => 'sometimes|string|max:100',
            'pincode' => 'sometimes|string|max:20',
        ]);

        $doctor->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully.',
            'profile' => $doctor->fresh()->makeHidden(['password', 'remember_token']),
        ]);
    }
}