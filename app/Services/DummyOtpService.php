<?php

namespace App\Services;

use App\Contracts\OtpServiceInterface;
use App\Models\OtpCode;
use Illuminate\Support\Carbon;

class DummyOtpService implements OtpServiceInterface
{
    public function send(string $phone): OtpCode
    {
        OtpCode::query()->where('phone', $phone)->delete();

        return OtpCode::create([
            'phone' => $phone,
            'code' => '123456',
            'expires_at' => now()->addMinutes(10),
        ]);
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

        if (! $otp) {
            return false;
        }

        $otp->update([
            'verified_at' => now(),
        ]);

        return true;
    }
}
