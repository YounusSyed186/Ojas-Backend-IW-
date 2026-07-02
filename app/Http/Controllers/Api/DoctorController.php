<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DoctorController extends Controller
{
    public function index(): JsonResponse
    {
        $doctors = User::query()
            ->where('role', 'doctor')
            ->where('status', 'active')
            ->whereNotNull('slug')
            ->orderBy('name')
            ->get()
            ->map(fn (User $doctor) => $this->formatDoctorProfile($doctor));

        return response()->json([
            'doctors' => $doctors,
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $doctor = User::query()
            ->where('role', 'doctor')
            ->where('status', 'active')
            ->where('slug', $slug)
            ->first();

        if (! $doctor) {
            return response()->json(['message' => 'Doctor not found.'], 404);
        }

        return response()->json([
            'doctor' => $this->formatDoctorProfile($doctor),
        ]);
    }

    private function formatDoctorProfile(User $doctor): array
    {
        return [
            'id' => $doctor->id,
            'slug' => $doctor->slug,
            'name' => $doctor->name,
            'spec' => $doctor->specialization ?? 'Nutritionist',
            'exp' => $doctor->experience ?? '10 yrs',
            'rating' => (float) ($doctor->rating ?? 4.8),
            'bio' => $doctor->bio ?? '',
            'focus' => $doctor->focus_areas ?? [],
        ];
    }
}
