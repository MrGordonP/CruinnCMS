<?php
/**
 * Admin — Media Library (3-panel layout)
 */
\Cruinn\Template::requireCss('admin-panel-layout.css');
\Cruinn\Template::requireJs('media.js');
$GLOBALS['admin_flush_layout'] = true;
?>

<style>
/* Media-specific overrides */
.media-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 0.75rem;
    padding: 0.75rem 1rem;
}
.media-thumb {
    border: 2px solid var(--color-border, #ccd9d3);
    border-radius: 6px;
    background: #fff;
    padding: 0.5rem;
    text-align: center;
    cursor: pointer;
    transition: border-color 0.1s, box-shadow 0.1s;
    position: relative;
}
.media-thumb:hover { border-color: var(--color-primary, #1d9e75); box-shadow: 0 2px 6px rgba(0,0,0,.08); }
.media-thumb.selected { border-color: var(--color-primary, #1d9e75); box-shadow: 0 0 0 2px #5dcaa580; }
.media-thumb img { width: 100%; height: 80px; object-fit: cover; border-radius: 3px; background: #f2f5f3; display: block; }
.media-thumb-name { font-size: 0.72rem; color: #555; margin-top: 0.3rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.media-thumb-del { position: absolute; top: 3px; right: 3px; background: rgba(200,30,30,.85); color: #fff; border: none; border-radius: 3px; font-size: 0.65rem; padding: 1px 5px; cursor: pointer; display: none; line-height: 1.4; }
.media-thumb:hover .media-thumb-del { display: block; }

/* Folder tree in sidebar */
.mf-folder {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.9rem;
    font-size: 0.84rem;
    color: var(--color-text, #0c1614);
    cursor: pointer;
    border-left: 3px solid transparent;
    transition: background 0.1s, border-color 0.1s;
    user-select: none;
}
.mf-folder:hover { background: var(--color-bg-light, #f2f5f3); }
.mf-folder.active { border-left-color: var(--color-primary, #1d9e75); background: #eaf6f1; font-weight: 600; }

/* Detail image preview */
.media-detail-img { width: 100%; max-height: 160px; object-fit: contain; border-radius: 4px; background: #f2f5f3; margin-bottom: 0.75rem; display: block; }
.media-copy-url { font-size: 0.75rem; font-family: monospace; word-break: break-all; background: #f2f5f3; border-radius: 4px; padding: 0.3rem 0.5rem; margin-bottom: 0.75rem; cursor: pointer; border: 1px solid #ddd; color: #333; }
.media-copy-url:hover { border-color: var(--color-primary, #1d9e75); }
</style>

<div class="panel-layout" id="media-layout">

    <!-- ── Left: Folder tree ──────────────────────────────────── -->
    <div class="pl-sidebar" id="pl-sidebar">
        <div class="pl-sidebar-header">
            <h3>Folders</h3>
            <button type="button" class="pl-panel-toggle" id="pl-sidebar-toggle" title="Collapse">&#x25C0;</button>
            <button class="btn btn-sm btn-secondary" id="new-folder-btn">+ Folder</button>
        </div>
        <div class="pl-sidebar-scroll" id="media-folder-tree">
            <div class="mf-folder active" data-path="" id="folder-root">
                <span>🏠</span> All Media
            </div>
            <!-- dynamic sub-folders appended here -->
        </div>
        <div class="pl-sidebar-footer" id="new-folder-wrap" style="display:none">
            <input type="text" id="new-folder-name" class="pl-search-input" placeholder="Folder name (a-z, 0-9, -, _)" style="margin-bottom:.4rem">
            <div style="display:flex;gap:.4rem">
                <button class="btn btn-sm btn-primary" id="new-folder-save">Create</button>
                <button class="btn btn-sm btn-secondary" id="new-folder-cancel">Cancel</button>
            </div>
        </div>
    </div>

    <!-- ── Middle: Media grid ─────────────────────────────────── -->
    <div class="pl-main">
        <div class="pl-main-toolbar">
            <span class="pl-main-title" id="media-folder-label">All Media</span>
            <div class="pl-main-toolbar-actions">
                <button class="btn btn-sm btn-primary" id="upload-btn">⬆ Upload</button>
                <input type="file" id="upload-input" multiple accept="image/*,application/pdf,.doc,.docx,.zip" style="display:none">
            </div>
        </div>
        <div class="pl-main-search">
            <input type="search" class="pl-search-input" id="media-search" placeholder="Search files…" autocomplete="off">
        </div>
        <div id="media-grid-wrap" style="flex:1;overflow-y:auto">
            <div class="media-grid" id="media-grid">
                <div class="pl-empty" style="grid-column:1/-1">Loading…</div>
            </div>
        </div>
    </div>

    <!-- ── Right: File detail ─────────────────────────────────── -->
    <div class="pl-detail" id="pl-detail">
        <div class="pl-detail-header"><h3>File Details</h3><button type="button" class="pl-panel-toggle" id="pl-detail-toggle" title="Collapse">&#x25B6;</button></div>
        <div class="pl-detail-scroll">
            <div class="pl-detail-placeholder" id="media-detail-placeholder">
                <div class="pl-detail-placeholder-icon">🖼</div>
                <span>Select a file to see details</span>
            </div>
            <div id="media-detail-content" style="display:none"></div>
        </div>
    </div>

</div>

