<?php

namespace App\Http\Controllers;

use App\Models\ConsultationFee;
use App\Models\MealPlanTemplate;
use App\Models\ServiceablePincode;
use Illuminate\View\View;

class LandingPageController extends Controller
{
    public function __invoke(): View
    {
        return view('welcome', [
            'consultationFee' => ConsultationFee::query()->where('is_active', true)->latest()->first(),
            'plans' => MealPlanTemplate::query()->with('mealOptions')->where('is_active', true)->take(3)->get(),
            'serviceableCount' => ServiceablePincode::query()->where('is_active', true)->count(),
        ]);
    }
}
