<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Thin wrapper around the Wise (TransferWise) Platform REST API.
 *
 * Mirrors the role Stripe_core plays for the Stripe integration: it centralises
 * every outbound HTTP call so the gateway/controller never talk to Wise directly.
 *
 * Wise has no Stripe-Checkout equivalent, so "payment links" are created through
 * the Payment Requests ("Request money" / Wise Pay) API and the hosted link Wise
 * returns is what the client is redirected to.
 *
 * NOTE: A few endpoint paths / field names below can vary by Wise account region
 * and product. They are grouped here on purpose so they are trivial to adjust
 * against your specific Wise account if a call returns a 4xx. They should be
 * validated against the Wise sandbox first.
 */
class Wise_core
{
    /** Production API base url */
    const BASE_LIVE = 'https://api.wise.com';

    /** Sandbox API base url */
    const BASE_SANDBOX = 'https://api.wise-sandbox.com';

    /**
     * The Wise webhook trigger we subscribe to.
     *
     * Wise has NO payment-request webhook ("payment-request#state-change" is
     * rejected by the API as an invalid trigger). When a payer settles a Wise
     * payment request the money lands in the balance, which fires "balances#credit".
     * We therefore subscribe to that and reconcile the credit to an invoice.
     */
    const WEBHOOK_TRIGGER = 'balances#credit';

    protected $ci;

    /** @var string decrypted Wise API token */
    protected $apiToken;

    /** @var string Wise business profile id */
    protected $profileId;

    public function __construct()
    {
        $this->ci        = &get_instance();
        $this->apiToken  = $this->ci->wise_gateway->decryptSetting('api_token');
        $this->profileId = $this->ci->wise_gateway->getSetting('profile_id');
    }

    /**
     * Whether the integration has the minimum credentials configured
     */
    public function has_api_key(): bool
    {
        return $this->apiToken != '' && $this->profileId != '';
    }

    /**
     * Resolve the API base url based on the configured environment
     */
    public function base_url(): string
    {
        return $this->ci->wise_gateway->is_sandbox() ? self::BASE_SANDBOX : self::BASE_LIVE;
    }

