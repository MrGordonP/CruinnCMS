<?php
/**
 * CruinnCMS — Payment Controller (stub)
 *
 * Handles inbound payment initiation requests from other modules
 * and eventual gateway callbacks. Full implementation pending.
 */

namespace Cruinn\Module\Payments\Controllers;

use Cruinn\Controllers\BaseController;
use Cruinn\Module\Payments\Services\PaymentService;

// Last edit: 2026-06-11 13:28 UTC.

class PaymentController extends BaseController
{
    /**
     * GET /payments/initiate
     *
     * Entry point from other modules (e.g. forms).
     * Expected query params:
     *   source      = 'form_submission'
     *   source_id   = <id>
     *
     * TODO: resolve amount from source, create payment_transaction,
     *       redirect to gateway or present manual payment instructions.
     */
    public function initiate(): void
    {
        $source   = trim((string) $this->query('source', ''));
        $sourceId = (int) $this->query('source_id');
        $amount   = (float) $this->query('amount', 0);
        $currency = strtoupper((string) $this->query('currency', 'EUR'));
        $gateway  = trim((string) $this->query('gateway', ''));

        $paymentId = 0;
        if ($source !== '' && $sourceId > 0 && $amount > 0) {
            $payments = new PaymentService($this->db);
            $existing = $payments->findPaymentBySource($source, $sourceId);

            if ($existing && in_array((string) ($existing['status'] ?? 'pending'), ['pending', 'completed'], true)) {
                $paymentId = (int) ($existing['id'] ?? 0);
            } else {
                $paymentId = $payments->createPayment([
                    'source_type' => $source,
                    'source_id'   => $sourceId,
                    'gateway'     => ($gateway !== '' ? $gateway : null),
                    'amount'      => $amount,
                    'currency'    => $currency,
                    'status'      => 'pending',
                    'notes'       => 'Initiated from public flow.',
                ]);
            }
        }

        $this->render('public/payments/initiate', [
            'title'     => 'Complete Payment',
            'source'    => $source,
            'source_id' => $sourceId,
            'payment_id'=> $paymentId,
            'amount'    => $amount,
            'currency'  => $currency,
            'gateway'   => $gateway,
        ]);
    }

    /**
     * GET /payments/success
     *
     * Landing page after a successful gateway redirect.
     * TODO: verify gateway callback, update transaction status.
     */
    public function success(): void
    {
        $this->render('public/payments/success', [
            'title' => 'Payment Received',
        ]);
    }

    /**
     * GET /payments/cancel
     *
     * Landing page when user cancels at the gateway.
     */
    public function cancel(): void
    {
        $this->render('public/payments/cancel', [
            'title' => 'Payment Cancelled',
        ]);
    }

    /**
     * POST /payments/webhook/{gateway}
     *
     * Receives gateway webhook callbacks (Stripe, etc.).
     * TODO: verify signature, update transaction + source records.
     */
    public function webhook(string $gateway): void
    {
        $payloadRaw = file_get_contents('php://input');
        $decoded = json_decode((string) $payloadRaw, true);
        if (!is_array($decoded)) {
            $decoded = [];
        }

        $payments = new PaymentService($this->db);

        $object = is_array($decoded['data']['object'] ?? null) ? $decoded['data']['object'] : [];
        $metadata = is_array($object['metadata'] ?? null) ? $object['metadata'] : [];

        $externalTx = trim((string) (
            $object['id']
            ?? $decoded['id']
            ?? $metadata['transaction_id']
            ?? ''
        ));

        $paymentId = (int) ($metadata['payment_id'] ?? 0);
        $payment = $paymentId > 0 ? $payments->findPaymentById($paymentId) : null;
        if (!$payment && $externalTx !== '') {
            $payment = $payments->findPaymentByTransactionId($externalTx);
        }

        $statusRaw = strtolower((string) ($object['status'] ?? $decoded['type'] ?? 'pending'));
        $status = 'pending';
        if (str_contains($statusRaw, 'succeed') || str_contains($statusRaw, 'paid') || str_contains($statusRaw, 'complete')) {
            $status = 'completed';
        } elseif (str_contains($statusRaw, 'refund')) {
            $status = 'refunded';
        } elseif (str_contains($statusRaw, 'fail') || str_contains($statusRaw, 'cancel')) {
            $status = 'failed';
        }

        $amount = 0.0;
        foreach (['amount_received', 'amount', 'amount_total'] as $amountKey) {
            if (isset($object[$amountKey])) {
                $raw = (float) $object[$amountKey];
                $amount = $raw > 999 ? ($raw / 100.0) : $raw;
                break;
            }
        }

        $currency = strtoupper((string) ($object['currency'] ?? 'EUR'));
        $eventTime = !empty($object['created']) ? date('Y-m-d H:i:s', (int) $object['created']) : date('Y-m-d H:i:s');

        $note = 'Webhook [' . $gateway . '] status=' . $status;

        if ($payment) {
            $payments->updatePaymentStatus((int) $payment['id'], $status, $note, $eventTime);
        }

        $payments->ingestRawTransaction([
            'payment_id'              => $payment ? (int) $payment['id'] : null,
            'source'                  => 'stripe_webhook',
            'external_transaction_id' => $externalTx !== '' ? $externalTx : null,
            'gateway'                 => $gateway,
            'direction'               => 'credit',
            'amount'                  => $amount,
            'currency'                => $currency,
            'transacted_at'           => $eventTime,
            'description'             => (string) ($decoded['type'] ?? 'webhook_event'),
            'reference'               => $externalTx !== '' ? $externalTx : null,
            'raw_payload'             => $decoded,
            'reconciliation_notes'    => $note,
        ]);

        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode([
            'received' => true,
            'payment_id' => $payment ? (int) $payment['id'] : null,
            'status' => $status,
        ]);
        exit;
    }
}
