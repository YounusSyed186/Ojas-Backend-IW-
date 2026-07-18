<?php

namespace App\Http\Controllers\Api\Doctor;

use App\Http\Controllers\Controller;
use App\Models\Consultation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $doctor = $request->user();
        $page = max((int) $request->query('page', 1), 1);
        $perPage = min(max((int) $request->query('per_page', 10), 1), 100);

        $notes = Consultation::with('customer:id,name')
            ->where('doctor_id', $doctor->id)
            ->whereNotNull('doctor_notes')
            ->where('doctor_notes', '!=', '')
            ->latest()
            ->paginate($perPage, ['id', 'doctor_notes', 'created_at', 'user_id'], 'page', $page);

        return response()->json(['notes' => $notes]);
    }
}
