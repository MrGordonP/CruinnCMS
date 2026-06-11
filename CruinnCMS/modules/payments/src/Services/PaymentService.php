<?php

namespace Cruinn\Module\Payments\Services;

use Cruinn\Database;

// Last edit: 2026-06-11 13:04 UTC.

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

    public function createPayment(array $data): int
    {
        $transactionId = trim((string) ($data['transaction_id'] ?? ''));
        if ($transactionId === '') {
            $transactionId = 'txn-' . date('YmdHis');
        }

        return (int) $this->db->insert('payments', [
            'subscription_id' => isset($data['subscription_id']) ? (int) $data['subscription_id'] : null,
            'subject_id'      => isset($data['subject_id']) ? (int) $data['subject_id'] : null,
            'source_type'     => !empty($data['source_type']) ? (string) $data['source_type'] : null,
            'source_id'       => isset($data['source_id']) ? (int) $data['source_id'] : null,
            'transaction_id'  => $transactionId,
            'gateway'         => !empty($data['gateway']) ? (string) $data['gateway'] : null,
            'amount'          => (float) ($data['amount'] ?? 0),
            'currency'        => strtoupper((string) ($data['currency'] ?? 'EUR')),
            'status'          => !empty($data['status']) ? (string) $data['status'] : 'pending',
            'paid_at'         => !empty($data['paid_at']) ? (string) $data['paid_at'] : date('Y-m-d H:i:s'),
            'notes'           => !empty($data['notes']) ? (string) $data['notes'] : null,
        ]);
    }

    public function createImportBatch(array $data): int
    {
        return (int) $this->db->insert('payment_import_batches', [
            'source'              => (string) ($data['source'] ?? 'manual'),
            'filename'            => !empty($data['filename']) ? (string) $data['filename'] : null,
            'checksum'            => !empty($data['checksum']) ? (string) $data['checksum'] : null,
            'status'              => 'processing',
            'row_count'           => (int) ($data['row_count'] ?? 0),
            'matched_count'       => 0,
            'unmatched_count'     => 0,
            'error_message'       => null,
            'imported_by_user_id' => isset($data['imported_by_user_id']) ? (int) $data['imported_by_user_id'] : null,
        ]);
    }

    public function markImportBatchCompleted(int $batchId, int $rowCount, int $matchedCount, int $unmatchedCount): void
    {
        $this->db->update('payment_import_batches', [
            'status'          => 'completed',
            'row_count'       => $rowCount,
            'matched_count'   => $matchedCount,
            'unmatched_count' => $unmatchedCount,
            'completed_at'    => date('Y-m-d H:i:s'),
            'error_message'   => null,
        ], 'id = ?', [$batchId]);
    }

    public function markImportBatchFailed(int $batchId, string $errorMessage): void
    {
        $this->db->update('payment_import_batches', [
            'status'        => 'failed',
            'error_message' => $errorMessage,
            'completed_at'  => date('Y-m-d H:i:s'),
        ], 'id = ?', [$batchId]);
    }

    public function ingestRawTransaction(array $data): int
    {
        $payload = $data['raw_payload'] ?? null;
        if (is_array($payload)) {
            $payload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } elseif ($payload !== null) {
            $payload = (string) $payload;
        }

        return (int) $this->db->insert('payment_transactions', [
            'payment_id'              => isset($data['payment_id']) ? (int) $data['payment_id'] : null,
            'batch_id'                => isset($data['batch_id']) ? (int) $data['batch_id'] : null,
            'source'                  => (string) ($data['source'] ?? 'manual'),
            'external_transaction_id' => !empty($data['external_transaction_id']) ? (string) $data['external_transaction_id'] : null,
            'gateway'                 => !empty($data['gateway']) ? (string) $data['gateway'] : null,
            'direction'               => (($data['direction'] ?? 'credit') === 'debit') ? 'debit' : 'credit',
            'amount'                  => (float) ($data['amount'] ?? 0),
            'currency'                => strtoupper((string) ($data['currency'] ?? 'EUR')),
            'transacted_at'           => !empty($data['transacted_at']) ? (string) $data['transacted_at'] : date('Y-m-d H:i:s'),
            'description'             => !empty($data['description']) ? (string) $data['description'] : null,
            'reference'               => !empty($data['reference']) ? (string) $data['reference'] : null,
            'counterparty'            => !empty($data['counterparty']) ? (string) $data['counterparty'] : null,
            'reconciled_status'       => !empty($data['payment_id']) ? 'matched' : 'unmatched',
            'reconciled_by_user_id'   => isset($data['reconciled_by_user_id']) ? (int) $data['reconciled_by_user_id'] : null,
            'reconciled_at'           => !empty($data['payment_id']) ? date('Y-m-d H:i:s') : null,
            'reconciliation_notes'    => !empty($data['reconciliation_notes']) ? (string) $data['reconciliation_notes'] : null,
            'raw_payload'             => $payload,
        ]);
    }

    public function linkTransactionToPayment(int $transactionId, int $paymentId, ?int $userId = null, ?string $notes = null): void
    {
        $payment = $this->db->fetch('SELECT id FROM payments WHERE id = ?', [$paymentId]);
        if (!$payment) {
            throw new \RuntimeException('Payment not found.');
        }

        $transaction = $this->db->fetch('SELECT id FROM payment_transactions WHERE id = ?', [$transactionId]);
        if (!$transaction) {
            throw new \RuntimeException('Transaction not found.');
        }

        $this->db->update('payment_transactions', [
            'payment_id'            => $paymentId,
            'reconciled_status'     => 'matched',
            'reconciled_by_user_id' => $userId,
            'reconciled_at'         => date('Y-m-d H:i:s'),
            'reconciliation_notes'  => $notes,
        ], 'id = ?', [$transactionId]);
    }

    public function unlinkTransactionFromPayment(int $transactionId, ?int $userId = null, ?string $notes = null): void
    {
        $transaction = $this->db->fetch('SELECT id FROM payment_transactions WHERE id = ?', [$transactionId]);
        if (!$transaction) {
            throw new \RuntimeException('Transaction not found.');
        }

        $this->db->update('payment_transactions', [
            'payment_id'            => null,
            'reconciled_status'     => 'unmatched',
            'reconciled_by_user_id' => $userId,
            'reconciled_at'         => date('Y-m-d H:i:s'),
            'reconciliation_notes'  => $notes,
        ], 'id = ?', [$transactionId]);
    }
}
