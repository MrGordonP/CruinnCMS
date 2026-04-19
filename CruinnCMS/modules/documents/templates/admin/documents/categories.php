<?php
/**
 * Documents — Category Management
 */
?>

<div class="admin-section">
    <div class="admin-section-header">
        <h1>Document Categories</h1>
        <a href="/admin/documents" class="btn btn-secondary btn-sm">&larr; Back to Documents Admin</a>
    </div>

    <?php if ($flash = \Cruinn\Auth::getFlash('success')): ?>
        <div class="alert alert-success"><?= $this->escape($flash) ?></div>
    <?php endif; ?>
    <?php if ($flash = \Cruinn\Auth::getFlash('error')): ?>
        <div class="alert alert-error"><?= $this->escape($flash) ?></div>
    <?php endif; ?>

    <!-- Add category -->
    <div class="admin-card">
        <h2>New Category</h2>
        <form method="post" action="/admin/documents/categories" class="admin-form">
            <input type="hidden" name="csrf_token" value="<?= \Cruinn\CSRF::getToken() ?>">
            <div class="form-row">
                <div class="form-group">
                    <label for="name">Name <span class="required">*</span></label>
                    <input type="text" name="name" id="name" class="form-input" required>
                </div>
                <div class="form-group">
                    <label for="sort_order">Sort Order</label>
                    <input type="number" name="sort_order" id="sort_order" class="form-input input-narrow"
                           value="0" min="0" step="10">
                </div>
            </div>
            <div class="form-group">
                <label for="description">Description</label>
                <input type="text" name="description" id="description" class="form-input">
            </div>
            <button type="submit" class="btn btn-primary">Add Category</button>
        </form>
    </div>

    <!-- Existing categories -->
    <?php if (empty($categories)): ?>
        <p class="empty-state">No categories yet.</p>
    <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Slug</th>
                    <th>Description</th>
                    <th>Order</th>
                    <th>Documents</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($categories as $cat): ?>
                <tr>
                    <td><strong><?= $this->escape($cat['name']) ?></strong></td>
                    <td><code><?= $this->escape($cat['slug']) ?></code></td>
                    <td><?= $this->escape($cat['description'] ?? '—') ?></td>
                    <td><?= (int)$cat['sort_order'] ?></td>
                    <td><?= (int)$cat['document_count'] ?></td>
                    <td class="actions">
                        <button type="button" class="btn btn-xs btn-secondary"
                                onclick="document.getElementById('edit-cat-<?= (int)$cat['id'] ?>').style.display='block';this.style.display='none'">
                            Edit
                        </button>
                        <?php if ((int)$cat['document_count'] === 0): ?>
                            <form method="post" action="/admin/documents/categories/<?= (int)$cat['id'] ?>/delete" style="display:inline">
                                <input type="hidden" name="csrf_token" value="<?= \Cruinn\CSRF::getToken() ?>">
                                <button type="submit" class="btn btn-xs btn-danger"
                                        onclick="return confirm('Delete this category?')">Delete</button>
                            </form>
                        <?php endif; ?>

                        <!-- Inline edit form (hidden by default) -->
                        <form id="edit-cat-<?= (int)$cat['id'] ?>" method="post"
                              action="/admin/documents/categories/<?= (int)$cat['id'] ?>/update"
                              class="admin-form inline-edit-form" style="display:none; margin-top:.5rem">
                            <input type="hidden" name="csrf_token" value="<?= \Cruinn\CSRF::getToken() ?>">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Name</label>
                                    <input type="text" name="name" class="form-input"
                                           value="<?= $this->escape($cat['name']) ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Order</label>
                                    <input type="number" name="sort_order" class="form-input input-narrow"
                                           value="<?= (int)$cat['sort_order'] ?>" min="0" step="10">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Description</label>
                                <input type="text" name="description" class="form-input"
                                       value="<?= $this->escape($cat['description'] ?? '') ?>">
                            </div>
                            <button type="submit" class="btn btn-sm btn-primary">Save</button>
                            <button type="button" class="btn btn-sm btn-link"
                                    onclick="this.closest('form').style.display='none'">Cancel</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<style>
.input-narrow { width: 70px; }
</style>
