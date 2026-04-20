<?php
/**
 * Admin — Mailbox tag management.
 *
 * @var array  $tags
 * @var string $csrf_token
 */
?>
<div class="acp-section">
    <div class="acp-header">
        <h1>Mailbox Tags</h1>
        <a class="btn" href="/admin/mailbox">← Mailbox</a>
    </div>

    <!-- Create tag -->
    <form class="acp-form" method="post" action="/admin/mailbox/tags">
        <input type="hidden" name="csrf_token" value="<?= $this->escape($csrf_token) ?>">
        <h2>New Tag</h2>
        <div class="form-row">
            <label for="tag-label">Label</label>
            <input class="form-input" id="tag-label" name="label" type="text" required maxlength="100">
        </div>
        <div class="form-row">
            <label for="tag-colour">Colour</label>
            <input class="form-input" id="tag-colour" name="colour" type="color" value="#1d9e75">
        </div>
        <div class="form-row">
            <label for="tag-order">Sort order</label>
            <input class="form-input" id="tag-order" name="sort_order" type="number" value="0" min="0">
        </div>
        <button class="btn btn-primary" type="submit">Create tag</button>
    </form>

    <!-- Existing tags -->
    <?php if (!empty($tags)): ?>
        <h2>Existing Tags</h2>
        <table class="acp-table">
            <thead>
                <tr><th>Label</th><th>Colour</th><th>Order</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($tags as $tag): ?>
                    <tr>
                        <form method="post" action="/admin/mailbox/tags/<?= (int) $tag['id'] ?>/update">
                            <input type="hidden" name="csrf_token" value="<?= $this->escape($csrf_token) ?>">
                            <td>
                                <input class="form-input form-input-inline" name="label" type="text"
                                       value="<?= $this->escape($tag['label']) ?>" required maxlength="100">
                            </td>
                            <td>
                                <input class="form-input" name="colour" type="color"
                                       value="<?= $this->escape($tag['colour']) ?>">
                            </td>
                            <td>
                                <input class="form-input form-input-sm" name="sort_order" type="number"
                                       value="<?= (int) $tag['sort_order'] ?>" min="0">
                            </td>
                            <td>
                                <button class="btn btn-sm" type="submit">Save</button>
                        </form>
                        <form method="post" action="/admin/mailbox/tags/<?= (int) $tag['id'] ?>/delete"
                              onsubmit="return confirm('Delete tag?')">
                            <input type="hidden" name="csrf_token" value="<?= $this->escape($csrf_token) ?>">
                            <button class="btn btn-sm btn-danger" type="submit">Delete</button>
                        </form>
                            </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
