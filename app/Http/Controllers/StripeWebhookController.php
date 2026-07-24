<?php

namespace App\Http\Controllers;

use App\Services\Billing\StripeService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $payload = $request->getContent();
        $sig = $request->header('Stripe-Signature');
        $secret = StripeService::webhookSecret();

        if (! $secret || ! class_exists(\Stripe\Webhook::class)) {
            return response('Stripe webhook not configured', 503);
        }

        try {
            $event = \Stripe\Webhook::constructEvent($payload, (string) $sig, $secret);
        } catch (\Throwable $e) {
            Log::warning('Stripe webhook signature failed', ['error' => $e->getMessage()]);

            return response('Invalid signature', 400);
        }

        Log::info('Stripe webhook received', ['type' => $event->type ?? null]);

        return response('ok', 200);
    }
}
