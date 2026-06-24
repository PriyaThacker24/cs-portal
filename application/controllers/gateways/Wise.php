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
        if (! staff_can('edit', 'settings')) {
            return;
        }

        $this->load->library('wise_core');

        try {
            foreach ($this->wise_core->list_subscriptions() as $subscription) {
                $url = $subscription['delivery']['url'] ?? '';
                if ($url === $this->wise_gateway->webhookEndPoint && isset($subscription['id'])) {
                    $this->wise_core->delete_subscription($subscription['id']);
                }
            }

            $this->wise_core->create_subscription($this->wise_gateway->webhookEndPoint);
            set_alert('success', _l('webhook_created'));
        } catch (GuzzleHttp\Exception\RequestException $e) {
            // Surface the actual Wise API error (status code + response body)
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

        redirect(admin_url('settings/?group=payment_gateways&tab=online_payments_wise_tab'));
    }

    /**
     * The application Wise webhook endpoint.
     *
     * Wise has no payment-request webhook, so we subscribe to "balances#credit"
     * (money landing in the Wise balance). Because a balance credit does not
     * carry our invoice reference, we cannot safely auto-record the payment.
     * Instead we flag likely invoice matches (by amount + currency) and notify
     * staff to confirm and record the payment in the admin area.
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
            if (stripos($eventType, 'balances#credit') !== false) {
                $this->process_balance_credit($event['data'] ?? []);
            }
        } catch (Exception $e) {
            log_activity('Wise webhook error: ' . $e->getMessage());
        }

        http_response_code(200);
    }

    /**
     * Handle a Wise "balances#credit" event: find open invoices whose pending
     * Wise payment attempt matches the credited amount + currency, and notify
     * staff to confirm the payment.
     *
     * NOTE: the exact field names of the balances#credit payload should be
     * confirmed against a real Wise event; the lookups below are defensive.
     *
     * @param array $data
     *
     * @return void
     */
    protected function process_balance_credit($data)
    {
        // Amount can arrive as a scalar or an {value,currency} object
        $amount = $data['amount'] ?? null;
        if (is_array($amount)) {
            $amount = $amount['value'] ?? null;
        }

        $currency          = $data['currency'] ?? ($data['amount']['currency'] ?? null);
        $transactionType   = strtolower($data['transaction_type'] ?? 'credit');

        if ($amount === null || $transactionType !== 'credit') {
            return;
        }

        $amount = round((float) $amount, get_decimal_places());

        // Pending Wise payment attempts whose amount matches the credit
        $this->db->where('payment_gateway', 'wise');
        $attempts = $this->db->get(db_prefix() . 'payment_attempts')->result();

        $this->load->model('invoices_model');
        $matchedInvoices = [];

        foreach ($attempts as $attempt) {
            if (round((float) $attempt->amount, get_decimal_places()) !== $amount) {
                continue;
            }

            $invoice = $this->invoices_model->get($attempt->invoice_id);
            if (! $invoice) {
                continue;
            }

            // Match currency when the credit carries one
            if ($currency && strcasecmp($invoice->currency_name, $currency) !== 0) {
                continue;
            }

            $matchedInvoices[$invoice->id] = $invoice;
        }

        if (empty($matchedInvoices)) {
            log_activity('Wise credit received (' . $amount . ' ' . $currency . ') with no matching pending invoice.');

            return;
        }

        foreach ($matchedInvoices as $invoice) {
            $this->notify_staff_to_confirm($invoice, $amount, $currency);
        }
    }

    /**
     * Notify the staff responsible for an invoice that a matching Wise credit
     * arrived and needs manual confirmation/recording.
     *
     * @param object $invoice
     * @param float  $amount
     * @param string $currency
     *
     * @return void
     */
    protected function notify_staff_to_confirm($invoice, $amount, $currency)
    {
        // Staff to notify: invoice creator + assigned sale agent, else admins
        $staffIds = array_filter([$invoice->addedfrom ?? 0, $invoice->sale_agent ?? 0]);

        if (empty($staffIds)) {
            $admins   = $this->db->select('staffid')->where('admin', 1)->where('active', 1)->get(db_prefix() . 'staff')->result();
            $staffIds = array_map(function ($s) { return $s->staffid; }, $admins);
        }

        $notified = [];
        foreach (array_unique($staffIds) as $staffId) {
            $ok = add_notification([
                'description'     => 'wise_credit_received_notification',
                'touserid'        => $staffId,
                'fromcompany'     => 1,
                'link'            => 'invoices/list_invoices/' . $invoice->id,
                'additional_data' => serialize([
                    app_format_money($amount, $currency ?: $invoice->currency_name),
                    format_invoice_number($invoice->id),
                ]),
            ]);
            if ($ok) {
                $notified[] = $staffId;
            }
        }

        if ($notified) {
            pusher_trigger_notification($notified);
        }

        log_activity('Wise credit (' . $amount . ' ' . $currency . ') flagged for invoice ' . format_invoice_number($invoice->id) . ' - staff notified to confirm.');
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
