<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Contracts\OtpServiceInterface;
use App\Models\OtpCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'required|string|max:20|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'password' => Hash::make($validated['password']),
            'role' => 'customer',
            'status' => 'active',
            'phone_verified_at' => null,
        ]);

        $customerRole = Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
        $user->assignRole($customerRole);

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Account created successfully. Please verify your phone.',
            'user' => $user,
            'token' => $token,
            'requires_phone_verification' => true,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required_without:phone|string|email',
            'phone' => 'required_without:email|string',
            'password' => 'required|string',
        ]);

        $loginField = isset($validated['phone']) ? 'phone' : 'email';
        $credentials = [
            $loginField => $validated[$loginField],
            'password' => $validated['password'],
        ];

        if (!Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                $loginField => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = Auth::user();
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Logged in successfully.',
            'user' => $user,
            'token' => $token,
            'requires_phone_verification' => is_null($user->phone_verified_at),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if ($token && method_exists($token, 'delete')) {
            $token->delete();
        }

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user()->load(['subscriptions.plan', 'consultations']),
        ]);
    }

    public function sendOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => 'required|string|max:20',
        ]);

        $phone = $this->normalizePhone($validated['phone']);

        if ($this->isDemoOtpEnabled() && $this->isDemoOtpPhone($phone)) {
            // Create an OtpCode record for audit/debug purposes
            OtpCode::create([
                'phone' => $phone,
                'code' => config('services.demo_otp.code'),
                'expires_at' => now()->addMinutes(10),
            ]);

            return response()->json([
                'message' => 'Demo OTP available. Use the configured test code.',
            ]);
        }

        $otpService = app(OtpServiceInterface::class);
        $otpService->send($phone);

        return response()->json([
            'message' => 'OTP sent successfully.',
        ]);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => 'required|string|max:20',
            'otp' => 'required|string|size:6',
        ]);

        $phone = $this->normalizePhone($validated['phone']);

        // Find user by phone first
        $user = User::where('phone', $phone)->first();

        if (! $user) {
            return response()->json([
                'message' => 'No account found with this phone number. Please create an account first.',
            ], 422);
        }

        // Verify OTP
        $otpValid = false;

        if ($this->isDemoOtpEnabled() && $this->isDemoOtpPhone($phone)) {
            $otpValid = $validated['otp'] === config('services.demo_otp.code');
        } else {
            $otpService = app(OtpServiceInterface::class);
            $otpValid = $otpService->verify($phone, $validated['otp']);
        }

        if (!$otpValid) {
            return response()->json([
                'message' => 'Invalid or expired OTP.',
            ], 422);
        }

        // Update phone_verified_at
        $user->update(['phone_verified_at' => now()]);

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'OTP verified successfully.',
            'user' => $user,
            'token' => $token,
        ]);
    }

    /**
     * Strip all non-digit characters from a phone number.
     */
    private function normalizePhone(string $phone): string
    {
        return preg_replace('/[^0-9]/', '', $phone);
    }

    /**
     * Check if demo OTP mode is enabled and safe to use.
     * Demo OTP is never allowed in the production environment.
     */
    private function isDemoOtpEnabled(): bool
    {
        if (app()->environment('production')) {
            return false;
        }

        return (bool) config('services.demo_otp.enabled', false);
    }

    /**
     * Check if the given phone number is in the demo phones list.
     * Comparison is done after normalizing both the input and configured phones.
     */
    private function isDemoOtpPhone(string $phone): bool
    {
        $normalizedPhone = $this->normalizePhone($phone);
        $demoPhones = config('services.demo_otp.phones', []);

        foreach ($demoPhones as $demoPhone) {
            if ($this->normalizePhone($demoPhone) === $normalizedPhone) {
                return true;
            }
        }

        return false;
    }
}