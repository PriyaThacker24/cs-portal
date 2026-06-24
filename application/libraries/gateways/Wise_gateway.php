<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Wise (TransferWise) payment gateway.
 *
 * Built to mirror the Stripe gateway: it dynamically creates a payment link per
 * invoice (via the Wise Payment Requests API) and the invoice status is updated
 * automatically once Wise confirms the payment through its webhook.
 *
 * The class is auto-registered because every file in libraries/gateways is
 * autoloaded (see config/autoload.php).
 */
class Wise_gateway extends App_gateway
{
    public $webhookEndPoint;
    public bool $processingFees = true;

    public function __construct()
    {
        parent::__construct();

        /**
         * REQUIRED - Gateway unique id (alpha/alphanumeric)
         */
        $this->setId('wise');

        /**
         * REQUIRED - Gateway name
         */
        $this->setName('Wise');

        $this->setSettings([
            [
                'name'      => 'api_token',
                'encrypted' => true,
                'label'     => 'settings_paymentmethod_wise_api_token',
            ],
            [
                'name'  => 'profile_id',
                'label' => 'settings_paymentmethod_wise_profile_id',
            ],
            [
                'name'          => 'webhook_url',
                'label'         => 'settings_paymentmethod_wise_webhook_url',
                'default_value' => '',
            ],
            [
                'name'          => 'balance_id',
                'label'         => 'settings_paymentmethod_wise_balance_id',
                'default_value' => '',
            ],
            [
                'name'          => 'sandbox',
                'type'          => 'yes_no',
                'default_value' => 1,
                'label'         => 'settings_paymentmethod_wise_sandbox',
            ],
            [
                'name'          => 'webhook_public_key',
                'type'          => 'textarea',
                'label'         => 'settings_paymentmethod_wise_webhook_public_key',
                'default_value' => '',
            ],
            [
                'name'          => 'description_dashboard',
                'label'         => 'settings_paymentmethod_description',
                'type'          => 'textarea',
                'default_value' => 'Payment for Invoice {invoice_number}',
            ],
            [
                'name'          => 'currencies',
                'label'         => 'settings_paymentmethod_currencies',
                'default_value' => 'USD,EUR,GBP',
            ],
        ]);

        // Wise rejects non-public delivery URLs (e.g. *.local). Allow overriding
        // the public base URL (an ngrok/tunnel or staging host) used when
        // subscribing the webhook, falling back to the app's own site_url.
        $override              = $this->getSetting('webhook_url');
        $base                  = $override !== '' ? rtrim($override, '/') : rtrim(site_url(), '/');
        $this->webhookEndPoint = $base . '/gateways/wise/webhook_endpoint';

        hooks()->add_action('before_render_payment_gateway_settings', 'wise_gateway_webhook_check');
    }

    /**
     * Whether the gateway is configured to use the Wise sandbox environment
     */
    public function is_sandbox(): bool
    {
        return $this->getSetting('sandbox') == '1';
    }

    /**
     * Process the payment: create a Wise payment request for the invoice and
     * redirect the customer to the hosted Wise payment link.
     *
     * @param array $data
     *
     * @return mixed
     */
    public function process_payment($data)
    {
        $this->ci->load->library('wise_core');

        $invoice  = $data['invoice'];
        $currency = $invoice->currency_name;

        $description = str_replace(
            '{invoice_number}',
            format_invoice_number($invoice->id),
            $this->getSetting('description_dashboard')
        );

        $cancelUrl = site_url('invoice/' . $data['invoiceid'] . '/' . $invoice->hash);

        try {
            $balanceId = $this->ci->wise_core->resolve_balance_id($currency);

            if (! $balanceId) {
                throw new Exception('No Wise balance found for currency ' . $currency);
            }

            $paymentRequest = $this->ci->wise_core->create_payment_request([
                'balanceId'   => $balanceId,
                'amount'      => $data['amount'],
                'currency'    => $currency,
                'description' => $description,
                // Used by the webhook to match the payment back to this invoice
                'reference'   => $data['payment_attempt']->reference,
            ]);
        } catch (Exception $e) {
            set_alert('warning', $e->getMessage());
            redirect($cancelUrl);

            return;
        }

        $link = $paymentRequest['link'] ?? null;

        if (! $link) {
            set_alert('warning', 'Wise did not return a payment link.');
            redirect($cancelUrl);

            return;
        }

        redirect($link);
    }
}

/**
 * Render a notice in the gateway settings page when the Wise webhook
 * subscription is missing, so the admin can create it with one click.
 */
function wise_gateway_webhook_check($gateway)
{
    if ($gateway['id'] !== 'wise') {
        return;
    }

    $CI = &get_instance();
    $CI->load->library('wise_core');

    if (! $CI->wise_core->has_api_key() || $gateway['active'] != '1') {
        return;
    }

    $endpoint = $CI->wise_gateway->webhookEndPoint;

    try {
        $subscriptions = $CI->wise_core->list_subscriptions();
    } catch (Exception $e) {
        echo '<div class="alert alert-warning">' . $e->getMessage() . '</div>';

        return;
    }

    $found = false;
    foreach ($subscriptions as $subscription) {
        $url = $subscription['delivery']['url'] ?? '';
        if ($url === $endpoint) {
            $found = true;

            break;
        }
    }

    if (! $found) {
        echo '<div class="alert alert-warning">';
        echo 'Wise webhook endpoint (' . $endpoint . ') not found for the ' . ($CI->wise_gateway->is_sandbox() ? 'sandbox' : 'production') . ' environment.';
        echo '<br />Click <a href="' . site_url('gateways/wise/create_webhook') . '">here</a> to create the webhook subscription in Wise.';
        echo '</div>';
    }
}
