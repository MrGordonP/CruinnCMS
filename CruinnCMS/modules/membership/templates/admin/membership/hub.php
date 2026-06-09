<?php
\Cruinn\Template::requireCss('admin-panel-layout.css');
$GLOBALS['admin_flush_layout'] = true;

$status = is_array($members ?? null) ? $members : [];
?>

<div class="panel-layout no-detail" id="membership-hub-layout">
<div class="pl-panel pl-panel-left">
    <div class="pl-panel-header"><h3>Membership</h3></div>
    <div class="pl-panel-body" style="padding:0">
        <div class="pl-nav-section">Workspace</div>
        <a class="pl-nav-item active" href="<?= url('/admin/membership') ?>">Hub</a>
        <a class="pl-nav-item" href="<?= url('/admin/membership/members') ?>">Members</a>
        <a class="pl-nav-item" href="<?= url('/admin/membership/plans') ?>">Plans</a>
        <a class="pl-nav-item" href="<?= url('/admin/membership/import') ?>">Import</a>
        <div class="pl-nav-section">Forms</div>
        <a class="pl-nav-item" href="<?= url('/admin/membership/forms') ?>">Forms and Responses</a>
    </div>
</div>

<div class="pl-main">
    <div class="pl-main-toolbar">
        <span class="pl-main-title">Membership Hub</span>
    </div>

    <div class="pl-main-scroll">
        <div class="membership-hub-grid">
            <section class="membership-hub-card">
                <h2>Membership Lists</h2>
                <p class="text-muted">Open the three-panel member workspace for member records, subscriptions, and payments.</p>
                <div class="membership-hub-stats">
                    <span>Total <?= (int) ($status['total'] ?? 0) ?></span>
                    <span>Active <?= (int) ($status['active'] ?? 0) ?></span>
                    <span>Unverified <?= (int) ($status['unverified'] ?? 0) ?></span>
                    <span>Lapsed <?= (int) ($status['lapsed'] ?? 0) ?></span>
                </div>
                <div class="membership-hub-actions">
                    <a href="<?= url('/admin/membership/members') ?>" class="btn btn-primary">Open Members Workspace</a>
                    <a href="<?= url('/admin/membership/members/new') ?>" class="btn btn-outline">New Member</a>
                </div>
            </section>

            <section class="membership-hub-card">
                <h2>Subscriptions and Plans</h2>
                <p class="text-muted">Manage membership plan catalogue and subscription/payment records.</p>
                <div class="membership-hub-stats">
                    <span>Plans <?= (int) ($plansCount ?? 0) ?></span>
                    <span>Subscriptions <?= (int) ($subscriptionsCount ?? 0) ?></span>
                    <span>Payments <?= (int) ($paymentsCount ?? 0) ?></span>
                </div>
                <div class="membership-hub-actions">
                    <a href="<?= url('/admin/membership/plans/new-group') ?>" class="btn btn-primary">Create Group</a>
                    <a href="<?= url('/admin/membership/plans/new-tier') ?>" class="btn btn-outline">Create Tier</a>
                    <a href="<?= url('/admin/membership/members') ?>" class="btn btn-outline">Subscription Workspace</a>
                </div>
            </section>

            <section class="membership-hub-card">
                <h2>Forms and Responses</h2>
                <p class="text-muted">Manage forms and responses for membership, linked by Subject associations.</p>
                <div class="membership-hub-stats">
                    <span>Forms <?= (int) ($formsCount ?? 0) ?></span>
                    <span>Responses <?= (int) ($responsesCount ?? 0) ?></span>
                    <span>Pending <?= (int) ($pendingResponsesCount ?? 0) ?></span>
                </div>
                <div class="membership-hub-actions">
                    <a href="<?= url('/admin/membership/forms') ?>" class="btn btn-primary">Open Forms Workspace</a>
                </div>
            </section>

            <section class="membership-hub-card">
                <h2>Import</h2>
                <p class="text-muted">Import and map member CSV files into membership records.</p>
                <div class="membership-hub-actions">
                    <a href="<?= url('/admin/membership/import') ?>" class="btn btn-primary">Import Members</a>
                </div>
            </section>
        </div>
    </div>
</div>
</div><!-- /panel-layout -->

<style>
#membership-hub-layout .pl-main-scroll {
    padding: 1rem;
}

.membership-hub-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 1rem;
}

.membership-hub-card {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    background: #fff;
    padding: 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.membership-hub-card h2 {
    margin: 0;
    font-size: 1.05rem;
}

.membership-hub-card p {
    margin: 0;
    font-size: 0.9rem;
}

.membership-hub-stats {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.4rem;
    font-size: 0.82rem;
    color: #475569;
}

.membership-hub-stats span {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 0.35rem 0.5rem;
}

.membership-hub-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: auto;
}

@media (max-width: 760px) {
    .membership-hub-grid {
        grid-template-columns: 1fr;
    }

    .membership-hub-actions .btn {
        width: 100%;
        text-align: center;
    }
}
</style>
