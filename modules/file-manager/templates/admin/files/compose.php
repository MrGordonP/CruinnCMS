<?php
/**
 * File Manager — Document Composer / Editor
 *
 * Rich-text editor for creating new documents or editing existing ones.
 * Uses a contenteditable-based editor with formatting toolbar.
 */
\IGA\Template::requireCss('admin-file-manager.css');


$isEdit = !empty($file);
$formAction = $isEdit ? '/files/' . (int)$file['id'] . '/edit' : '/files/compose';
?>
<div class="admin-page">
    <div class="admin-page-header">
        <h1><?= $isEdit ? 'Edit Document' : 'New Document' ?></h1>
        <a href="<?= $isEdit ? '/files/' . (int)$file['id'] : '/files' ?>" class="btn btn-secondary">← Back</a>
    </div>

    <form method="post" action="<?= $formAction ?>" class="fm-compose-form" id="compose-form">
        <?= csrf_field() ?>

        <div class="fm-compose-meta">
            <div class="form-group">
                <label for="title">Title <span class="required">*</span></label>
                <input type="text" id="title" name="title" required value="<?= e($file['title'] ?? '') ?>" placeholder="Document title">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="folder_id">Folder</label>
                    <select id="folder_id" name="folder_id">
                        <option value="">— Root —</option>
                        <?php foreach ($folders as $f): ?>
                            <option value="<?= (int)$f['id'] ?>" <?= ($currentFolderId ?? ($file['folder_id'] ?? '')) == $f['id'] ? 'selected' : '' ?>>
                                <?= str_repeat('— ', $f['depth'] ?? 0) ?><?= e($f['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="subject_id">Subject</label>
                    <select id="subject_id" name="subject_id">
                        <option value="">— None —</option>
                        <?php foreach ($subjects as $s): ?>
                            <option value="<?= (int)$s['id'] ?>" <?= ($file['subject_id'] ?? '') == $s['id'] ? 'selected' : '' ?>>
                                <?= e($s['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <input type="text" id="description" name="description" value="<?= e($file['description'] ?? '') ?>" placeholder="Brief description (optional)">
            </div>

            <?php if ($isEdit): ?>
                <div class="form-group">
                    <label for="version_notes">Version Notes</label>
                    <input type="text" id="version_notes" name="version_notes" placeholder="What changed in this revision?">
                </div>
            <?php endif; ?>
        </div>

        <!-- Rich Text Editor -->
        <div class="fm-editor-container">
            <div class="fm-editor-toolbar" id="editor-toolbar">
                <div class="fm-toolbar-group">
                    <button type="button" onclick="execCmd('bold')" title="Bold (Ctrl+B)"><strong>B</strong></button>
                    <button type="button" onclick="execCmd('italic')" title="Italic (Ctrl+I)"><em>I</em></button>
                    <button type="button" onclick="execCmd('underline')" title="Underline (Ctrl+U)"><u>U</u></button>
                    <button type="button" onclick="execCmd('strikeThrough')" title="Strikethrough"><s>S</s></button>
                </div>
                <div class="fm-toolbar-group">
                    <select onchange="execCmd('formatBlock', this.value); this.value='';" title="Heading">
                        <option value="">Heading…</option>
                        <option value="h1">Heading 1</option>
                        <option value="h2">Heading 2</option>
                        <option value="h3">Heading 3</option>
                        <option value="h4">Heading 4</option>
                        <option value="p">Paragraph</option>
                        <option value="blockquote">Quote</option>
                        <option value="pre">Code Block</option>
                    </select>
                </div>
                <div class="fm-toolbar-group">
                    <button type="button" onclick="execCmd('insertUnorderedList')" title="Bullet List">•≡</button>
                    <button type="button" onclick="execCmd('insertOrderedList')" title="Numbered List">1.</button>
                    <button type="button" onclick="execCmd('indent')" title="Indent">⇥</button>
                    <button type="button" onclick="execCmd('outdent')" title="Outdent">⇤</button>
                </div>
                <div class="fm-toolbar-group">
                    <button type="button" onclick="insertLink()" title="Insert Link">🔗</button>
                    <button type="button" onclick="insertImage()" title="Insert Image">🖼</button>
                    <button type="button" onclick="insertTable()" title="Insert Table">⊞</button>
                    <button type="button" onclick="execCmd('insertHorizontalRule')" title="Horizontal Rule">―</button>
                </div>
                <div class="fm-toolbar-group">
                    <button type="button" onclick="execCmd('justifyLeft')" title="Align Left">⫷</button>
                    <button type="button" onclick="execCmd('justifyCenter')" title="Align Centre">⫿</button>
                    <button type="button" onclick="execCmd('justifyRight')" title="Align Right">⫸</button>
                </div>
                <div class="fm-toolbar-group">
                    <button type="button" onclick="execCmd('removeFormat')" title="Clear Formatting">⌧</button>
                    <button type="button" onclick="toggleSource()" title="View HTML Source">⟨/⟩</button>
                </div>
                <div class="fm-toolbar-right">
                    <span class="fm-word-count" id="word-count">0 words</span>
                </div>
            </div>

            <div class="fm-editor" id="editor" contenteditable="true"><?= $file['parsed_content'] ?? '' ?></div>

            <textarea class="fm-editor-source" id="editor-source" style="display:none"></textarea>
        </div>

        <!-- Hidden textarea synced from editor -->
        <textarea name="content" id="content-hidden" style="display:none"><?= e($file['parsed_content'] ?? '') ?></textarea>

        <div class="fm-compose-footer">
            <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save Changes' : 'Create Document' ?></button>
            <a href="<?= $isEdit ? '/files/' . (int)$file['id'] : '/files' ?>" class="btn btn-secondary">Cancel</a>
            <?php if ($isEdit): ?>
                <span class="text-muted fm-compose-version">Version <?= (int)$file['version'] ?> · Last updated <?= format_date($file['updated_at'], 'j M Y H:i') ?></span>
            <?php endif; ?>
        </div>
    </form>
</div>

<script>
(function() {
    'use strict';

    // ── Constants ──────────────────────────────────────────────────
    const AUTOSAVE_LOCAL_MS  = 5000;   // localStorage every 5s on change
    const AUTOSAVE_SERVER_MS = 60000;  // server autosave every 60s on change
    const STORAGE_KEY = <?= json_encode('iga_doc_' . ($isEdit ? (int)$file['id'] : 'new')) ?>;
    const FILE_ID     = <?= $isEdit ? (int)$file['id'] : 'null' ?>;
    const CSRF_TOKEN  = <?= json_encode(\IGA\CSRF::getToken()) ?>;

    // ── DOM refs ───────────────────────────────────────────────────
    const editor  = document.getElementById('editor');
    const hidden  = document.getElementById('content-hidden');
    const source  = document.getElementById('editor-source');
    const form    = document.getElementById('compose-form');
    const titleEl = document.getElementById('title');
    let sourceMode = false;

    // ── Save-status indicator ──────────────────────────────────────
    const statusEl = document.createElement('span');
    statusEl.className = 'fm-save-status';
    statusEl.id = 'save-status';
    document.querySelector('.fm-toolbar-right').prepend(statusEl);

    function setStatus(text, cls) {
        statusEl.textContent = text;
        statusEl.className = 'fm-save-status' + (cls ? ' fm-save-' + cls : '');
    }

    // ── Dirty tracking ─────────────────────────────────────────────
    let lastSavedContent = editor.innerHTML;
    let lastSavedTitle   = titleEl.value;
    let dirty = false;
    let serverDirty = false;

    function markDirty() {
        dirty = true;
        serverDirty = true;
        setStatus('Unsaved changes', 'unsaved');
    }

    function isDirty() {
        return editor.innerHTML !== lastSavedContent || titleEl.value !== lastSavedTitle;
    }

    editor.addEventListener('input', markDirty);
    titleEl.addEventListener('input', markDirty);
    document.getElementById('description')?.addEventListener('input', markDirty);

    // ── beforeunload guard ─────────────────────────────────────────
    window.addEventListener('beforeunload', function(e) {
        syncHidden();
        saveToLocal();   // last-chance local backup
        if (isDirty()) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    // ── localStorage autosave ──────────────────────────────────────
    function getFormData() {
        return {
            title:       titleEl.value,
            content:     sourceMode ? source.value : editor.innerHTML,
            description: document.getElementById('description')?.value || '',
            folder_id:   document.getElementById('folder_id')?.value || '',
            subject_id:  document.getElementById('subject_id')?.value || '',
            version_notes: document.getElementById('version_notes')?.value || '',
            saved_at:    new Date().toISOString()
        };
    }

    function saveToLocal() {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(getFormData()));
        } catch (e) { /* quota exceeded — silent */ }
    }

    function loadFromLocal() {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            return raw ? JSON.parse(raw) : null;
        } catch (e) { return null; }
    }

    function clearLocal() {
        try { localStorage.removeItem(STORAGE_KEY); } catch(e) {}
    }

    // Periodic local save
    setInterval(function() {
        if (dirty) {
            saveToLocal();
            dirty = false;
        }
    }, AUTOSAVE_LOCAL_MS);

    // ── Recovery check on load ─────────────────────────────────────
    (function checkRecovery() {
        const saved = loadFromLocal();
        if (!saved) return;

        // Only offer recovery if local draft differs from current server content
        const serverContent = editor.innerHTML;
        const serverTitle   = titleEl.value;

        if (saved.content === serverContent && saved.title === serverTitle) {
            clearLocal();   // identical — no recovery needed
            return;
        }

        // Show recovery banner
        const banner = document.createElement('div');
        banner.className = 'fm-recovery-banner';
        const savedDate = new Date(saved.saved_at);
        const timeStr = savedDate.toLocaleString();
        banner.innerHTML =
            '<span>\ud83d\udcdd A locally saved draft from <strong>' + timeStr + '</strong> was found. </span>' +
            '<button type="button" class="btn btn-sm btn-primary" id="recover-btn">Restore Draft</button> ' +
            '<button type="button" class="btn btn-sm btn-secondary" id="discard-btn">Discard</button>';

        form.parentNode.insertBefore(banner, form);

        document.getElementById('recover-btn').addEventListener('click', function() {
            titleEl.value = saved.title || titleEl.value;
            editor.innerHTML = saved.content || '';
            if (saved.description) document.getElementById('description').value = saved.description;
            if (saved.folder_id)   document.getElementById('folder_id').value = saved.folder_id;
            if (saved.subject_id)  document.getElementById('subject_id').value = saved.subject_id;
            if (saved.version_notes && document.getElementById('version_notes'))
                document.getElementById('version_notes').value = saved.version_notes;
            banner.remove();
            markDirty();
            updateWordCount();
            setStatus('Draft restored', 'restored');
        });

        document.getElementById('discard-btn').addEventListener('click', function() {
            clearLocal();
            banner.remove();
        });
    })();

    // ── Server autosave (AJAX) ─────────────────────────────────────
    let autosaveInFlight = false;
    let pendingRetry = null;

    function autosaveToServer() {
        if (!FILE_ID) return;          // new doc — no server endpoint yet
        if (autosaveInFlight) return;  // don't stack requests
        if (!serverDirty) return;      // nothing changed since last save

        autosaveInFlight = true;
        setStatus('Saving\u2026', 'saving');

        const payload = new URLSearchParams();
        payload.append('_csrf_token', CSRF_TOKEN);
        payload.append('title', titleEl.value);
        payload.append('content', sourceMode ? source.value : editor.innerHTML);

        const controller = new AbortController();
        const timeoutId = setTimeout(function() { controller.abort(); }, 15000);

        fetch('/files/' + FILE_ID + '/autosave', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': CSRF_TOKEN
            },
            body: payload.toString(),
            signal: controller.signal
        })
        .then(function(resp) {
            clearTimeout(timeoutId);
            if (!resp.ok) throw new Error('HTTP ' + resp.status);
            return resp.json();
        })
        .then(function(data) {
            autosaveInFlight = false;
            if (data.ok) {
                serverDirty = false;
                lastSavedContent = editor.innerHTML;
                lastSavedTitle   = titleEl.value;
                const t = new Date(data.saved_at).toLocaleTimeString();
                setStatus('Saved at ' + t, 'saved');
                if (data.word_count !== undefined) {
                    document.getElementById('word-count').textContent =
                        data.word_count + ' word' + (data.word_count !== 1 ? 's' : '');
                }
                saveToLocal();  // keep local copy in sync
            } else {
                setStatus('Save failed \u2014 stored locally', 'error');
                saveToLocal();
            }
        })
        .catch(function() {
            autosaveInFlight = false;
            saveToLocal();
            if (!navigator.onLine) {
                setStatus('Offline \u2014 saved locally', 'offline');
            } else {
                setStatus('Save failed \u2014 saved locally', 'error');
            }
            scheduleRetry();
        });
    }

    function scheduleRetry() {
        if (pendingRetry) return;
        pendingRetry = setTimeout(function() {
            pendingRetry = null;
            if (serverDirty) autosaveToServer();
        }, 15000);
    }

    // Periodic server autosave
    setInterval(function() {
        if (serverDirty && FILE_ID) autosaveToServer();
    }, AUTOSAVE_SERVER_MS);

    // When coming back online, retry immediately
    window.addEventListener('online', function() {
        setStatus('Back online', 'saved');
        if (serverDirty && FILE_ID) {
            setTimeout(autosaveToServer, 1000);
        }
    });

    window.addEventListener('offline', function() {
        setStatus('Offline \u2014 changes saved locally', 'offline');
    });

    // ── Offline-resilient form submit ──────────────────────────────
    let formSubmitted = false;

    form.addEventListener('submit', function(e) {
        if (sourceMode) toggleSource();
        syncHidden();
        saveToLocal();

        // If we're offline, intercept and queue
        if (!navigator.onLine) {
            e.preventDefault();
            setStatus('Offline \u2014 saved locally, will submit when online', 'offline');

            const submitWhenOnline = function() {
                window.removeEventListener('online', submitWhenOnline);
                setStatus('Connection restored \u2014 submitting\u2026', 'saving');
                setTimeout(function() { form.submit(); }, 500);
            };
            window.addEventListener('online', submitWhenOnline);
            return;
        }

        formSubmitted = true;
    });

    // On successful navigation away after submit, clear local draft
    window.addEventListener('pagehide', function() {
        if (formSubmitted) clearLocal();
    });

    // ── Editor helper functions ────────────────────────────────────

    function syncHidden() {
        hidden.value = sourceMode ? source.value : editor.innerHTML;
    }

    function execCmd(cmd, value) {
        if (cmd === 'formatBlock' && value) {
            document.execCommand(cmd, false, '<' + value + '>');
        } else {
            document.execCommand(cmd, false, value || null);
        }
        editor.focus();
        updateWordCount();
    }
    window.execCmd = execCmd;

    function insertLink() {
        const url = prompt('Enter URL:');
        if (url) document.execCommand('createLink', false, url);
        editor.focus();
    }
    window.insertLink = insertLink;

    function insertImage() {
        const url = prompt('Enter image URL (or /uploads/... path):');
        if (url) document.execCommand('insertImage', false, url);
        editor.focus();
    }
    window.insertImage = insertImage;

    function insertTable() {
        const rows = parseInt(prompt('Number of rows:', '3'), 10) || 3;
        const cols = parseInt(prompt('Number of columns:', '3'), 10) || 3;

        let html = '<table><thead><tr>';
        for (let c = 0; c < cols; c++) html += '<th>Header ' + (c+1) + '</th>';
        html += '</tr></thead><tbody>';
        for (let r = 0; r < rows - 1; r++) {
            html += '<tr>';
            for (let c = 0; c < cols; c++) html += '<td>&nbsp;</td>';
            html += '</tr>';
        }
        html += '</tbody></table>';
        document.execCommand('insertHTML', false, html);
        editor.focus();
    }
    window.insertTable = insertTable;

    function toggleSource() {
        sourceMode = !sourceMode;
        if (sourceMode) {
            source.value = editor.innerHTML;
            editor.style.display = 'none';
            source.style.display = 'block';
            source.focus();
        } else {
            editor.innerHTML = source.value;
            source.style.display = 'none';
            editor.style.display = 'block';
            editor.focus();
        }
    }
    window.toggleSource = toggleSource;

    function updateWordCount() {
        const text = editor.innerText || '';
        const words = text.trim() ? text.trim().split(/\s+/).length : 0;
        document.getElementById('word-count').textContent = words + ' word' + (words !== 1 ? 's' : '');
    }

    editor.addEventListener('input', updateWordCount);
    updateWordCount();

    // Keyboard shortcuts
    editor.addEventListener('keydown', function(e) {
        if (e.ctrlKey || e.metaKey) {
            switch(e.key.toLowerCase()) {
                case 'b': e.preventDefault(); execCmd('bold'); break;
                case 'i': e.preventDefault(); execCmd('italic'); break;
                case 'u': e.preventDefault(); execCmd('underline'); break;
                case 's': e.preventDefault(); autosaveToServer(); saveToLocal(); break;
            }
        }
        if (e.key === 'Tab') {
            e.preventDefault();
            execCmd(e.shiftKey ? 'outdent' : 'indent');
        }
    });

})();
</script>
