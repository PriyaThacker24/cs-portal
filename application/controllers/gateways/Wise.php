<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Wise payment gateway controller.
 *
 * Mirrors controllers/gateways/Stripe.php:
 *  - create_webhook()   creates the Wise webhook subscription from the settings page
 *  - webhook_endpoint() receives Wise notifications and records the payment, which
 *                       in turn auto-updates the invoice status
 *  - success()          customer landing page after returning from Wise
 */
class Wise extends App_Controller
{
    /**
     * Create the Wise webhook subscription for this installation.
     * Removes any existing subscription pointing at our endpoint first.
     *
     * @return mixed
     */
    public function create_webhook()
    {
        $redirectUrl = admin_url('settings/?group=payment_gateways&tab=online_payments_wise_tab');

        // is_admin() is reliable in this frontend gateway controller (staff_can
        // was returning false here, causing a silent no-op).
        if (! is_staff_logged_in() || ! is_admin()) {
            set_alert('warning', _l('access_denied'));
            redirect($redirectUrl);

            return;
        }

        $this->load->library('wise_core');

        try {
            // Remove any existing subscription pointing at our endpoint first.
            foreach ($this->wise_core->list_subscriptions() as $subscription) {
                $url = $subscription['delivery']['url'] ?? '';
                if ($url === $this->wise_gateway->webhookEndPoint && isset($subscription['id'])) {
                    $this->wise_core->delete_subscription($subscription['id']);
                }
            }

            $results = $this->wise_core->create_subscription($this->wise_gateway->webhookEndPoint);

            $errors = [];
            foreach ($results as $trigger => $result) {
                if (isset($result['error'])) {
                    $errors[] = $trigger . ' → ' . $result['error'];
                }
            }

            if (empty($errors)) {
                set_alert('success', _l('webhook_created'));
            } else {
                set_alert('warning', 'Some Wise webhook subscriptions failed: ' . implode(' | ', $errors));
            }
        } catch (GuzzleHttp\Exception\RequestException $e) {
            $message = $e->getMessage();
            if ($e->hasResponse()) {
                $message = 'HTTP ' . $e->getResponse()->getStatusCode() . ': ' . (string) $e->getResponse()->getBody();
            }
            log_activity('Wise create webhook error: ' . $message);
            set_alert('warning', $message);
        } catch (Exception $e) {
            log_activity('Wise create webhook error: ' . $e->getMessage());
            set_alert('warning', $e->getMessage());
        }

        redirect($redirectUrl);
    }

    /**
     * The application Wise webhook endpoint.
     *
     * Handles "account-details-payment#state-change": the event carries the
     * payment reference the link was created with (the invoice number), so the
     * matching invoice is resolved and the payment recorded automatically.
     *
     * @return mixed
     */
    public function webhook_endpoint()
    {
        $payload = @file_get_contents('php://input');

        // Wise pings the callback URL (with an empty/validation body) while
        // creating the subscription and requires a 2xx response, otherwise it
        // rejects the subscription with INVALID_CALLBACK_URL. Answer OK.
        if (! $payload) {
            http_response_code(200);

            return;
        }

        $this->load->library('wise_core');

        // Defense in depth - verify the signature when a public key is configured
        $signature = $_SERVER['HTTP_X_SIGNATURE_SHA256'] ?? ($_SERVER['HTTP_X_SIGNATURE'] ?? '');
        if (! $this->wise_core->verify_signature($payload, $signature)) {
            http_response_code(400);

            exit();
        }

        $event = json_decode($payload, true);

        if (! is_array($event)) {
            // Not a JSON event we understand (e.g. a validation ping) - ack it
            http_response_code(200);

            return;
        }

        // Wise sends a test notification when the subscription is created
        $eventType = $event['event_type'] ?? '';
        if (stripos($eventType, 'test') !== false) {
            http_response_code(200);

            return;
        }

        try {
            if (stripos($eventType, 'account-details-payment') !== false) {
                // Carries the invoice-number reference, so we match the invoice
                // and auto-record the payment.
                $this->process_account_details_payment($event['data'] ?? []);
            }
        } catch (Exception $e) {
            log_activity('Wise webhook error: ' . $e->getMessage());
        }

        http_response_code(200);
    }

