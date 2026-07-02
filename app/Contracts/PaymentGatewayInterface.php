<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Model;

interface PaymentGatewayInterface
{
    public function charge(Model $payable, float $amount, string $currency = 'INR', array $payload = []): void;
}
