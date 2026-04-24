<?php
/**
 * Drivespace — Google Drive Browse View
 *
 * Reuses the drivespace 3-column layout with a Google Drive source panel.
 * Left: source switcher (Local / Google Drive) — middle: Drive contents.
 */
\Cruinn\Template::requireCss('admin-drivespace.css');

$folders   = $listing['folders'] ?? [];
$files     = $listing['files']   ?? [];
$isRoot    = $folderId === $rootFolderId || $folderId === 'root';
?>

<div class="drivespace" id="drivespace-app">

    <!-- ── Left: Source ───────────────────────────────────────── -->
    <div class="fm-tree-panel">
        <div class="fm-tree-header">
            <h3>Source</h3>
        </div>
        <div class="fm-tree-scroll">
            <a href="/drivespace" class="fm-tree-root">
                <span>🏠</span> Local Drivespace
            </a>
            <a href="/drivespace/gdrive" class="fm-tree-root active" style="border-left:3px solid #1d9e75">
                <span>☁️</span> Google Drive
            </a>
        </div>
    </div>

    <!-- ── Middle: Drive Contents ─────────────────────────────── -->
    <div class="fm-content-panel">
        <div class="fm-toolbar">
            <div class="fm-breadcrumb">
                <?php if (!$isRoot): ?>
                    <a href="/drivespace/gdrive">Google Drive</a>
                    <span class="fm-breadcrumb-sep">›</span>
                    <span class="fm-breadcrumb-current">Folder</span>
                <?php else: ?>
                    <span class="fm-breadcrumb-current">Google Drive</span>
                <?php endif; ?>
            </div>
            <div class="fm-toolbar-actions">
                <?php if ($canWrite): ?>
                <button class="btn btn-sm btn-primary" onclick="document.getElementById('gdrive-upload-form').style.display=document.getElementById('gdrive-upload-form').style.display==='none'?'flex':'none'">⬆ Upload to Drive</button>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($canWrite): ?>
        <form id="gdrive-upload-form" method="POST" action="/drivespace/gdrive/upload"
              enctype="multipart/form-data"
              style="display:none;align-items:center;gap:0.5rem;padding:0.6rem 1rem;background:#f8f8f8;border-bottom:1px solid #e5e5e5">
            <input type="hidden" name="csrf_token" value="<?= \Cruinn\CSRF::getToken() ?>">
            <input type="hidden" name="folder_id" value="<?= e($folderId ?? '') ?>">
            <input type="file" name="file" required style="flex:1;min-width:0">
            <button type="submit" class="btn btn-sm btn-primary">Upload</button>
            <button type="button" class="btn btn-sm btn-outline"
                    onclick="this.closest('form').style.display='none'">Cancel</button>
        </form>
        <?php endif; ?>

        <div class="fm-contents-scroll" id="fm-contents">
            <?php if (empty($folders) && empty($files)): ?>
                <div class="fm-empty">
                    <p>This folder is empty or not accessible.</p>
                </div>
            <?php else: ?>

                <?php if (!empty($folders)): ?>
                <p class="fm-section-title">Folders</p>
                <table class="fm-items-table">
                    <thead>
                        <tr>
                            <th class="fm-col-icon"></th>
                            <th>Name</th>
                            <th>Modified</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($folders as $f): ?>
                        <tr ondblclick="location.href='/drivespace/gdrive?folder=<?= e($f['id']) ?>'">
                            <td class="fm-col-icon">📁</td>
                            <td class="fm-col-name">
                                <a href="/drivespace/gdrive?folder=<?= e($f['id']) ?>"><?= e($f['name']) ?></a>
                            </td>
                            <td class="fm-col-meta"><?= e(substr($f['modifiedTime'] ?? '', 0, 10)) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>

                <?php if (!empty($files)): ?>
                <p class="fm-section-title" style="<?= !empty($folders) ? 'margin-top:1rem' : '' ?>">
                    Files <span style="font-weight:400;color:#bbb">(<?= count($files) ?>)</span>
                </p>
                <table class="fm-items-table">
                    <thead>
                        <tr>
                            <th class="fm-col-icon"></th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Size</th>
                            <th>Modified</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($files as $f):
                            $size = isset($f['size']) ? \Cruinn\Module\Drivespace\Services\GoogleDriveService::formatSize((int)$f['size']) : '—';
                            $icon = \Cruinn\Module\Drivespace\Services\GoogleDriveService::fileIcon($f['mimeType'] ?? '');
                        ?>
                        <tr>
                            <td class="fm-col-icon"><?= $icon ?></td>
                            <td class="fm-col-name">
                                <?= e($f['name']) ?>
                                <?php if (!empty($f['webViewLink'])): ?>
                                    <a href="<?= e($f['webViewLink']) ?>" target="_blank" rel="noopener"
                                       title="Open in Google Drive" style="margin-left:0.4rem;font-size:0.75rem;color:#888">↗</a>
                                <?php endif; ?>
                            </td>
                            <td class="fm-col-meta" style="font-size:0.78rem;color:#888">
                                <?= e(str_replace('application/vnd.google-apps.', '', $f['mimeType'] ?? '')) ?>
                            </td>
                            <td class="fm-col-meta"><?= e($size) ?></td>
                            <td class="fm-col-meta"><?= e(substr($f['modifiedTime'] ?? '', 0, 10)) ?></td>
                            <td class="fm-col-actions" style="white-space:nowrap">
                                <a href="/drivespace/gdrive/<?= e($f['id']) ?>/download"
                                   class="btn btn-sm btn-outline">⬇ Download</a>
                                <?php if ($canWrite): ?>
                                <form method="POST" action="/drivespace/gdrive/<?= e($f['id']) ?>/import"
                                      style="display:inline;margin-left:0.3rem">
                                    <input type="hidden" name="csrf_token" value="<?= \Cruinn\CSRF::getToken() ?>">
                                    <button type="submit" class="btn btn-sm btn-outline"
                                            title="Import to local Drivespace">⬇ Import</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>

    <!-- ── Right: placeholder (no item properties for Drive files) ── -->
    <div class="fm-properties-panel" id="fm-properties">
        <div class="fm-props-placeholder">
            <p style="color:#aaa;font-size:0.875rem;padding:1rem">
                Select a file to view details.
            </p>
        </div>
    </div>

</div>
