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
 * Wise has no public "create payment link" API, so payment links are entered
 * manually on the invoice. This class is used only for webhook subscription
 * management and signature verification; incoming payments are reconciled via
 * the "account-details-payment#state-change" webhook.
 */
class Wise_core
{
    /** Production API base url */
    const BASE_LIVE = 'https://api.wise.com';

    /** Sandbox API base url */
    const BASE_SANDBOX = 'https://api.wise-sandbox.com';

    /**
     * The Wise webhook triggers we subscribe to.
     *
     * "account-details-payment#state-change" fires when a payment is received
     * into the account details. It carries the payment "reference" the link was
     * created with (the invoice number), which lets us match the invoice and
     * auto-record the payment.
     */
    const WEBHOOK_TRIGGERS = [
        'account-details-payment#state-change',
    ];

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
     * Create the webhook subscriptions pointing at our endpoint (one per
     * trigger). Each trigger is attempted independently so a trigger that is
     * not available for the account does not block the others.
     *
     * @param string $url
     *
     * @return array trigger => created subscription | ['error' => message]
     */
    public function create_subscription($url): array
    {
        $results = [];

        foreach (self::WEBHOOK_TRIGGERS as $trigger) {
            try {
                $results[$trigger] = (array) $this->request(
                    'POST',
                    '/v3/profiles/' . $this->profileId . '/subscriptions',
                    [
                        'name'       => 'CRM payment notifications',
                        'trigger_on' => $trigger,
                        'delivery'   => [
                            'version' => '2.0.0',
                            'url'     => $url,
                        ],
                    ],
                    ['X-External-Correlation-Id' => $this->correlation_id()]
                );
            } catch (\GuzzleHttp\Exception\RequestException $e) {
                $message = $e->getMessage();
                if ($e->hasResponse()) {
                    $message = 'HTTP ' . $e->getResponse()->getStatusCode() . ': ' . (string) $e->getResponse()->getBody();
                }
                $results[$trigger] = ['error' => $message];
            } catch (GuzzleException $e) {
                $results[$trigger] = ['error' => $e->getMessage()];
            }
        }

            return $results;
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
