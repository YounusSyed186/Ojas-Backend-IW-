<?php

namespace App\Jobs;

use App\Models\PaymentWebhookEvent;
use App\Services\RazorpayWebhookService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessRazorpayWebhook implements ShouldQueue
{
    use Queueable;
    public function __construct(public int $eventId) {}
    public function handle(RazorpayWebhookService $service): void { $service->process(PaymentWebhookEvent::findOrFail($this->eventId)); }
}
