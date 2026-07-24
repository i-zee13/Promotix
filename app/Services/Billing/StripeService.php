<?php

namespace App\Services\Billing;

use App\Models\AdminIntegrationSetting;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class StripeService
{
    public static function isConfigured(): bool
    {
        return self::secretKey() !== null && self::publishableKey() !== null;
    }

    public static function publishableKey(): ?string
    {
        $fromEnv = trim((string) config('services.stripe.key', ''));
        if ($fromEnv !== '') {
            return $fromEnv;
        }

        $row = self::platformIntegration();
        $key = trim((string) data_get($row?->settings, 'publishable_key', ''));

        return $key !== '' ? $key : null;
    }

    public static function secretKey(): ?string
    {
        $fromEnv = trim((string) config('services.stripe.secret', ''));
        if ($fromEnv !== '') {
            return $fromEnv;
        }

        $row = self::platformIntegration();
        if (! $row) {
            return null;
        }

        $payload = $row->secret_payload;
        if (is_string($payload) && $payload !== '') {
            try {
                $decoded = json_decode(Crypt::decryptString($payload), true);
                if (is_array($decoded) && ! empty($decoded['secret_key'])) {
                    return (string) $decoded['secret_key'];
                }
            } catch (\Throwable) {
                // fall through
            }
        }

        $plain = trim((string) data_get($row->settings, 'secret_key', ''));

        return $plain !== '' ? $plain : null;
    }

    public static function webhookSecret(): ?string
    {
        $fromEnv = trim((string) config('services.stripe.webhook_secret', ''));
        if ($fromEnv !== '') {
            return $fromEnv;
        }

        $row = self::platformIntegration();
        if (! $row) {
            return null;
        }

        $payload = $row->secret_payload;
        if (is_string($payload) && $payload !== '') {
            try {
                $decoded = json_decode(Crypt::decryptString($payload), true);
                if (is_array($decoded) && ! empty($decoded['webhook_secret'])) {
                    return (string) $decoded['webhook_secret'];
                }
            } catch (\Throwable) {
                // fall through
            }
        }

        $plain = trim((string) data_get($row->settings, 'webhook_secret', ''));

        return $plain !== '' ? $plain : null;
    }

    public static function verifyAmountCents(): int
    {
        return max(50, min(500, (int) config('services.stripe.verify_amount_cents', 100)));
    }

    public static function client(): ?\Stripe\StripeClient
    {
        $secret = self::secretKey();
        if (! $secret || ! class_exists(\Stripe\StripeClient::class)) {
            return null;
        }

        return new \Stripe\StripeClient($secret);
    }

    public static function ensureCustomer(User $user): ?string
    {
        $client = self::client();
        if (! $client) {
            return null;
        }

        if ($user->stripe_customer_id) {
            return $user->stripe_customer_id;
        }

        $customer = $client->customers->create([
            'email' => $user->email,
            'name' => $user->name,
            'metadata' => ['user_id' => (string) $user->id],
        ]);

        $user->forceFill(['stripe_customer_id' => $customer->id])->save();

        return $customer->id;
    }

    /**
     * @return array{client_secret: string, setup_intent_id: string}|null
     */
    public static function createSetupIntent(User $user): ?array
    {
        $client = self::client();
        $customerId = self::ensureCustomer($user);
        if (! $client || ! $customerId) {
            return null;
        }

        $intent = $client->setupIntents->create([
            'customer' => $customerId,
            'usage' => 'off_session',
            'payment_method_types' => ['card'],
            'metadata' => ['user_id' => (string) $user->id],
        ]);

        return [
            'client_secret' => (string) $intent->client_secret,
            'setup_intent_id' => (string) $intent->id,
        ];
    }

    /**
     * Charge a small amount then refund immediately to prove the card works.
     *
     * @return array{ok: bool, brand: string, last_four: string, payment_method_id: string, charge_id: ?string, message: ?string}
     */
    public static function attachAndVerify(User $user, string $setupIntentId): array
    {
        $client = self::client();
        if (! $client) {
            return ['ok' => false, 'brand' => 'Card', 'last_four' => '0000', 'payment_method_id' => '', 'charge_id' => null, 'message' => 'Stripe is not configured.'];
        }

        try {
            $intent = $client->setupIntents->retrieve($setupIntentId, []);
            if (($intent->status ?? '') !== 'succeeded' || empty($intent->payment_method)) {
                return ['ok' => false, 'brand' => 'Card', 'last_four' => '0000', 'payment_method_id' => '', 'charge_id' => null, 'message' => 'Card setup was not completed.'];
            }

            $pmId = (string) $intent->payment_method;
            $pm = $client->paymentMethods->retrieve($pmId, []);
            $card = $pm->card ?? null;
            $brand = ucfirst((string) ($card->brand ?? 'Card'));
            $lastFour = (string) ($card->last4 ?? '0000');
            $expMonth = str_pad((string) ($card->exp_month ?? ''), 2, '0', STR_PAD_LEFT);
            $expYear = (string) ($card->exp_year ?? '');

            $customerId = self::ensureCustomer($user);
            if ($customerId) {
                try {
                    $client->paymentMethods->attach($pmId, ['customer' => $customerId]);
                } catch (\Throwable $e) {
                    // already attached is fine
                    if (! str_contains(strtolower($e->getMessage()), 'already been attached')) {
                        throw $e;
                    }
                }
                $client->customers->update($customerId, [
                    'invoice_settings' => ['default_payment_method' => $pmId],
                ]);
            }

            $chargeId = null;
            $amount = self::verifyAmountCents();
            $pi = $client->paymentIntents->create([
                'amount' => $amount,
                'currency' => strtolower((string) config('services.stripe.currency', 'usd')),
                'customer' => $customerId,
                'payment_method' => $pmId,
                'off_session' => true,
                'confirm' => true,
                'description' => 'Card verification (refunded)',
                'metadata' => [
                    'user_id' => (string) $user->id,
                    'purpose' => 'card_verification',
                ],
            ]);

            if (! empty($pi->latest_charge)) {
                $chargeId = (string) $pi->latest_charge;
                $client->refunds->create(['charge' => $chargeId]);
            }

            return [
                'ok' => true,
                'brand' => $brand !== '' ? $brand : 'Card',
                'last_four' => $lastFour,
                'exp_month' => $expMonth,
                'exp_year' => $expYear,
                'payment_method_id' => $pmId,
                'charge_id' => $chargeId,
                'message' => null,
            ];
        } catch (\Throwable $e) {
            Log::warning('Stripe card verify failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'brand' => 'Card',
                'last_four' => '0000',
                'payment_method_id' => '',
                'charge_id' => null,
                'message' => $e->getMessage(),
            ];
        }
    }

    private static function platformIntegration(): ?AdminIntegrationSetting
    {
        return AdminIntegrationSetting::query()
            ->where('name', 'stripe')
            ->where('enabled', true)
            ->orderByDesc('id')
            ->first();
    }
}