    /**
     * Handle a Wise "account-details-payment#state-change" event.
     *
     * The Wise payment link is created with the invoice number as its
     * reference, so an incoming payment carries that reference. We match it to
     * the invoice and record the payment automatically (which flips the invoice
     * to Paid). Payments without a usable reference are logged and left for
     * manual recording.
     *
     * @param array $data
     *
     * @return void
     */
    protected function process_account_details_payment($data)
    {
        // Only act once the payment is actually received/credited.
        $state = strtoupper((string) ($data['current_state'] ?? $data['state'] ?? $data['status'] ?? ''));
        if ($state !== ''
            && strpos($state, 'CREDIT') === false
            && strpos($state, 'COMPLET') === false
            && strpos($state, 'PROCESS') === false
            && strpos($state, 'RECEIV') === false) {
            return;
        }

        // Amount + currency (can be scalar or {value,currency}).
        $amount   = $data['amount'] ?? null;
        $currency = $data['currency'] ?? null;
        if (is_array($amount)) {
            $currency = $amount['currency'] ?? $currency;
            $amount   = $amount['value'] ?? null;
        }

        // Reference the link was created with (should be the invoice number).
        $reference = trim((string) (
            $data['reference']
            ?? $data['paymentReference']
            ?? $data['payment_reference']
            ?? ($data['details']['reference'] ?? '')
        ));

        // No usable reference -> cannot safely match an invoice; log for manual
        // recording.
        if ($reference === '' || $amount === null) {
            log_activity('Wise payment received without a usable reference (amount: ' . (is_scalar($amount) ? $amount : 'n/a') . ' ' . $currency . ') - record manually.');

            return;
        }

        $invoice = $this->find_invoice_by_reference($reference);

        if (! $invoice) {
            log_activity('Wise payment received (reference: ' . $reference . ') but no matching invoice was found.');

            return;
        }

        // Currency guard (when the event carries one).
        if ($currency && strcasecmp($invoice->currency_name, $currency) !== 0) {
            log_activity('Wise payment reference ' . $reference . ' currency mismatch (' . $currency . ' vs ' . $invoice->currency_name . ').');

            return;
        }

        // Already paid - nothing to do.
        if ((int) $invoice->status === Invoices_model::STATUS_PAID) {
            return;
        }

        $recordAmount  = round((float) $amount, get_decimal_places());
        $transactionId = (string) ($data['id'] ?? $data['resource']['id'] ?? $reference);

        // addPayment() records the payment and auto-updates the invoice status.
        $recorded = $this->wise_gateway->addPayment([
            'amount'                    => $recordAmount,
            'invoiceid'                 => $invoice->id,
            'paymentmethod'             => 'Wise',
            'transactionid'             => $transactionId,
            'note'                      => 'Wise payment auto-recorded (reference: ' . $reference . ').',
            'payment_attempt_reference' => '',
        ]);

        if ($recorded) {
            log_activity('Wise payment auto-recorded for invoice ' . format_invoice_number($invoice->id) . ' (reference: ' . $reference . ', amount: ' . $recordAmount . ' ' . $currency . ').');
        } else {
            log_activity('Wise payment for invoice ' . format_invoice_number($invoice->id) . ' (reference: ' . $reference . ') could not be recorded automatically.');
        }
    }

    /**
     * Resolve an invoice from a Wise payment reference that should contain the
     * invoice number (e.g. "INV-000755" or "755"). Returns the invoice object
     * only when the reference confidently identifies it.
     *
     * @param string $reference
     *
     * @return object|null
     */
    protected function find_invoice_by_reference($reference)
    {
        $ref = strtoupper(trim($reference));

        // Numeric part of the reference -> candidate invoice id.
        $digits = preg_replace('/\D/', '', $ref);
        if ($digits === '') {
            return null;
        }

        $this->load->model('invoices_model');
        $invoice = $this->invoices_model->get((int) $digits);
        if (! $invoice) {
            return null;
        }

        // Confirm the reference really identifies this invoice: it should equal
        // the formatted invoice number, or the numeric id should match exactly.
        $formatted = strtoupper(format_invoice_number($invoice->id));
        if ($ref === $formatted
            || (int) $digits === (int) $invoice->id
            || strpos($ref, $formatted) !== false
            || strpos($formatted, $ref) !== false) {
            return $invoice;
        }

        return null;
    }

    /**
     * Customer landing page after returning from the Wise hosted payment page.
     *
     * @param string $invoice_id
     * @param string $invoice_hash
     *
     * @return mixed
     */
    public function success($invoice_id, $invoice_hash)
    {
        set_alert('success', _l('online_payment_recorded_success'));

        redirect(site_url('invoice/' . $invoice_id . '/' . $invoice_hash));
    }
}
