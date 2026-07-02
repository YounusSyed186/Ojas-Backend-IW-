<?php

namespace App\Services;

use App\Contracts\OtpServiceInterface;
use App\Models\OtpCode;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class Msg91OtpService implements OtpServiceInterface
{
    public function send(string $phone): OtpCode
    {
        $this->guardResendLimit($phone);

        $otp = OtpCode::create([
            'phone' => $phone,
            'code' => $this->generateCode(),
            'expires_at' => now()->addMinutes((int) config('services.msg91.otp_expiry_minutes', 10)),
            'send_attempts' => $this->attemptsInWindow($phone) + 1,
            'last_sent_at' => now(),
            'provider' => 'msg91',
        ]);

        try {
            $this->sendViaMsg91($phone, $otp->code);
        } catch (ValidationException $exception) {
            $otp->delete();
            throw $exception;
        }

        return $otp;
    }

    public function verify(string $phone, string $code): bool
    {
        $otp = OtpCode::query()
            ->where('phone', $phone)
            ->where('code', $code)
            ->whereNull('verified_at')
            ->where('expires_at', '>=', Carbon::now())
            ->latest()
            ->first();

        if (!$otp) {
            return false;
        }

        if ($this->shouldVerifyWithMsg91()) {
            $this->verifyViaMsg91($phone, $code);
        }

        $otp->update([
            'verified_at' => now(),
        ]);

        return true;
    }

    private function sendViaMsg91(string $phone, string $code): void
    {
        $authKey = config('services.msg91.auth_key');
        $templateId = config('services.msg91.otp_template_id');

        if (!$authKey || !$templateId) {
            throw ValidationException::withMessages([
                'phone' => 'MSG91 OTP credentials are not configured.',
            ]);
        }

        try {
            $response = Http::acceptJson()
                ->withHeaders(['authkey' => $authKey])
                ->get('https://control.msg91.com/api/v5/otp', [
                    'template_id' => $templateId,
                    'mobile' => $this->normalizePhone($phone),
                    'otp' => $code,
                    'otp_expiry' => (int) config('services.msg91.otp_expiry_minutes', 10),
                ])
                ->throw();

            if ($this->isMsg91Error($response->json())) {
                throw ValidationException::withMessages([
                    'phone' => $response->json('message') ?? 'MSG91 failed to send OTP.',
                ]);
            }
        } catch (RequestException $exception) {
            throw ValidationException::withMessages([
                'phone' => $exception->response?->json('message') ?? 'MSG91 failed to send OTP.',
            ]);
        }
    }

    private function verifyViaMsg91(string $phone, string $code): void
    {
        $authKey = config('services.msg91.auth_key');

        if (!$authKey) {
            throw ValidationException::withMessages([
                'otp' => 'MSG91 OTP credentials are not configured.',
            ]);
        }

        try {
            $response = Http::acceptJson()
                ->withHeaders(['authkey' => $authKey])
                ->get('https://control.msg91.com/api/v5/otp/verify', [
                    'mobile' => $this->normalizePhone($phone),
                    'otp' => $code,
                ])
                ->throw();

            if ($this->isMsg91Error($response->json())) {
                throw ValidationException::withMessages([
                    'otp' => $response->json('message') ?? 'MSG91 failed to verify OTP.',
                ]);
            }
        } catch (RequestException $exception) {
            throw ValidationException::withMessages([
                'otp' => $exception->response?->json('message') ?? 'MSG91 failed to verify OTP.',
            ]);
        }
    }

    private function guardResendLimit(string $phone): void
    {
        $windowMinutes = (int) config('services.msg91.resend_window_minutes', 30);
        $maxAttempts = (int) config('services.msg91.max_resend_attempts', 3);

        if ($this->attemptsInWindow($phone, $windowMinutes) >= $maxAttempts) {
            throw ValidationException::withMessages([
                'phone' => "Too many OTP requests. Please try again after {$windowMinutes} minutes.",
            ]);
        }
    }

    private function attemptsInWindow(string $phone, ?int $windowMinutes = null): int
    {
        $windowMinutes ??= (int) config('services.msg91.resend_window_minutes', 30);

        return OtpCode::query()
            ->where('phone', $phone)
            ->where('created_at', '>=', now()->subMinutes($windowMinutes))
            ->count();
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        $countryCode = preg_replace('/\D+/', '', (string) config('services.msg91.default_country_code', '91')) ?: '91';

        if (str_starts_with($digits, $countryCode)) {
            return $digits;
        }

        return $countryCode . $digits;
    }

    private function generateCode(): string
    {
        return (string) random_int(100000, 999999);
    }

    private function shouldVerifyWithMsg91(): bool
    {
        return (bool) config('services.msg91.verify_with_provider', false);
    }

    private function isMsg91Error(mixed $payload): bool
    {
        if (!is_array($payload)) {
            return false;
        }

        $type = strtolower((string) ($payload['type'] ?? ''));

        return in_array($type, ['error', 'failure', 'failed'], true);
    }
}
