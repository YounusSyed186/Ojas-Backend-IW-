<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DummyPaymentGateway implements PaymentGatewayInterface
{
    public function charge(Model $payable, float $amount, string $currency = 'INR', array $payload = []): void
    {
        $payable->payment()->create([
            'gateway' => 'dummy',
            'reference' => 'DUMMY-'.Str::upper(Str::random(12)),
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'paid',
            'payload' => array_merge($payload, [
                'note' => 'Dummy payment gateway approved this transaction.',
            ]),
            'paid_at' => now(),
        ]);
    }
}