    /**
     * Lazily build the Guzzle client with auth headers
     */
    protected function client(): Client
    {
        return new Client([
            'base_uri' => $this->base_url(),
            'headers'  => [
                'Authorization' => 'Bearer ' . $this->apiToken,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'http_errors' => true,
            'timeout'     => 30,
            // Some local environments (e.g. Local by Flywheel) ship a php.ini
            // whose curl.cainfo/openssl.cafile points at a CA bundle that does
            // not exist for this app, causing "cURL error 77". Point Guzzle at
            // a CA bundle we know exists so TLS verification keeps working.
            'verify' => $this->ca_bundle(),
        ]);
    }

    /**
     * Resolve a readable CA bundle for TLS verification.
     * Falls back to the bundle vendored with the Stripe SDK, which is always
     * present in this project, then finally to Guzzle's default behaviour.
     *
     * @return string|bool
     */
    protected function ca_bundle()
    {
        $candidates = [
            ini_get('openssl.cafile'),
            ini_get('curl.cainfo'),
            APPPATH . 'vendor/stripe/stripe-php/data/ca-certificates.crt',
        ];

        foreach ($candidates as $path) {
            if ($path && is_readable($path)) {
                return $path;
            }
        }

        // Let Guzzle decide (true = use its detected default)
        return true;
    }

    /**
     * Perform an API request and decode the JSON response
     *
     * @param string $method
     * @param string $path
     * @param array  $payload
     *
     * @throws GuzzleException
     *
     * @return array|null
     */
    protected function request($method, $path, $payload = null, $headers = [])
    {
        $options = [];

        if (! is_null($payload)) {
            $options['json'] = $payload;
        }

        if (! empty($headers)) {
            $options['headers'] = $headers;
        }

        $response = $this->client()->request($method, $path, $options);
        $body     = (string) $response->getBody();

        return $body === '' ? null : json_decode($body, true);
    }

    /**
     * Generate a UUID v4 for the X-External-Correlation-Id header
     */
    protected function correlation_id(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * List the standard balances of the configured profile.
     * Used to resolve the balance id money should be received into.
     *
     * @throws GuzzleException
     */
    public function get_balances(): array
    {
        $balances = $this->request('GET', '/v4/profiles/' . $this->profileId . '/balances?types=STANDARD');

        return is_array($balances) ? $balances : [];
    }

    /**
     * Find the balance id matching the given currency (e.g. "USD").
     * Falls back to the explicitly configured balance id setting.
     *
     * @param string $currency
     *
     * @throws GuzzleException
     *
     * @return string|int|null
     */
    public function resolve_balance_id($currency)
    {
        $configured = $this->ci->wise_gateway->getSetting('balance_id');
        if ($configured !== '') {
            return $configured;
        }

        foreach ($this->get_balances() as $balance) {
            if (isset($balance['currency']) && strcasecmp($balance['currency'], $currency) === 0) {
                return $balance['id'];
            }
        }

        return null;
    }

    /**
     * Create a Wise payment request ("Request money") and return the decoded
     * response. The hosted payment link is available on the "link" key.
     *
     * @param array $data {
     *
     *     @var string|int $balanceId   Balance the payment should be received into
     *     @var float      $amount      Amount value
     *     @var string     $currency    ISO currency code
     *     @var string     $description Human readable description
     *     @var string     $reference   Our internal reference used to match the payment back to the invoice
     * }
     *
     * @throws GuzzleException
     */
    public function create_payment_request($data): array
    {
        $payload = [
            'balanceId'   => $data['balanceId'],
            'amount'      => [
                'value'    => round((float) $data['amount'], 2),
                'currency' => strtoupper($data['currency']),
            ],
            'description' => $data['description'],
            // "reference" is echoed back on the payment request and is how the
            // webhook resolves which invoice the payment belongs to.
            'reference'   => $data['reference'],
        ];

        return (array) $this->request('POST', '/v3/profiles/' . $this->profileId . '/payment-requests', $payload);
    }

    /**
     * Retrieve a single payment request (authoritative source of truth for its
     * status and reference). The webhook only acts as a trigger; this call is
     * what actually confirms a payment, so a forged webhook cannot create a
     * fake payment.
     *
     * @param string $id
     *
     * @throws GuzzleException
     */
    public function get_payment_request($id): array
    {
        return (array) $this->request('GET', '/v3/profiles/' . $this->profileId . '/payment-requests/' . $id);
    }

    /**
     * List the webhook subscriptions configured for the profile
     *
     * @throws GuzzleException
     */
    public function list_subscriptions(): array
    {
        $subscriptions = $this->request('GET', '/v3/profiles/' . $this->profileId . '/subscriptions');

        return is_array($subscriptions) ? $subscriptions : [];
    }

    /**
     * Create a webhook subscription pointing at our endpoint
     *
     * @param string $url
     *
     * @throws GuzzleException
     */
    public function create_subscription($url): array
    {
        $payload = [
            'name'       => 'CRM payment-request notifications',
            'trigger_on' => self::WEBHOOK_TRIGGER,
            'delivery'   => [
                'version' => '2.0.0',
                'url'     => $url,
            ],
        ];

        return (array) $this->request(
            'POST',
            '/v3/profiles/' . $this->profileId . '/subscriptions',
            $payload,
            ['X-External-Correlation-Id' => $this->correlation_id()]
        );
    }

    /**
     * Delete a webhook subscription by id
     *
     * @param string $id
     *
     * @throws GuzzleException
     */
    public function delete_subscription($id): void
    {
        $this->request('DELETE', '/v3/profiles/' . $this->profileId . '/subscriptions/' . $id);
    }

    /**
     * Verify the Wise webhook signature (defense in depth).
     *
     * Wise signs the raw request body with RSA-SHA256 and sends the base64
     * signature in the "X-Signature-SHA256" header. Verification requires the
     * Wise public key, which the admin can paste into the gateway settings.
     *
     * When no public key is configured this returns true, because the webhook
     * handler independently re-fetches the payment request from the Wise API
     * (using the secret token) before recording anything.
     *
     * @param string $payload   Raw request body
     * @param string $signature Base64 signature header value
     */
    public function verify_signature($payload, $signature): bool
    {
        $publicKey = $this->ci->wise_gateway->getSetting('webhook_public_key');

        if ($publicKey === '' || $signature === '') {
            return true;
        }

        $verified = openssl_verify(
            $payload,
            base64_decode($signature),
            $publicKey,
            OPENSSL_ALGO_SHA256
        );

        return $verified === 1;
    }
}
