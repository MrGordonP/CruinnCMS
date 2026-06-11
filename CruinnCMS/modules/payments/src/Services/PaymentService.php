<?php

namespace Cruinn\Module\Payments\Services;

use Cruinn\Database;

class PaymentService
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function recordManualMembershipPayment(int $subscriptionId, array $data): int
    {
        $subscription = $this->db->fetch(
            'SELECT id FROM membership_subscriptions WHERE id = ?',
            [$subscriptionId]
        );
        if (!$subscription) {
            throw new \RuntimeException('Subscription not found.');
        }

        $transactionId = trim((string) ($data['transaction_id'] ?? ''));
        if ($transactionId === '') {
            $transactionId = 'manual-' . date('YmdHis');
        }

        $paymentId = (int) $this->db->insert('payments', [
            'subscription_id' => $subscriptionId,
            'transaction_id'  => $transactionId,
            'gateway'         => !empty($data['gateway']) ? (string) $data['gateway'] : null,
            'amount'          => (float) ($data['amount'] ?? 0),
            'currency'        => strtoupper((string) ($data['currency'] ?? 'EUR')),
            'status'          => 'completed',
            'paid_at'         => !empty($data['paid_at']) ? (string) $data['paid_at'] : date('Y-m-d H:i:s'),
            'notes'           => !empty($data['notes']) ? (string) $data['notes'] : null,
        ]);

        $this->db->update('membership_subscriptions', [
            'payment_id'          => $paymentId,
            'verification_status' => 'verified',
            'verified_at'         => date('Y-m-d H:i:s'),
            'transaction_id'      => $transactionId,
        ], 'id = ?', [$subscriptionId]);

        return $paymentId;
    }

    public function linkPaymentToSubscription(int $subscriptionId, int $paymentId): void
    {
        $subscription = $this->db->fetch('SELECT id FROM membership_subscriptions WHERE id = ?', [$subscriptionId]);
        if (!$subscription) {
            throw new \RuntimeException('Subscription not found.');
        }

        $payment = $this->db->fetch('SELECT id FROM payments WHERE id = ?', [$paymentId]);
        if (!$payment) {
            throw new \RuntimeException('Payment not found.');
        }

        $this->db->update('membership_subscriptions', ['payment_id' => $paymentId], 'id = ?', [$subscriptionId]);
        $this->db->update('payments', ['subscription_id' => $subscriptionId], 'id = ?', [$paymentId]);
    }

    public function unlinkPaymentFromSubscription(int $subscriptionId): void
    {
        $subscription = $this->db->fetch(
            'SELECT id, payment_id FROM membership_subscriptions WHERE id = ?',
            [$subscriptionId]
        );
        if (!$subscription) {
            throw new \RuntimeException('Subscription not found.');
        }

        $linkedPaymentId = (int) ($subscription['payment_id'] ?? 0);

        $this->db->update('membership_subscriptions', ['payment_id' => null], 'id = ?', [$subscriptionId]);

        if ($linkedPaymentId > 0) {
            $this->db->update(
                'payments',
                ['subscription_id' => null],
                'id = ? AND subscription_id = ?',
                [$linkedPaymentId, $subscriptionId]
            );
        }
    }
}
