<?php

namespace App\Providers;

use App\Contracts\OtpServiceInterface;
use App\Contracts\PaymentGatewayInterface;
use App\Services\DummyPaymentGateway;
use App\Services\Msg91OtpService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(OtpServiceInterface::class, Msg91OtpService::class);
        $this->app->singleton(PaymentGatewayInterface::class, DummyPaymentGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
