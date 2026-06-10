<?php
\Cruinn\Template::requireCss('admin-acp.css');
\Cruinn\Template::requireCss('admin-panel-layout.css');

$memberA   = $memberA ?? [];
$memberB   = $memberB ?? [];
$subsA     = $subsA ?? [];
$subsB     = $subsB ?? [];

$nameA = trim(($memberA['forenames'] ?? '') . ' ' . ($memberA['surnames'] ?? ''));
$nameB = trim(($memberB['forenames'] ?? '') . ' ' . ($memberB['surnames'] ?? ''));
$idA   = (int) ($memberA['id'] ?? 0);
$idB   = (int) ($memberB['id'] ?? 0);

$subCount = static function (array $subs): string {
    $n = count($subs);
    return $n === 1 ? '1 subscription' : $n . ' subscriptions';
};
?>

<div style="max-width:860px;margin:2rem auto;padding:0 1rem;">

    <h2 style="font-size:1.15rem;font-weight:700;margin:0 0 0.25rem;">Merge Member Accounts</h2>
    <p style="color:#64748b;font-size:0.875rem;margin:0 0 1.75rem;">
        Select which account to keep as the <strong>primary</strong>. All subscriptions and payment records from
        the other account will be moved to the primary. The secondary account will then be deleted.
    </p>

    <form method="post" action="<?= url('/admin/membership/members/merge') ?>">
        <?= csrf_field() ?>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.5rem;">

            <?php foreach ([['member' => $memberA, 'subs' => $subsA, 'value' => $idA, 'other' => $idB],
                             ['member' => $memberB, 'subs' => $subsB, 'value' => $idB, 'other' => $idA]] as $i => $card):
                $m    = $card['member'];
                $name = trim(($m['forenames'] ?? '') . ' ' . ($m['surnames'] ?? ''));
                $val  = $card['value'];
                $radioId = 'primary_' . $val;
            ?>
            <label for="<?= $radioId ?>" style="display:block;cursor:pointer;">
                <div style="border:2px solid #e2e8f0;border-radius:0.5rem;padding:1rem;transition:border-color 0.15s;"
                     class="merge-card" data-radio="<?= $radioId ?>">
                    <div style="display:flex;align-items:center;gap:0.6rem;margin-bottom:0.75rem;">
                        <input type="radio" id="<?= $radioId ?>" name="primary_id"
                               value="<?= $val ?>"<?= $i === 0 ? ' checked' : '' ?> required
                               style="width:1rem;height:1rem;flex-shrink:0;"
                               onchange="document.querySelector('[name=secondary_id]').value=<?= $card['other'] ?>;">
                        <strong style="font-size:0.95rem;"><?= e($name !== '' ? $name : '(unnamed)') ?></strong>
                        <span style="font-size:0.75rem;color:#94a3b8;">ID #<?= $val ?></span>
                    </div>
                    <dl style="display:grid;grid-template-columns:max-content 1fr;gap:0.2rem 0.75rem;font-size:0.82rem;margin:0;">
                        <dt style="color:#64748b;">Email</dt>
                        <dd style="margin:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= e((string)($m['email'] ?? '')) ?>"><?= e((string)($m['email'] ?? '—')) ?></dd>
                        <dt style="color:#64748b;">Mbr #</dt>
                        <dd style="margin:0;"><?= e((string)($m['membership_number'] ?? '—')) ?></dd>
                        <dt style="color:#64748b;">Status</dt>
                        <dd style="margin:0;"><?= e(ucfirst((string)($m['status'] ?? '—'))) ?></dd>
                        <dt style="color:#64748b;">Organisation</dt>
                        <dd style="margin:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e((string)($m['organisation'] ?? '—')) ?></dd>
                        <dt style="color:#64748b;">Joined</dt>
                        <dd style="margin:0;"><?= e(!empty($m['joined_at']) ? substr((string)$m['joined_at'], 0, 10) : '—') ?></dd>
                        <dt style="color:#64748b;">Subscriptions</dt>
                        <dd style="margin:0;"><?= $subCount($card['subs']) ?></dd>
                    </dl>
                    <?php if (!empty($card['subs'])): ?>
                    <ul style="margin:0.75rem 0 0;padding:0 0 0 1rem;font-size:0.78rem;color:#475569;">
                        <?php foreach ($card['subs'] as $s): ?>
                        <li style="margin-bottom:0.2rem;">
                            <?= e(($s['period_start'] ?? '') . ' – ' . ($s['period_end'] ?? '')) ?>
                            <?php if (!empty($s['plan_name'])): ?> · <?= e($s['plan_name']) ?><?php endif; ?>
                            · <?= e($s['currency'] ?? 'EUR') ?> <?= number_format((float)($s['amount'] ?? 0), 2) ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </label>
            <?php endforeach; ?>

        </div>

        <!-- secondary_id is kept in sync by the radio onchange handlers above; default to B -->
        <input type="hidden" name="secondary_id" value="<?= $idB ?>">

        <div style="display:flex;align-items:center;gap:0.75rem;">
            <button type="submit" class="btn btn-primary"
                    data-confirm="Merge these two accounts? The secondary account will be permanently deleted.">
                Confirm Merge
            </button>
            <a href="<?= url('/admin/membership/members') ?>" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

<style>
.merge-card { transition: border-color 0.15s, box-shadow 0.15s; }
label:has(input[type=radio]:checked) .merge-card {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37,99,235,0.12);
}
</style>
