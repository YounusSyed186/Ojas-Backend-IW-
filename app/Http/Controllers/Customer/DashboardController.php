<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Consultation;
use App\Models\Subscription;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $user = auth()->user();

        $activeSubscription = Subscription::query()
            ->with(['template', 'preferences.mealOption', 'dailySelections.mealOption'])
            ->where('user_id', $user->id)
            ->whereIn('status', ['active', 'pending'])
            ->latest()
            ->first();

        $consultations = Consultation::query()
            ->with(['doctor', 'payment'])
            ->where('user_id', $user->id)
            ->latest()
            ->take(5)
            ->get();

        $payments = collect([
            optional($activeSubscription)->payment,
            ...$consultations->pluck('payment'),
        ])->filter();

        return view('customer.dashboard', compact('activeSubscription', 'consultations', 'payments'));
    }
}
