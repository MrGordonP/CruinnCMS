<?php
    $isNew = empty($form['id']);
\Cruinn\Template::requireCss('admin-forms.css');
    $settings = is_string($form['settings'] ?? '') ? json_decode($form['settings'] ?? '{}', true) : ($form['settings'] ?? []);
?>
<div class="admin-form-edit">
    <h1><?= $isNew ? 'New Form' : 'Edit: ' . e($form['title']) ?></h1>

    <?php if (!empty($errors)): ?>
    <div class="flash flash-error" role="alert">
        <ul>
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- ── Form Metadata ── -->
    <form method="post" action="<?= $isNew ? '/admin/forms' : '/admin/forms/' . (int)$form['id'] ?>" class="form-meta">
        <?= csrf_field() ?>

        <div class="form-row">
            <div class="form-group form-group-half">
                <label for="title">Title <span class="required">*</span></label>
                <input type="text" id="title" name="title" required
                       value="<?= e($form['title'] ?? '') ?>" class="form-input"
                       placeholder="e.g. Membership Application">
            </div>
            <div class="form-group form-group-half">
                <label for="slug">Slug</label>
                <input type="text" id="slug" name="slug"
                       value="<?= e($form['slug'] ?? '') ?>" class="form-input"
                       placeholder="auto-generated from title">
            </div>
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" class="form-input" rows="3"
                      placeholder="Displayed above the form fields"><?= e($form['description'] ?? '') ?></textarea>
        </div>

        <div class="form-row">
            <div class="form-group form-group-third">
                <label for="form_type">Type</label>
                <select id="form_type" name="form_type" class="form-input">
                    <option value="general"                  <?= ($form['form_type'] ?? '') === 'general' ? 'selected' : '' ?>>General</option>
                    <option value="membership_application"   <?= ($form['form_type'] ?? '') === 'membership_application' ? 'selected' : '' ?>>Membership Application</option>
                    <option value="event_registration"       <?= ($form['form_type'] ?? '') === 'event_registration' ? 'selected' : '' ?>>Event Registration</option>
                    <option value="survey"                   <?= ($form['form_type'] ?? '') === 'survey' ? 'selected' : '' ?>>Survey</option>
                    <option value="feedback"                 <?= ($form['form_type'] ?? '') === 'feedback' ? 'selected' : '' ?>>Feedback</option>
                </select>
            </div>
            <div class="form-group form-group-third">
                <label for="status">Status</label>
                <select id="status" name="status" class="form-input">
                    <option value="draft"     <?= ($form['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="published" <?= ($form['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option>
                    <option value="closed"    <?= ($form['status'] ?? '') === 'closed' ? 'selected' : '' ?>>Closed</option>
                </select>
            </div>
            <div class="form-group form-group-third">
                <label for="max_submissions">Max Submissions</label>
                <input type="number" id="max_submissions" name="max_submissions"
                       value="<?= (int)($settings['max_submissions'] ?? 0) ?: '' ?>" class="form-input"
                       placeholder="Unlimited" min="0">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group form-group-half">
                <label for="notification_email">Notification Email</label>
                <input type="email" id="notification_email" name="notification_email"
                       value="<?= e($settings['notification_email'] ?? '') ?>" class="form-input"
                       placeholder="Receive email on each submission">
            </div>
            <div class="form-group form-group-half">
                <label for="redirect_url">Redirect After Submit</label>
                <input type="text" id="redirect_url" name="redirect_url"
                       value="<?= e($settings['redirect_url'] ?? '') ?>" class="form-input"
                       placeholder="/thank-you (optional)">
            </div>
        </div>

        <div class="form-group">
            <label for="success_message">Success Message</label>
            <input type="text" id="success_message" name="success_message"
                   value="<?= e($settings['success_message'] ?? '') ?>" class="form-input"
                   placeholder="Thank you! Your submission has been received.">
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="require_login" value="1"
                           <?= !empty($settings['require_login']) ? 'checked' : '' ?>>
                    Require login to submit
                </label>
            </div>
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="require_approval" value="1"
                           <?= !empty($settings['require_approval']) ? 'checked' : '' ?>>
                    Require admin approval
                </label>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $isNew ? 'Create Form' : 'Save Changes' ?></button>
            <?php if (!$isNew): ?>
                <a href="/admin/forms/<?= (int)$form['id'] ?>/submissions" class="btn">View Submissions</a>
                <a href="/admin/forms/<?= (int)$form['id'] ?>/export" class="btn">Export CSV</a>
                <?php if ($form['status'] === 'published'): ?>
                    <a href="/forms/<?= e($form['slug']) ?>" class="btn" target="_blank">Preview</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </form>

    <?php if (!$isNew): ?>
    <!-- ── Field Builder ── -->
    <hr>
    <div class="form-builder" data-form-id="<?= (int)$form['id'] ?>">
        <div class="admin-list-header">
            <h2>Form Fields</h2>
            <button type="button" class="btn btn-primary" id="btn-add-field">+ Add Field</button>
        </div>

        <div id="field-list">
            <?php if (empty($form['fields'])): ?>
                <p class="admin-empty">No fields yet. Click "Add Field" to start building your form.</p>
            <?php else: ?>
                <?php foreach ($form['fields'] as $field): ?>
                <div class="field-card" data-field-id="<?= (int)$field['id'] ?>">
                    <div class="field-card-header">
                        <span class="field-drag-handle" title="Drag to reorder">☰</span>
                        <strong><?= e($field['label']) ?></strong>
                        <code class="text-muted"><?= e($field['field_type']) ?></code>
                        <?php if (!empty($field['validation']['required'])): ?>
                            <span class="badge badge-warning">Required</span>
                        <?php endif; ?>
                        <span class="field-name text-muted"><?= e($field['name']) ?></span>
                        <div class="field-actions">
                            <button type="button" class="btn btn-small btn-edit-field">Edit</button>
                            <button type="button" class="btn btn-small btn-danger btn-delete-field">Delete</button>
                        </div>
                    </div>
                    <div class="field-card-body" style="display:none;">
                        <!-- Inline edit form populated by JS -->
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Add Field Modal ── -->
    <div id="field-modal" class="modal" style="display:none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="field-modal-title">Add Field</h3>
                <button type="button" class="modal-close" id="field-modal-close">&times;</button>
            </div>
            <form id="field-form">
                <div class="form-row">
                    <div class="form-group form-group-half">
                        <label for="field-label">Label <span class="required">*</span></label>
                        <input type="text" id="field-label" name="label" class="form-input" required>
                    </div>
                    <div class="form-group form-group-half">
                        <label for="field-name">Field Name</label>
                        <input type="text" id="field-name" name="name" class="form-input" placeholder="auto-generated">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group form-group-half">
                        <label for="field-type">Type</label>
                        <select id="field-type" name="field_type" class="form-input">
                            <option value="text">Text</option>
                            <option value="email">Email</option>
                            <option value="number">Number</option>
                            <option value="textarea">Textarea</option>
                            <option value="select">Dropdown</option>
                            <option value="radio">Radio Buttons</option>
                            <option value="checkbox">Single Checkbox</option>
                            <option value="checkbox_group">Checkbox Group</option>
                            <option value="date">Date</option>
                            <option value="file">File Upload</option>
                            <option value="heading">Heading (display only)</option>
                            <option value="paragraph">Paragraph (display only)</option>
                            <option value="hidden">Hidden</option>
                        </select>
                    </div>
                    <div class="form-group form-group-half">
                        <label for="field-placeholder">Placeholder</label>
                        <input type="text" id="field-placeholder" name="placeholder" class="form-input">
                    </div>
                </div>
                <div class="form-group">
                    <label for="field-help">Help Text</label>
                    <input type="text" id="field-help" name="help_text" class="form-input">
                </div>
                <div class="form-group" id="field-options-group">
                    <label for="field-options">Options <small class="text-muted">(one per line; use <code>value|Label</code> for custom values)</small></label>
                    <textarea id="field-options" name="options_raw" class="form-input" rows="5" placeholder="Option 1&#10;Option 2&#10;Option 3"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="field-required" name="required" value="1"> Required
                        </label>
                    </div>
                    <div class="form-group form-group-third">
                        <label for="field-min-length">Min Length</label>
                        <input type="number" id="field-min-length" name="min_length" class="form-input" min="0">
                    </div>
                    <div class="form-group form-group-third">
                        <label for="field-max-length">Max Length</label>
                        <input type="number" id="field-max-length" name="max_length" class="form-input" min="0">
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" id="field-save-btn">Add Field</button>
                    <button type="button" class="btn" id="field-cancel-btn">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Danger Zone ── -->
    <hr>
    <div class="danger-zone">
        <h3>Danger Zone</h3>
        <p>Deleting this form will permanently remove all fields and submissions.</p>
        <form method="post" action="/admin/forms/<?= (int)$form['id'] ?>/delete"
              onsubmit="return confirm('Delete this form and all its data? This cannot be undone.')">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-danger">Delete Form</button>
        </form>
    </div>
    <?php endif; ?>
</div>

<?php if (!$isNew): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const formId = <?= (int)$form['id'] ?>;
    const csrfToken = document.querySelector('input[name="_csrf"]')?.value || '';
    const fieldList = document.getElementById('field-list');
    const modal = document.getElementById('field-modal');
    const fieldForm = document.getElementById('field-form');
    const fieldTypeSelect = document.getElementById('field-type');
    const optionsGroup = document.getElementById('field-options-group');
    let editingFieldId = null;

    function toggleOptionsVisibility() {
        const needsOptions = ['select', 'radio', 'checkbox_group'].includes(fieldTypeSelect.value);
        optionsGroup.style.display = needsOptions ? '' : 'none';
    }
    fieldTypeSelect.addEventListener('change', toggleOptionsVisibility);
    toggleOptionsVisibility();

    // Open add modal
    document.getElementById('btn-add-field').addEventListener('click', function() {
        editingFieldId = null;
        document.getElementById('field-modal-title').textContent = 'Add Field';
        document.getElementById('field-save-btn').textContent = 'Add Field';
        fieldForm.reset();
        toggleOptionsVisibility();
        modal.style.display = '';
    });

    // Close modal
    document.getElementById('field-modal-close').addEventListener('click', () => modal.style.display = 'none');
    document.getElementById('field-cancel-btn').addEventListener('click', () => modal.style.display = 'none');

    // Submit field form (add or update)
    fieldForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const data = new FormData(fieldForm);
        data.append('_csrf', csrfToken);

        const url = editingFieldId
            ? '/admin/forms/' + formId + '/fields/' + editingFieldId
            : '/admin/forms/' + formId + '/fields';

        fetch(url, { method: 'POST', body: data })
            .then(r => r.json())
            .then(resp => {
                if (resp.error) { alert(resp.error); return; }
                location.reload();
            })
            .catch(() => alert('An error occurred.'));
    });

    // Edit field
    fieldList.addEventListener('click', function(e) {
        if (!e.target.classList.contains('btn-edit-field')) return;
        const card = e.target.closest('.field-card');
        editingFieldId = card.dataset.fieldId;

        // Pre-fill from card data attributes or fetch — for simplicity, use data from PHP render
        const fields = <?= json_encode($form['fields'] ?? []) ?>;
        const field = fields.find(f => f.id == editingFieldId);
        if (!field) return;

        document.getElementById('field-modal-title').textContent = 'Edit Field';
        document.getElementById('field-save-btn').textContent = 'Update Field';
        document.getElementById('field-label').value = field.label || '';
        document.getElementById('field-name').value = field.name || '';
        fieldTypeSelect.value = field.field_type || 'text';
        document.getElementById('field-placeholder').value = field.placeholder || '';
        document.getElementById('field-help').value = field.help_text || '';
        document.getElementById('field-required').checked = !!(field.validation && field.validation.required);
        document.getElementById('field-min-length').value = field.validation?.min_length || '';
        document.getElementById('field-max-length').value = field.validation?.max_length || '';

        if (field.options && Array.isArray(field.options)) {
            document.getElementById('field-options').value = field.options.map(o =>
                o.value !== o.label ? o.value + '|' + o.label : o.value
            ).join('\n');
        } else {
            document.getElementById('field-options').value = '';
        }

        toggleOptionsVisibility();
        modal.style.display = '';
    });

    // Delete field
    fieldList.addEventListener('click', function(e) {
        if (!e.target.classList.contains('btn-delete-field')) return;
        if (!confirm('Remove this field?')) return;
        const card = e.target.closest('.field-card');
        const fid = card.dataset.fieldId;

        const data = new FormData();
        data.append('_csrf', csrfToken);

        fetch('/admin/forms/' + formId + '/fields/' + fid + '/delete', { method: 'POST', body: data })
            .then(r => r.json())
            .then(resp => {
                if (resp.error) { alert(resp.error); return; }
                card.remove();
            })
            .catch(() => alert('An error occurred.'));
    });

    // Drag-and-drop reorder for fields
    (function initFieldDragDrop() {
        let dragCard = null;

        fieldList.addEventListener('dragstart', function(e) {
            const card = e.target.closest('.field-card');
            if (!card || !e.target.classList.contains('field-drag-handle')) { e.preventDefault(); return; }
            dragCard = card;
            card.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        });

        fieldList.addEventListener('dragover', function(e) {
            e.preventDefault();
            if (!dragCard) return;
            const target = e.target.closest('.field-card');
            if (!target || target === dragCard) return;
            const rect = target.getBoundingClientRect();
            const mid = rect.top + rect.height / 2;
            if (e.clientY < mid) {
                fieldList.insertBefore(dragCard, target);
            } else {
                fieldList.insertBefore(dragCard, target.nextSibling);
            }
        });

        fieldList.addEventListener('dragend', function() {
            if (!dragCard) return;
            dragCard.classList.remove('dragging');
            dragCard = null;

            // Save new order
            const cards = fieldList.querySelectorAll('.field-card');
            const items = Array.from(cards).map((c, i) => ({
                id: parseInt(c.dataset.fieldId),
                sort_order: i + 1
            }));

            fetch('/admin/forms/' + formId + '/fields/reorder', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ items: items, _csrf: csrfToken })
            });
        });

        // Make field cards draggable via handle
        fieldList.querySelectorAll('.field-drag-handle').forEach(h => {
            h.closest('.field-card').setAttribute('draggable', 'true');
        });
    })();
});
</script>
<?php endif; ?>
