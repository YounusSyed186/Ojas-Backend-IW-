<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class AdminDoctorController extends Controller
{
    /**
     * List all doctors with performance stats
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::where('role', 'doctor');

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('specialization')) {
            $query->where('specialization', $request->query('specialization'));
        }

        $perPage = $request->integer('per_page', 20);
        $doctors = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Attach performance stats
        $doctors->getCollection()->transform(function ($doctor) {
            $doctor->total_patients = $doctor->assignedConsultations()
                ->whereNotNull('doctor_id')
                ->distinct('user_id')
                ->count('user_id');

            $doctor->completed_consultations = $doctor->assignedConsultations()
                ->where('status', 'completed')
                ->count();

            return $doctor;
        });

        return response()->json([
            'doctors' => $doctors,
        ]);
    }

    /**
     * Create a new doctor account
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'phone' => 'required|string|max:20|unique:users,phone',
            'password' => 'required|string|min:8',
            'specialization' => 'nullable|string|max:255',
            'qualification' => 'nullable|string|max:255',
            'experience' => 'nullable|string|max:50',
            'bio' => 'nullable|string',
            'status' => 'sometimes|string|in:active,inactive,suspended',
        ]);

        $validated['role'] = 'doctor';
        $validated['password'] = Hash::make($validated['password']);
        $validated['status'] = $validated['status'] ?? 'active';

        $user = User::create($validated);

        // Assign Spatie role
        $role = Role::firstOrCreate(['name' => 'doctor', 'guard_name' => 'web']);
        $user->assignRole($role);

        return response()->json([
            'message' => 'Doctor account created successfully.',
            'doctor' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'specialization' => $user->specialization,
                'status' => $user->status,
            ],
            'credentials' => [
                'email' => $user->email,
                'password' => $request->input('password'), // Return plain text only on creation
            ],
        ], 201);
    }

    /**
     * Get doctor details with stats
     */
    public function show(int $id): JsonResponse
    {
        $doctor = User::where('role', 'doctor')->findOrFail($id);

        $consultations = $doctor->assignedConsultations()
            ->with(['customer', 'payment', 'fee'])
            ->latest()
            ->paginate(20);

        $stats = [
            'total_consultations' => $doctor->assignedConsultations()->count(),
            'completed_consultations' => $doctor->assignedConsultations()->where('status', 'completed')->count(),
            'pending_consultations' => $doctor->assignedConsultations()->where('status', 'pending')->count(),
            'total_patients' => $doctor->assignedConsultations()
                ->whereNotNull('doctor_id')
                ->distinct('user_id')
                ->count('user_id'),
            'active_subscriptions' => \App\Models\Subscription::whereHas('consultation', function ($q) use ($doctor) {
                $q->where('doctor_id', $doctor->id);
            })->where('status', 'active')->count(),
        ];

        return response()->json([
            'doctor' => $doctor,
            'consultations' => $consultations,
            'stats' => $stats,
        ]);
    }

    /**
     * Update doctor profile
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $doctor = User::where('role', 'doctor')->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20|unique:users,phone,' . $id,
            'specialization' => 'sometimes|nullable|string|max:255',
            'qualification' => 'sometimes|nullable|string|max:255',
            'experience' => 'sometimes|nullable|string|max:50',
            'bio' => 'sometimes|nullable|string',
            'status' => 'sometimes|string|in:active,inactive,suspended',
        ]);

        $doctor->update($validated);

        return response()->json([
            'message' => 'Doctor updated successfully.',
            'doctor' => $doctor->fresh(),
        ]);
    }

    /**
     * Reset doctor password
     */
    public function resetPassword(Request $request, int $id): JsonResponse
    {
        $doctor = User::where('role', 'doctor')->findOrFail($id);

        $validated = $request->validate([
            'password' => 'required|string|min:8',
        ]);

        $doctor->update([
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json([
            'message' => 'Password reset successfully.',
            'credentials' => [
                'email' => $doctor->email,
                'password' => $validated['password'], // Return plain text only on reset
            ],
        ]);
    }

    /**
     * Toggle doctor status (activate/deactivate/suspend)
     */
    public function toggleStatus(Request $request, int $id): JsonResponse
    {
        $doctor = User::where('role', 'doctor')->findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|string|in:active,inactive,suspended',
        ]);

        $doctor->update(['status' => $validated['status']]);

        return response()->json([
            'message' => 'Doctor status updated to ' . $validated['status'] . '.',
            'doctor' => $doctor->fresh(),
        ]);
    }
}