<?php

namespace Cruinn\Module\Payments\Services;

use Cruinn\Database;

// Last edit: 2026-06-11 13:39 UTC.

class PaymentService
{
    private Database $db;
    private array $tableColumnCache = [];

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

    public function findPaymentById(int $paymentId): ?array
    {
        $row = $this->db->fetch('SELECT * FROM payments WHERE id = ?', [$paymentId]);
        return $row ?: null;
    }

    public function findPaymentBySource(string $sourceType, int $sourceId): ?array
    {
        $row = $this->db->fetch(
            'SELECT * FROM payments WHERE source_type = ? AND source_id = ? ORDER BY id DESC LIMIT 1',
            [$sourceType, $sourceId]
        );
        return $row ?: null;
    }

    public function findPaymentByTransactionId(string $transactionId): ?array
    {
        $tx = trim($transactionId);
        if ($tx === '') {
            return null;
        }

        $row = $this->db->fetch('SELECT * FROM payments WHERE transaction_id = ? ORDER BY id DESC LIMIT 1', [$tx]);
        return $row ?: null;
    }

    public function updatePaymentStatus(int $paymentId, string $status, ?string $notes = null, ?string $paidAt = null): void
    {
        $allowed = ['pending', 'completed', 'failed', 'refunded'];
        if (!in_array($status, $allowed, true)) {
            throw new \InvalidArgumentException('Invalid payment status.');
        }

        $update = ['status' => $status];
        if ($notes !== null) {
            $update['notes'] = $notes;
        }
        if ($paidAt !== null && $paidAt !== '') {
            $update['paid_at'] = $paidAt;
        }

        $this->db->update('payments', $update, 'id = ?', [$paymentId]);
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
        if (!$this->tableExists('payment_transactions')) {
            return 0;
        }

        $payload = $data['raw_payload'] ?? null;
        if (is_array($payload)) {
            $payload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } elseif ($payload !== null) {
            $payload = (string) $payload;
        }

        $insert = [];

        if ($this->tableHasColumn('payment_transactions', 'payment_id')) {
            $insert['payment_id'] = isset($data['payment_id']) ? (int) $data['payment_id'] : null;
        }
        if ($this->tableHasColumn('payment_transactions', 'batch_id')) {
            $insert['batch_id'] = isset($data['batch_id']) ? (int) $data['batch_id'] : null;
        }

        $source = (string) ($data['source'] ?? 'manual');
        if ($this->tableHasColumn('payment_transactions', 'source')) {
            $insert['source'] = $source;
        } elseif ($this->tableHasColumn('payment_transactions', 'source_type')) {
            $insert['source_type'] = $source;
        }

        $external = !empty($data['external_transaction_id']) ? (string) $data['external_transaction_id'] : null;
        if ($this->tableHasColumn('payment_transactions', 'external_transaction_id')) {
            $insert['external_transaction_id'] = $external;
        } elseif ($this->tableHasColumn('payment_transactions', 'gateway_ref')) {
            $insert['gateway_ref'] = $external;
        }

        if ($this->tableHasColumn('payment_transactions', 'gateway')) {
            $insert['gateway'] = !empty($data['gateway']) ? (string) $data['gateway'] : null;
        }
        if ($this->tableHasColumn('payment_transactions', 'direction')) {
            $insert['direction'] = (($data['direction'] ?? 'credit') === 'debit') ? 'debit' : 'credit';
        }
        if ($this->tableHasColumn('payment_transactions', 'amount')) {
            $insert['amount'] = (float) ($data['amount'] ?? 0);
        }
        if ($this->tableHasColumn('payment_transactions', 'currency')) {
            $insert['currency'] = strtoupper((string) ($data['currency'] ?? 'EUR'));
        }

        $transactedAt = !empty($data['transacted_at']) ? (string) $data['transacted_at'] : date('Y-m-d H:i:s');
        if ($this->tableHasColumn('payment_transactions', 'transacted_at')) {
            $insert['transacted_at'] = $transactedAt;
        }
        if ($this->tableHasColumn('payment_transactions', 'description')) {
            $insert['description'] = !empty($data['description']) ? (string) $data['description'] : null;
        }
        if ($this->tableHasColumn('payment_transactions', 'reference')) {
            $insert['reference'] = !empty($data['reference']) ? (string) $data['reference'] : null;
        }
        if ($this->tableHasColumn('payment_transactions', 'counterparty')) {
            $insert['counterparty'] = !empty($data['counterparty']) ? (string) $data['counterparty'] : null;
        }

        if ($this->tableHasColumn('payment_transactions', 'reconciled_status')) {
            $insert['reconciled_status'] = !empty($data['payment_id']) ? 'matched' : 'unmatched';
        }
        if ($this->tableHasColumn('payment_transactions', 'reconciled_by_user_id')) {
            $insert['reconciled_by_user_id'] = isset($data['reconciled_by_user_id']) ? (int) $data['reconciled_by_user_id'] : null;
        }
        if ($this->tableHasColumn('payment_transactions', 'reconciled_at')) {
            $insert['reconciled_at'] = !empty($data['payment_id']) ? date('Y-m-d H:i:s') : null;
        }
        if ($this->tableHasColumn('payment_transactions', 'reconciliation_notes')) {
            $insert['reconciliation_notes'] = !empty($data['reconciliation_notes']) ? (string) $data['reconciliation_notes'] : null;
        }
        if ($this->tableHasColumn('payment_transactions', 'raw_payload')) {
            $insert['raw_payload'] = $payload;
        }

        if ($this->tableHasColumn('payment_transactions', 'created_at') && !$this->tableHasColumn('payment_transactions', 'transacted_at')) {
            $insert['created_at'] = $transactedAt;
        }

        if (empty($insert)) {
            return 0;
        }

        return (int) $this->db->insert('payment_transactions', $insert);
    }

