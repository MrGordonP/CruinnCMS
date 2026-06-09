<?php

namespace Cruinn\Module\Payments\Controllers;

use Cruinn\Auth;
use Cruinn\Controllers\BaseController;

class PaymentAdminController extends BaseController
{
    public function indexPayments(): void
    {
        Auth::requireAdmin();

        $gatewayFilter = trim((string) $this->query('gateway', ''));
        $statusFilter  = (string) $this->query('status', '');
        $yearFilter    = (string) $this->query('year', '');
        $memberSearch  = trim((string) $this->query('q', ''));
        $sort          = (string) $this->query('sort', 'paid_at');
        $dir           = strtoupper((string) $this->query('dir', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        $selectedId    = (int) $this->query('payment', 0);

        $allowedSorts = [
            'paid_at'    => 'py.paid_at',
            'amount'     => 'py.amount',
            'gateway'    => 'py.gateway',
            'status'     => 'py.status',
            'member'     => 'm.surnames',
            'tx'         => 'py.transaction_id',
        ];
        $orderBy = $allowedSorts[$sort] ?? 'py.paid_at';

        $allowedStatuses = ['pending', 'completed', 'failed', 'refunded'];

        $where  = ['1=1'];
        $params = [];

        if ($gatewayFilter !== '') {
            $where[]  = 'py.gateway = ?';
            $params[] = $gatewayFilter;
        }
        if (in_array($statusFilter, $allowedStatuses, true)) {
            $where[]  = 'py.status = ?';
            $params[] = $statusFilter;
        }
        if ($yearFilter !== '') {
            $where[]  = 'YEAR(py.paid_at) = ?';
            $params[] = (int) $yearFilter;
        }
        if ($memberSearch !== '') {
            $where[]  = '(m.forenames LIKE ? OR m.surnames LIKE ? OR m.email LIKE ? OR m.membership_number LIKE ?)';
            $like     = '%' . $memberSearch . '%';
            $params   = array_merge($params, [$like, $like, $like, $like]);
        }

        $whereClause = implode(' AND ', $where);

        $payments = $this->db->fetchAll(
            "SELECT py.id, py.transaction_id, py.gateway, py.amount, py.currency,
                    py.status, py.paid_at, py.notes, py.subscription_id,
                    s.period_start, s.period_end, s.verification_status,
                    m.id AS member_id, m.forenames, m.surnames, m.membership_number,
                    p.name AS plan_name
             FROM payments py
             LEFT JOIN membership_subscriptions s ON s.id = py.subscription_id
             LEFT JOIN members m ON m.id = s.member_id
             LEFT JOIN membership_plans p ON p.id = s.plan_id
             WHERE $whereClause
             ORDER BY $orderBy $dir, py.id DESC
             LIMIT 500",
            $params
        );

        $selectedPayment = null;
        if ($selectedId > 0) {
            foreach ($payments as $pay) {
                if ((int) $pay['id'] === $selectedId) { $selectedPayment = $pay; break; }
            }
            if (!$selectedPayment) {
                $selectedPayment = $this->db->fetch(
                    "SELECT py.id, py.transaction_id, py.gateway, py.amount, py.currency,
                            py.status, py.paid_at, py.notes, py.subscription_id,
                            s.period_start, s.period_end, s.verification_status,
                            m.id AS member_id, m.forenames, m.surnames, m.membership_number,
                            p.name AS plan_name
                     FROM payments py
                     LEFT JOIN membership_subscriptions s ON s.id = py.subscription_id
                     LEFT JOIN members m ON m.id = s.member_id
                     LEFT JOIN membership_plans p ON p.id = s.plan_id
                     WHERE py.id = ?",
                    [$selectedId]
                );
            }
        }

        $availableYears = $this->db->fetchAll(
            'SELECT DISTINCT YEAR(paid_at) AS y FROM payments ORDER BY y DESC'
        );
        $availableGateways = $this->db->fetchAll(
            "SELECT DISTINCT gateway FROM payments WHERE gateway IS NOT NULL AND gateway != '' ORDER BY gateway"
        );

        $this->renderAdmin('admin/payments/index', [
            'title'             => 'Payments',
            'payments'          => $payments,
            'selectedId'        => $selectedId,
            'selectedPayment'   => $selectedPayment,
            'availableYears'    => array_column($availableYears, 'y'),
            'availableGateways' => array_column($availableGateways, 'gateway'),
            'allowedStatuses'   => $allowedStatuses,
            'filters'           => compact('gatewayFilter', 'statusFilter', 'yearFilter', 'memberSearch', 'sort', 'dir'),
            'breadcrumbs'       => [['Admin', '/admin'], ['Payments']],
        ]);
    }
}
