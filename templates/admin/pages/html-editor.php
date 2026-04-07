<?php
/**
 * Admin — HTML Page Editor
 * Variables: $page, $title
 */
?>
<div class="admin-page-header">
    <h1>HTML Editor: <?= e($page['title']) ?></h1>
    <div style="display:flex;gap:0.5rem;">
        <a href="/<?= e($page['slug']) ?>" target="_blank" class="btn btn-outline">Preview</a>
        <a href="/admin/pages" class="btn btn-outline">← Pages</a>
    </div>
</div>

<div style="background:#fff38ed4;border-left:4px solid #7c3aed;padding:0.75rem 1rem;margin-bottom:1rem;border-radius:4px;font-size:0.875rem;">
    <strong>HTML mode:</strong> This page's content is written directly as HTML.
    It will be wrapped in the site layout (nav, header, footer).
    If you want a fully standalone page instead, use <em>Export → File mode</em> in the Pages list.
</div>

<form method="POST" action="/admin/pages/<?= (int)$page['id'] ?>/html">
    <input type="hidden" name="csrf_token" value="<?= e(\Cruinn\CSRF::getToken()) ?>">

    <div class="form-group">
        <label for="body_html">HTML Content</label>
        <textarea
            id="body_html"
            name="body_html"
            class="form-control html-code-editor"
            rows="30"
            style="font-family: 'Fira Code', 'Courier New', monospace; font-size: 0.85rem; line-height: 1.6; background: #1e1e2e; color: #cdd6f4; border: 1px solid #444; border-radius: 6px;"
            spellcheck="false"><?= htmlspecialchars($page['body_html'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
        <small class="form-hint">Write any valid HTML. Do not include &lt;html&gt;, &lt;head&gt;, or &lt;body&gt; tags — the site layout handles those.</small>
    </div>

    <div style="margin-top:1rem;display:flex;gap:0.5rem;">
        <button type="submit" class="btn btn-primary">Save HTML</button>
        <a href="/admin/pages" class="btn btn-outline">Cancel</a>
    </div>
</form>

<style>
.html-code-editor { resize: vertical; tab-size: 4; }
</style>

<script>
// Allow Tab key to insert 4 spaces instead of leaving the textarea
document.getElementById('body_html').addEventListener('keydown', function(e) {
    if (e.key === 'Tab') {
        e.preventDefault();
        const start = this.selectionStart;
        const end   = this.selectionEnd;
        this.value  = this.value.substring(0, start) + '    ' + this.value.substring(end);
        this.selectionStart = this.selectionEnd = start + 4;
    }
});
</script>
