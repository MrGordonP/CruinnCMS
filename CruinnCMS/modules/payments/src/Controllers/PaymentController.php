<?php
/**
 * CruinnCMS — Payment Controller (stub)
 *
 * Handles inbound payment initiation requests from other modules
 * and eventual gateway callbacks. Full implementation pending.
 */

namespace Cruinn\Module\Payments\Controllers;

use Cruinn\Controllers\BaseController;

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
        $source   = $this->query('source');
        $sourceId = (int) $this->query('source_id');

        $this->render('public/payments/initiate', [
            'title'     => 'Complete Payment',
            'source'    => $source,
            'source_id' => $sourceId,
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
        // Stub — return 200 to prevent gateway retries
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode(['received' => true]);
        exit;
    }
}
