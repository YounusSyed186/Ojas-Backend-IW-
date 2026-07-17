<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessRazorpayWebhook;
use App\Models\PaymentWebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RazorpayWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $raw = $request->getContent();
        $signature = (string) $request->header('X-Razorpay-Signature');
        $secret = (string) config('services.razorpay.webhook_secret');
        if (! $secret || ! $signature || ! hash_equals(hash_hmac('sha256', $raw, $secret), $signature)) return response()->json(['message' => 'Invalid webhook signature.'], 401);
        $payload = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        $event = PaymentWebhookEvent::firstOrCreate(
            ['event_id' => (string) ($request->header('X-Razorpay-Event-Id') ?: hash('sha256', $raw))],
            ['gateway' => 'razorpay', 'event_type' => $payload['event'] ?? 'unknown', 'payload' => $payload],
        );
        if ($event->wasRecentlyCreated) ProcessRazorpayWebhook::dispatch($event->id);
        return response()->json(['received' => true]);
    }
}
