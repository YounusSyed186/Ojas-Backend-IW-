<?php

namespace App\Http\Controllers\Auth;

use App\Contracts\OtpServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PhoneAuthController extends Controller
{
    public function create(): View
    {
        return view('auth.phone-login');
    }

    public function send(Request $request, OtpServiceInterface $otpService): RedirectResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
        ]);

        $otpService->send($validated['phone']);

        return back()->with('status', 'OTP sent. Use the test code to continue in local development.');
    }

    public function verify(Request $request, OtpServiceInterface $otpService): RedirectResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'name' => ['nullable', 'string', 'max:255'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        if (! $otpService->verify($validated['phone'], $validated['code'])) {
            return back()->withErrors([
                'code' => 'The OTP code is invalid or expired.',
            ])->withInput();
        }

        $user = User::firstOrCreate(
            ['phone' => $validated['phone']],
            [
                'name' => $validated['name'] ?: 'Ojas Customer',
                'role' => 'customer',
                'phone_verified_at' => now(),
            ],
        );

        if (! $user->phone_verified_at) {
            $user->forceFill(['phone_verified_at' => now()])->save();
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->route('customer.dashboard');
    }
}
