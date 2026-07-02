<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'settings' => Setting::orderBy('key')->get(),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'value' => 'required|string|max:1000',
        ]);

        $setting = Setting::findOrFail($id);
        $setting->update($validated);

        return response()->json([
            'message' => 'Setting updated successfully.',
            'setting' => $setting->fresh(),
        ]);
    }
}
