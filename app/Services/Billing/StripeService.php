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

    public static function keysArePaired(): bool
    {
        $pk = self::publishableKey() ?? '';
        $sk = self::secretKey() ?? '';

        if ($pk === '' || $sk === '') {
            return false;
        }

        return (str_starts_with($pk, 'pk_test_') && str_starts_with($sk, 'sk_test_'))
            || (str_starts_with($pk, 'pk_live_') && str_starts_with($sk, 'sk_live_'));
    }

    /**
     * True when secret key can reach Stripe API (invalid/expired keys fail here).
     */
    public static function secretKeyIsValid(): bool
    {
        static $cached = null;

        if ($cached !== null) {
            return $cached;
        }

        $client = self::client();
        if (! $client) {
            return $cached = false;
        }

        try {
            $client->balance->retrieve();

            return $cached = true;
        } catch (\Throwable $e) {
            Log::warning('Stripe secret key validation failed', [
                'error' => $e->getMessage(),
            ]);

            return $cached = false;
        }
    }

    /**
     * Stripe is ready for $1 verify + refund on this server.
     */
    public static function canCharge(): bool
    {
        return self::isConfigured()
            && self::keysArePaired()
            && self::secretKeyIsValid();
    }

    /**
     * @return array{ready: bool, message: ?string}
     */
    public static function readiness(): array
    {
        if (! self::isConfigured()) {
            return ['ready' => false, 'message' => null];
        }

        if (! self::keysArePaired()) {
            return [
                'ready' => false,
                'message' => 'Stripe keys do not match (use both test or both live keys from the same Stripe account).',
            ];
        }

        if (! self::secretKeyIsValid()) {
            return [
                'ready' => false,
                'message' => 'Stripe secret key is invalid or expired. Update STRIPE_SECRET in .env, then run php artisan config:clear && php artisan config:cache.',
            ];
        }

        $pk = self::publishableKey() ?? '';
        if (! str_starts_with($pk, 'pk_test_') && ! str_starts_with($pk, 'pk_live_')) {
            return [
                'ready' => false,
                'message' => 'Stripe publishable key format is invalid. Update STRIPE_KEY in .env.',
            ];
        }

        return ['ready' => true, 'message' => null];
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

        try {
            $customer = $client->customers->create([
                'email' => $user->email,
                'name' => $user->name,
                'metadata' => ['user_id' => (string) $user->id],
            ]);

            $user->forceFill(['stripe_customer_id' => $customer->id])->save();

            return $customer->id;
        } catch (\Throwable $e) {
            Log::warning('Stripe customer create failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
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
     * Hosted Stripe Checkout (setup mode) — user leaves the app and adds a card on Stripe.
     *
     * @param  array<string, string>  $metadata
     * @return array{url: string, session_id: string}|null
     */
    public static function createCheckoutSetupSession(
        User $user,
        string $successUrl,
        string $cancelUrl,
        array $metadata = []
    ): ?array {
        $client = self::client();
        $customerId = self::ensureCustomer($user);
        if (! $client || ! $customerId) {
            return null;
        }

        try {
            $session = $client->checkout->sessions->create([
                'mode' => 'setup',
                'customer' => $customerId,
                'payment_method_types' => ['card'],
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'metadata' => array_merge([
                    'user_id' => (string) $user->id,
                    'purpose' => 'card_setup',
                ], $metadata),
            ]);

            $url = (string) ($session->url ?? '');
            if ($url === '') {
                return null;
            }

            return [
                'url' => $url,
                'session_id' => (string) $session->id,
            ];
        } catch (\Throwable $e) {
            Log::warning('Stripe Checkout setup session failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Hosted Stripe Checkout (payment mode) — charge for a plan on Stripe's page.
     *
     * @param  array<string, string>  $metadata
     * @return array{url: string, session_id: string}|null
     */
    public static function createCheckoutPaymentSession(
        User $user,
        string $productName,
        int $amountCents,
        string $currency,
        string $successUrl,
        string $cancelUrl,
        array $metadata = []
    ): ?array {
        $client = self::client();
        $customerId = self::ensureCustomer($user);
        if (! $client || ! $customerId || $amountCents < 50) {
            return null;
        }

        try {
            $session = $client->checkout->sessions->create([
                'mode' => 'payment',
                'customer' => $customerId,
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => strtolower($currency ?: (string) config('services.stripe.currency', 'usd')),
                        'product_data' => ['name' => $productName],
                        'unit_amount' => $amountCents,
                    ],
                    'quantity' => 1,
                ]],
                'payment_intent_data' => [
                    'setup_future_usage' => 'off_session',
                    'metadata' => array_merge([
                        'user_id' => (string) $user->id,
                    ], $metadata),
                ],
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'metadata' => array_merge([
                    'user_id' => (string) $user->id,
                    'purpose' => 'plan_payment',
                ], $metadata),
            ]);

            $url = (string) ($session->url ?? '');
            if ($url === '') {
                return null;
            }

            return [
                'url' => $url,
                'session_id' => (string) $session->id,
            ];
        } catch (\Throwable $e) {
            Log::warning('Stripe Checkout payment session failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array{ok: bool, brand: string, last_four: string, exp_month: ?string, exp_year: ?string, payment_method_id: string, charge_id: ?string, metadata: array<string, string>, message: ?string}
     */
    public static function completeCheckoutSetup(User $user, string $sessionId): array
    {
        $client = self::client();
        if (! $client) {
            return self::failResult('Stripe is not configured.');
        }

        try {
            $session = $client->checkout->sessions->retrieve($sessionId, [
                'expand' => ['setup_intent', 'setup_intent.payment_method'],
            ]);

            if ((string) ($session->status ?? '') !== 'complete') {
                return self::failResult('Checkout was not completed.');
            }

            if ((string) data_get($session->metadata, 'user_id', '') !== (string) $user->id) {
                return self::failResult('Checkout session does not belong to this account.');
            }

            $setupIntentId = is_string($session->setup_intent)
                ? $session->setup_intent
                : (string) ($session->setup_intent->id ?? '');

            if ($setupIntentId === '') {
                return self::failResult('No card was set up on Stripe.');
            }

            $result = self::attachAndVerify($user, $setupIntentId);
            $result['metadata'] = self::metadataToArray($session->metadata ?? null);

            return $result;
        } catch (\Throwable $e) {
            Log::warning('Stripe Checkout setup complete failed', [
                'user_id' => $user->id,
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return self::failResult($e->getMessage());
        }
    }

    /**
     * @return array{ok: bool, brand: string, last_four: string, exp_month: ?string, exp_year: ?string, payment_method_id: string, charge_id: ?string, amount_cents: int, metadata: array<string, string>, message: ?string}
     */
    public static function completeCheckoutPayment(User $user, string $sessionId): array
    {
        $client = self::client();
        if (! $client) {
            return array_merge(self::failResult('Stripe is not configured.'), ['amount_cents' => 0]);
        }

        try {
            $session = $client->checkout->sessions->retrieve($sessionId, [
                'expand' => ['payment_intent', 'payment_intent.payment_method'],
            ]);

            if ((string) ($session->payment_status ?? '') !== 'paid') {
                return array_merge(self::failResult('Payment was not completed.'), ['amount_cents' => 0]);
            }

            if ((string) data_get($session->metadata, 'user_id', '') !== (string) $user->id) {
                return array_merge(self::failResult('Checkout session does not belong to this account.'), ['amount_cents' => 0]);
            }

            $pi = $session->payment_intent;
            $pmId = '';
            if (is_object($pi) && ! empty($pi->payment_method)) {
                $pmId = is_string($pi->payment_method)
                    ? $pi->payment_method
                    : (string) ($pi->payment_method->id ?? '');
            }

            $brand = 'Card';
            $lastFour = '0000';
            $expMonth = null;
            $expYear = null;

            if ($pmId !== '') {
                $pm = $client->paymentMethods->retrieve($pmId, []);
                $card = $pm->card ?? null;
                $brand = ucfirst((string) ($card->brand ?? 'Card')) ?: 'Card';
                $lastFour = (string) ($card->last4 ?? '0000');
                $expMonth = str_pad((string) ($card->exp_month ?? ''), 2, '0', STR_PAD_LEFT);
                $expYear = (string) ($card->exp_year ?? '');

                $customerId = self::ensureCustomer($user);
                if ($customerId) {
                    try {
                        $client->paymentMethods->attach($pmId, ['customer' => $customerId]);
                    } catch (\Throwable $e) {
                        if (! str_contains(strtolower($e->getMessage()), 'already been attached')) {
                            throw $e;
                        }
                    }
                    $client->customers->update($customerId, [
                        'invoice_settings' => ['default_payment_method' => $pmId],
                    ]);
                }
            }

            $chargeId = is_object($pi) && ! empty($pi->latest_charge)
                ? (string) $pi->latest_charge
                : null;

            return [
                'ok' => true,
                'brand' => $brand,
                'last_four' => $lastFour,
                'exp_month' => $expMonth,
                'exp_year' => $expYear,
                'payment_method_id' => $pmId,
                'charge_id' => $chargeId,
                'amount_cents' => (int) ($session->amount_total ?? 0),
                'metadata' => self::metadataToArray($session->metadata ?? null),
                'message' => null,
            ];
        } catch (\Throwable $e) {
            Log::warning('Stripe Checkout payment complete failed', [
                'user_id' => $user->id,
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return array_merge(self::failResult($e->getMessage()), ['amount_cents' => 0]);
        }
    }

    /**
     * Charge a small amount then refund immediately to prove the card works.
     *
     * @return array{ok: bool, brand: string, last_four: string, exp_month?: ?string, exp_year?: ?string, payment_method_id: string, charge_id: ?string, message: ?string, metadata?: array<string, string>}
     */
    public static function attachAndVerify(User $user, string $setupIntentId): array
    {
        $client = self::client();
        if (! $client) {
            return self::failResult('Stripe is not configured.');
        }

        try {
            $intent = $client->setupIntents->retrieve($setupIntentId, []);
            if (($intent->status ?? '') !== 'succeeded' || empty($intent->payment_method)) {
                return self::failResult('Card setup was not completed.');
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
                'metadata' => [],
            ];
        } catch (\Throwable $e) {
            Log::warning('Stripe card verify failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return self::failResult($e->getMessage());
        }
    }

    /**
     * Verify an already-created Stripe payment method by charging and refunding.
     *
     * @return array{ok: bool, brand: string, last_four: string, exp_month?: ?string, exp_year?: ?string, payment_method_id: string, charge_id: ?string, message: ?string, metadata?: array<string, string>}
     */
    public static function verifyPaymentMethod(User $user, string $paymentMethodId): array
    {
        $client = self::client();
        if (! $client) {
            return self::failResult('Stripe is not configured.');
        }

        $pmId = trim($paymentMethodId);
        if ($pmId === '') {
            return self::failResult('Missing payment method.');
        }

        try {
            $pm = $client->paymentMethods->retrieve($pmId, []);
            $card = $pm->card ?? null;
            if (! $card) {
                return self::failResult('Only card payment methods are supported.');
            }

            $brand = ucfirst((string) ($card->brand ?? 'Card'));
            $lastFour = (string) ($card->last4 ?? '0000');
            $expMonth = str_pad((string) ($card->exp_month ?? ''), 2, '0', STR_PAD_LEFT);
            $expYear = (string) ($card->exp_year ?? '');

            $customerId = self::ensureCustomer($user);
            if (! $customerId) {
                return self::failResult('Unable to create Stripe customer.');
            }

            try {
                $client->paymentMethods->attach($pmId, ['customer' => $customerId]);
            } catch (\Throwable $e) {
                if (! str_contains(strtolower($e->getMessage()), 'already been attached')) {
                    throw $e;
                }
            }

            $client->customers->update($customerId, [
                'invoice_settings' => ['default_payment_method' => $pmId],
            ]);

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
                'metadata' => [],
            ];
        } catch (\Throwable $e) {
            Log::warning('Stripe payment method verify failed', [
                'user_id' => $user->id,
                'payment_method_id' => $pmId,
                'error' => $e->getMessage(),
            ]);

            return self::failResult($e->getMessage());
        }
    }

    /**
     * @return array{ok: bool, brand: string, last_four: string, exp_month: ?string, exp_year: ?string, payment_method_id: string, charge_id: ?string, message: ?string, metadata: array<string, string>}
     */
    private static function failResult(string $message): array
    {
        return [
            'ok' => false,
            'brand' => 'Card',
            'last_four' => '0000',
            'exp_month' => null,
            'exp_year' => null,
            'payment_method_id' => '',
            'charge_id' => null,
            'message' => $message,
            'metadata' => [],
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function metadataToArray(mixed $metadata): array
    {
        if (is_array($metadata)) {
            return array_map('strval', $metadata);
        }

        if (is_object($metadata) && method_exists($metadata, 'toArray')) {
            return array_map('strval', $metadata->toArray());
        }

        return [];
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
