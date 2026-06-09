<?php \Cruinn\Template::requireCss('admin-acp.css'); ?>

<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
    <div>
        <h1 style="margin:0;">Import Members — Match Plan Values</h1>
        <p class="text-muted" style="margin:0.25rem 0 0;">
            Your CSV contains plan values that need to be matched to plans in this system.
            Unmatched rows will be imported without a plan assignment.
        </p>
    </div>
    <a class="btn btn-outline" href="<?= url('/admin/membership/import') ?>">Start Over</a>
</div>

<form method="post" action="<?= url('/admin/membership/import/map-plans') ?>">
    <?= csrf_field() ?>

    <div class="card" style="border:1px solid #e5e7eb;border-radius:8px;background:#fff;padding:1.25rem;margin-bottom:1.5rem;">
        <h2 style="margin:0 0 1rem;font-size:1rem;">Plan Value Matching</h2>
        <table style="border-collapse:collapse;width:100%;font-size:0.875rem;">
            <thead>
                <tr style="background:#f9fafb;">
                    <th style="text-align:left;padding:0.5rem 0.75rem;border:1px solid #e5e7eb;width:45%;">Value in CSV</th>
                    <th style="text-align:left;padding:0.5rem 0.75rem;border:1px solid #e5e7eb;">Plan in system</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($uniquePlanValues as $csvValue): ?>
                <tr>
                    <td style="padding:0.5rem 0.75rem;border:1px solid #e5e7eb;">
                        <code style="background:#f3f4f6;padding:0.15rem 0.4rem;border-radius:4px;font-size:0.82rem;"><?= e($csvValue) ?></code>
                    </td>
                    <td style="padding:0.4rem 0.75rem;border:1px solid #e5e7eb;">
                        <select name="plan_map[<?= e($csvValue) ?>]" class="form-input" style="width:100%;max-width:400px;">
                            <option value="">— No plan / skip plan assignment —</option>
                            <?php foreach ($allPlans as $plan): ?>
                            <option value="<?= (int) $plan['id'] ?>">
                                <?= e($plan['name']) ?>
                                <?php if (!empty($plan['subject_title'])): ?>(<?= e($plan['subject_title']) ?>)<?php endif; ?>
                                <?php if (empty($plan['is_active'])): ?>[inactive]<?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div style="display:flex;gap:0.75rem;">
        <button class="btn btn-primary" type="submit">Run Import</button>
        <a class="btn btn-outline" href="<?= url('/admin/membership/import') ?>">Cancel</a>
    </div>
</form>
