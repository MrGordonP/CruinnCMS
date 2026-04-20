<?php
/**
 * Finance Admin — Entry Form (new & edit)
 */
$isEdit = !empty($entry['id']);
?>

<div class="admin-section">
    <div class="admin-section-header">
        <h1><?= $isEdit ? 'Edit Entry' : 'New Entry' ?></h1>
    </div>

    <div class="admin-card">
        <form method="post" action="<?= $isEdit ? '/admin/organisation/finance/update/' . (int) $entry['id'] : '/admin/organisation/finance/create' ?>" class="admin-form">
            <input type="hidden" name="csrf_token" value="<?= \Cruinn\CSRF::getToken() ?>">

            <div class="form-row">
                <div class="form-group">
                    <label for="period_id">Period <span class="required">*</span></label>
                    <select name="period_id" id="period_id" class="form-input" required>
                        <?php foreach ($periods as $p): ?>
                            <option value="<?= (int) $p['id'] ?>"
                                <?= ($entry['period_id'] ?? $periodId) == $p['id'] ? 'selected' : '' ?>>
                                <?= $this->escape($p['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="type">Type <span class="required">*</span></label>
                    <select name="type" id="type" class="form-input" required>
                        <option value="income"  <?= ($entry['type'] ?? '') === 'income'  ? 'selected' : '' ?>>Income</option>
                        <option value="expense" <?= ($entry['type'] ?? '') === 'expense' ? 'selected' : '' ?>>Expense</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="category_id">Category <span class="required">*</span></label>
                    <select name="category_id" id="category_id" class="form-input" required>
                        <optgroup label="Income">
                            <?php foreach ($categories as $c): if ($c['type'] !== 'income') continue; ?>
                                <option value="<?= (int) $c['id'] ?>"
                                    <?= ($entry['category_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>>
                                    <?= $this->escape($c['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Expense">
                            <?php foreach ($categories as $c): if ($c['type'] !== 'expense') continue; ?>
                                <option value="<?= (int) $c['id'] ?>"
                                    <?= ($entry['category_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>>
                                    <?= $this->escape($c['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="entry_date">Date <span class="required">*</span></label>
                    <input type="date" name="entry_date" id="entry_date" class="form-input"
                           value="<?= $this->escape($entry['entry_date'] ?? date('Y-m-d')) ?>" required>
                </div>

                <div class="form-group">
                    <label for="amount">Amount <span class="required">*</span></label>
                    <input type="number" name="amount" id="amount" class="form-input input-narrow"
                           value="<?= $this->escape($entry['amount'] ?? '') ?>"
                           min="0.01" step="0.01" required placeholder="0.00">
                </div>

                <div class="form-group">
                    <label for="currency">Currency</label>
                    <input type="text" name="currency" id="currency" class="form-input input-narrow"
                           value="<?= $this->escape($entry['currency'] ?? 'EUR') ?>"
                           maxlength="3" style="text-transform:uppercase">
                </div>
            </div>

            <div class="form-group form-group-full">
                <label for="description">Description <span class="required">*</span></label>
                <input type="text" name="description" id="description" class="form-input"
                       value="<?= $this->escape($entry['description'] ?? '') ?>" required maxlength="500">
            </div>

            <div class="form-group">
                <label for="reference">Reference <span class="form-hint">(cheque no., receipt, etc.)</span></label>
                <input type="text" name="reference" id="reference" class="form-input"
                       value="<?= $this->escape($entry['reference'] ?? '') ?>" maxlength="100">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save Changes' : 'Add Entry' ?></button>
                <a href="/admin/organisation/finance<?= $periodId ? '?period_id=' . $periodId : '' ?>" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