    public function linkTransactionToPayment(int $transactionId, int $paymentId, ?int $userId = null, ?string $notes = null): void
    {
        if (!$this->tableHasColumn('payment_transactions', 'payment_id')) {
            throw new \RuntimeException('payment_transactions.payment_id is missing. Apply payments migrations on this instance.');
        }

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
        if (!$this->tableHasColumn('payment_transactions', 'payment_id')) {
            throw new \RuntimeException('payment_transactions.payment_id is missing. Apply payments migrations on this instance.');
        }

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

    public function findTransactionById(int $transactionId): ?array
    {
        if (!$this->tableExists('payment_transactions')) {
            return null;
        }

        $row = $this->db->fetch('SELECT * FROM payment_transactions WHERE id = ?', [$transactionId]);
        return $row ?: null;
    }

    public function listUnmatchedTransactions(int $limit = 200): array
    {
        if (!$this->tableExists('payment_transactions')) {
            return [];
        }

        $safeLimit = max(1, min($limit, 500));

        $select = [
            'id',
            $this->tableHasColumn('payment_transactions', 'payment_id') ? 'payment_id' : 'NULL AS payment_id',
            $this->tableHasColumn('payment_transactions', 'batch_id') ? 'batch_id' : 'NULL AS batch_id',
            $this->tableHasColumn('payment_transactions', 'source')
                ? 'source'
                : ($this->tableHasColumn('payment_transactions', 'source_type') ? 'source_type AS source' : "'legacy' AS source"),
            $this->tableHasColumn('payment_transactions', 'external_transaction_id')
                ? 'external_transaction_id'
                : ($this->tableHasColumn('payment_transactions', 'gateway_ref') ? 'gateway_ref AS external_transaction_id' : 'NULL AS external_transaction_id'),
            $this->tableHasColumn('payment_transactions', 'gateway') ? 'gateway' : 'NULL AS gateway',
            $this->tableHasColumn('payment_transactions', 'direction') ? 'direction' : "'credit' AS direction",
            $this->tableHasColumn('payment_transactions', 'amount') ? 'amount' : '0 AS amount',
            $this->tableHasColumn('payment_transactions', 'currency') ? 'currency' : "'EUR' AS currency",
            $this->tableHasColumn('payment_transactions', 'transacted_at')
                ? 'transacted_at'
                : ($this->tableHasColumn('payment_transactions', 'created_at') ? 'created_at AS transacted_at' : 'NOW() AS transacted_at'),
            $this->tableHasColumn('payment_transactions', 'description') ? 'description' : 'NULL AS description',
            $this->tableHasColumn('payment_transactions', 'reference') ? 'reference' : 'NULL AS reference',
            $this->tableHasColumn('payment_transactions', 'counterparty') ? 'counterparty' : 'NULL AS counterparty',
            $this->tableHasColumn('payment_transactions', 'reconciled_status') ? 'reconciled_status' : "'unmatched' AS reconciled_status",
            $this->tableHasColumn('payment_transactions', 'created_at') ? 'created_at' : 'NOW() AS created_at',
        ];

        $where = '1=1';
        if ($this->tableHasColumn('payment_transactions', 'reconciled_status')) {
            $where = "reconciled_status = 'unmatched'";
        } elseif ($this->tableHasColumn('payment_transactions', 'payment_id')) {
            $where = 'payment_id IS NULL';
        }

        $orderBy = $this->tableHasColumn('payment_transactions', 'transacted_at')
            ? 'transacted_at DESC, id DESC'
            : ($this->tableHasColumn('payment_transactions', 'created_at') ? 'created_at DESC, id DESC' : 'id DESC');

        return $this->db->fetchAll(
            "SELECT " . implode(', ', $select) . "
             FROM payment_transactions
             WHERE {$where}
             ORDER BY {$orderBy}
             LIMIT {$safeLimit}"
        );
    }

    public function listTransactionsForPayment(int $paymentId, int $limit = 100): array
    {
        if (!$this->tableExists('payment_transactions') || !$this->tableHasColumn('payment_transactions', 'payment_id')) {
            return [];
        }

        $safeLimit = max(1, min($limit, 500));
        return $this->db->fetchAll(
            "SELECT id, payment_id, batch_id, source, external_transaction_id, gateway,
                    direction, amount, currency, transacted_at, description,
                    reference, counterparty, reconciled_status, reconciliation_notes, created_at
             FROM payment_transactions
             WHERE payment_id = ?
             ORDER BY transacted_at DESC, id DESC
             LIMIT {$safeLimit}",
            [$paymentId]
        );
    }

    public function markTransactionIgnored(int $transactionId, ?int $userId = null, ?string $notes = null): void
    {
        if (!$this->tableHasColumn('payment_transactions', 'reconciled_status')) {
            throw new \RuntimeException('payment_transactions.reconciled_status is missing. Apply payments migrations on this instance.');
        }

        $transaction = $this->db->fetch('SELECT id FROM payment_transactions WHERE id = ?', [$transactionId]);
        if (!$transaction) {
            throw new \RuntimeException('Transaction not found.');
        }

        $this->db->update('payment_transactions', [
            'payment_id'            => null,
            'reconciled_status'     => 'ignored',
            'reconciled_by_user_id' => $userId,
            'reconciled_at'         => date('Y-m-d H:i:s'),
            'reconciliation_notes'  => $notes,
        ], 'id = ?', [$transactionId]);
    }

    private function tableExists(string $table): bool
    {
        if (!array_key_exists($table, $this->tableColumnCache)) {
            try {
                $rows = $this->db->fetchAll(
                    'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                    [$table]
                );
                $cols = [];
                foreach ($rows as $row) {
                    $name = strtolower((string) ($row['COLUMN_NAME'] ?? ''));
                    if ($name !== '') {
                        $cols[$name] = true;
                    }
                }
                $this->tableColumnCache[$table] = $cols;
            } catch (\Throwable) {
                $this->tableColumnCache[$table] = [];
            }
        }

        return !empty($this->tableColumnCache[$table]);
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        if (!$this->tableExists($table)) {
            return false;
        }
        return isset($this->tableColumnCache[$table][strtolower($column)]);
    }
}
