(function () {
    var wrap = document.getElementById('source-wrap');
    if (!wrap) return;

    var STORE_KEY_LEFT = 'cms_source_panel_left';
    var STORE_KEY_RIGHT = 'cms_source_panel_right';
    var csrfToken = wrap.dataset.csrfToken || '';
    var previewUrl = wrap.dataset.previewUrl || '';
    var currentDir = null;
    var loaded = false;

    var leftPanel = document.getElementById('source-panel-left');
    var rightPanel = document.getElementById('source-panel-right');
    var leftToggleBtn = document.getElementById('source-panel-left-toggle');
    var rightToggleBtn = document.getElementById('source-panel-right-toggle');
    var sourceTree = document.querySelector('.source-tree');
    var dirPullBtn = document.getElementById('props-dir-pull-btn');
    var dirResults = document.getElementById('props-dir-results');
    var sourceForm = document.getElementById('source-form');
    var sourceTextarea = document.getElementById('source-textarea');
    var sourceEditorBody = document.getElementById('source-editor-body');
    var sourcePreviewFrame = document.getElementById('source-preview-frame');
    var viewButtons = document.querySelectorAll('[data-source-view]');
    var filePullForm = document.getElementById('props-pull-form');
    var filePullBtn = document.getElementById('props-file-pull-btn');

    function applyPanelState(side, collapsed) {
        var panel = side === 'left' ? leftPanel : rightPanel;
        var button = side === 'left' ? leftToggleBtn : rightToggleBtn;
        if (!panel || !button) return;

        if (collapsed) {
            panel.classList.add('collapsed');
            button.textContent = side === 'left' ? '\u25B6' : '\u25C0';
            button.title = 'Expand ' + (side === 'left' ? 'tree' : 'properties');
        } else {
            panel.classList.remove('collapsed');
            button.textContent = side === 'left' ? '\u25C0' : '\u25B6';
            button.title = 'Collapse ' + (side === 'left' ? 'tree' : 'properties');
        }
    }

    function togglePanel(side) {
        var panel = side === 'left' ? leftPanel : rightPanel;
        if (!panel) return;

        var collapsed = !panel.classList.contains('collapsed');
        applyPanelState(side, collapsed);
        try {
            localStorage.setItem(side === 'left' ? STORE_KEY_LEFT : STORE_KEY_RIGHT, collapsed ? '1' : '0');
        } catch (e) {
            // Ignore storage errors.
        }
    }

    function loadPreview() {
        if (!sourcePreviewFrame || !previewUrl || loaded) return;
        sourcePreviewFrame.src = previewUrl;
        loaded = true;
    }

    function setView(view) {
        if (!sourceEditorBody) return;

        sourceEditorBody.className = 'source-editor-body source-view-' + view;
        viewButtons.forEach(function (btn) {
            btn.classList.toggle('active', btn.dataset.sourceView === view);
        });

        if (view === 'split' || view === 'preview') {
            loadPreview();
        }
    }

    if (leftToggleBtn) {
        leftToggleBtn.addEventListener('click', function () {
            togglePanel('left');
        });
    }

    if (rightToggleBtn) {
        rightToggleBtn.addEventListener('click', function () {
            togglePanel('right');
        });
    }

    if (sourceTextarea) {
        sourceTextarea.addEventListener('keydown', function (event) {
            if (event.key !== 'Tab') return;

            event.preventDefault();
            var start = this.selectionStart;
            var end = this.selectionEnd;
            this.value = this.value.substring(0, start) + '\t' + this.value.substring(end);
            this.selectionStart = this.selectionEnd = start + 1;
        });
    }

    viewButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            setView(btn.dataset.sourceView || 'code');
        });
    });

    if (sourceForm) {
        sourceForm.addEventListener('submit', function () {
            loaded = false;
        });
    }

    if (sourceTree) {
        sourceTree.addEventListener('click', function (event) {
            var summary = event.target.closest('summary[data-dir]');
            if (!summary) return;

            currentDir = summary.dataset.dir;

            var propsFile = document.getElementById('props-file');
            var propsDir = document.getElementById('props-dir');
            var propsEmpty = document.getElementById('props-empty');
            var propsDirName = document.getElementById('props-dir-name');

            if (propsFile) propsFile.style.display = 'none';
            if (propsEmpty) propsEmpty.style.display = 'none';
            if (propsDirName) propsDirName.textContent = currentDir + '/';

            if (dirResults) {
                dirResults.style.display = 'none';
                dirResults.innerHTML = '';
            }

            if (dirPullBtn) {
                dirPullBtn.disabled = false;
                dirPullBtn.textContent = '\u2193 Pull Folder from Repo';
            }

            if (propsDir) propsDir.style.display = '';
            if (rightPanel && rightPanel.classList.contains('collapsed')) {
                togglePanel('right');
            }
        });
    }

    if (dirPullBtn) {
        dirPullBtn.addEventListener('click', function () {
            if (!currentDir) return;
            if (!window.confirm('Pull all files under "' + currentDir + '/" from GitHub and overwrite local copies?')) return;

            var button = this;
            button.disabled = true;
            button.textContent = 'Pulling\u2026';

            if (dirResults) {
                dirResults.style.display = '';
                dirResults.innerHTML = '<span style="color:var(--plat-text-muted)">Contacting GitHub\u2026</span>';
            }

            var fd = new FormData();
            fd.append('csrf_token', csrfToken);
            fd.append('dir', currentDir);

            fetch('/cms/source/pull-dir', { method: 'POST', body: fd })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    button.disabled = false;
                    button.textContent = '\u2193 Pull Folder from Repo';

                    if (!dirResults) return;

                    if (!data.ok && !data.files) {
                        dirResults.innerHTML = '<span style="color:#dc2626">\u274c ' + (data.error || 'Unknown error') + '</span>';
                        return;
                    }

                    if (data.error && (!data.files || data.files.length === 0)) {
                        dirResults.innerHTML = '<span style="color:#d97706">\u26a0 ' + data.error + '</span>';
                        return;
                    }

                    var html = '';
                    var ok = 0;
                    var failed = 0;
                    var skipped = 0;

                    (data.files || []).forEach(function (file) {
                        if (file.status === 'ok') {
                            ok++;
                            html += '<div style="color:#16a34a">\u2713 ' + file.path + '</div>';
                        } else if (file.status === 'skipped') {
                            skipped++;
                            html += '<div style="color:var(--plat-text-muted)">\u2014 ' + file.path + (file.error ? ' (' + file.error + ')' : '') + '</div>';
                        } else {
                            failed++;
                            html += '<div style="color:#dc2626">\u274c ' + file.path + (file.error ? ': ' + file.error : '') + '</div>';
                        }
                    });

                    var summary = '<div style="font-weight:600;margin-bottom:.4rem;border-bottom:1px solid var(--plat-border);padding-bottom:.3rem;">'
                        + ok + ' updated, ' + failed + ' failed, ' + skipped + ' skipped</div>';
                    dirResults.innerHTML = summary + html;
                })
                .catch(function (error) {
                    button.disabled = false;
                    button.textContent = '\u2193 Pull Folder from Repo';
                    if (dirResults) {
                        dirResults.innerHTML = '<span style="color:#dc2626">\u274c Network error: ' + error.message + '</span>';
                    }
                });
        });
    }

    if (filePullForm && filePullBtn) {
        filePullForm.addEventListener('submit', function (event) {
            var fileName = filePullBtn.dataset.fileName || 'this file';
            var ok = window.confirm('Pull "' + fileName + '" from GitHub and overwrite the local copy?');
            if (!ok) event.preventDefault();
        });
    }

    applyPanelState('left', false);
    applyPanelState('right', false);
}());
