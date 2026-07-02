<?php

namespace App\Http\Controllers\Api\Doctor;

use App\Http\Controllers\Controller;
use App\Models\Consultation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $doctor = $request->user();
        $view = $request->query('view', 'today');
        $page = max((int) $request->query('page', 1), 1);
        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);

        $query = Consultation::with(['customer:id,name,phone'])
            ->where('doctor_id', $doctor->id)
            ->whereIn('status', ['scheduled', 'requested', 'pending']);

        if ($view === 'today') {
            $query->whereDate('scheduled_for', today());
        } elseif ($view === 'weekly') {
            $query->whereBetween('scheduled_for', [now()->startOfWeek(), now()->endOfWeek()]);
        } elseif ($view === 'monthly') {
            $query->whereMonth('scheduled_for', now()->month)
                  ->whereYear('scheduled_for', now()->year);
        }

        $appointments = $query->orderBy('scheduled_for')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json(['appointments' => $appointments]);
    }
}