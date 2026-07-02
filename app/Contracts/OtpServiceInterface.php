<?php

namespace App\Contracts;

use App\Models\OtpCode;

interface OtpServiceInterface
{
    public function send(string $phone): OtpCode;

    public function verify(string $phone, string $code): bool;
}
