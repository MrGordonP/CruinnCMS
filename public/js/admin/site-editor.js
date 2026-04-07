/**
 * Cruinn Admin — Site Editor
 *
 * Left-panel accordion toggles, sidebar zone checkbox, zone click selection
 * (syncing with block editor's activeZone), zone visibility toggles,
 * and the embedded properties panel MutationObserver.
 *
 * Cross-module communication: writes to window.__seActiveZone, which is
 * a getter/setter defined by block-editor/core.js over Cruinn.blockContext.activeZone.
 *
 * No Cruinn dependency — operates on DOM only.
 */
(function (Cruinn) {

    Cruinn.initSiteEditor = function () {
        var siteEditor = document.querySelector('.site-editor');
        if (!siteEditor) return;

        // ── Accordion toggles for collapsible sections ─────────────

        document.querySelectorAll('.se-section-header[data-toggle]').forEach(function (header) {
            var targetId = header.dataset.toggle;
            var body = document.getElementById(targetId);
            if (!body) return;

            header.addEventListener('click', function () {
                var isOpen = body.style.display === 'block' || body.classList.contains('se-section-body-open');
                if (isOpen) {
                    body.style.display = 'none';
                    body.classList.remove('se-section-body-open');
                    var chevron = header.querySelector('.se-chevron');
                    if (chevron) chevron.style.transform = 'rotate(-90deg)';
                } else {
                    body.style.display = 'block';
                    body.classList.add('se-section-body-open');
                    var chevron = header.querySelector('.se-chevron');
                    if (chevron) chevron.style.transform = '';
                }
            });

            if (!body.classList.contains('se-section-body-open')) {
                body.style.display = 'none';
                var chevron = header.querySelector('.se-chevron');
                if (chevron) chevron.style.transform = 'rotate(-90deg)';
            }
        });

        // ── Sidebar toggle ──────────────────────────────────────────

        var sidebarToggle = document.getElementById('seSidebarToggle');
        var sidebarPosSelect = document.querySelector('.se-sidebar-pos');
        var zonesInput = document.getElementById('seZones');

        if (sidebarToggle) {
            sidebarToggle.addEventListener('change', function () {
                if (sidebarPosSelect) {
                    sidebarPosSelect.disabled = !this.checked;
                }
                if (zonesInput) {
                    var zones = [];
                    try { zones = JSON.parse(zonesInput.value); } catch (e) { zones = ['main']; }
                    if (!Array.isArray(zones)) zones = ['main'];
                    var idx = zones.indexOf('sidebar');
                    if (this.checked && idx === -1) zones.push('sidebar');
                    else if (!this.checked && idx > -1) zones.splice(idx, 1);
                    zonesInput.value = JSON.stringify(zones);
                }
            });
        }

        // ── Zone selection ──────────────────────────────────────────

        var zoneElements = document.querySelectorAll('.se-zone');
        var sectionItems = document.querySelectorAll('.se-section-item.se-zone-link[data-zone]');

        function setActiveZone(zoneName) {
            zoneElements.forEach(function (z) {
                z.classList.toggle('se-zone-active', z.dataset.zone === zoneName);
            });
            sectionItems.forEach(function (item) {
                item.classList.toggle('active', item.dataset.zone === zoneName);
            });
            var targetZone = document.querySelector('.se-zone[data-zone="' + zoneName + '"]');
            if (targetZone) {
                targetZone.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
            if (window.__seActiveZone !== undefined) {
                window.__seActiveZone = zoneName;
            }
        }

        sectionItems.forEach(function (item) {
            item.addEventListener('click', function (e) {
                if (e.target.tagName === 'INPUT' || e.target.tagName === 'A') return;
                var zone = item.dataset.zone;
                if (zone) setActiveZone(zone);
            });
        });

        zoneElements.forEach(function (z) {
            var label = z.querySelector('.se-zone-label');
            if (label) {
                label.addEventListener('click', function () {
                    setActiveZone(z.dataset.zone);
                });
            }
        });

        // ── Zone toggle checkboxes ──────────────────────────────────

        document.querySelectorAll('.se-zone-toggle').forEach(function (cb) {
            cb.addEventListener('change', function () {
                var zone = this.dataset.zone;
                var zoneEl = document.querySelector('.se-zone[data-zone="' + zone + '"]');
                if (zoneEl) {
                    zoneEl.style.display = this.checked ? '' : 'none';
                }
            });
        });

        // ── Embedded props panel observer ───────────────────────────

        var propsPanel = document.getElementById('block-props-panel');
        if (propsPanel && propsPanel.classList.contains('se-props-embedded')) {
            var observer = new MutationObserver(function () {
                var editing = document.querySelector('.block-editor-item.block-props-editing');
                propsPanel.classList.toggle('block-props-active', !!editing);
            });
            observer.observe(
                document.querySelector('.site-editor-center') || document.body,
                { subtree: true, attributes: true, attributeFilter: ['class'] }
            );
        }

        // ── Named Block Library ─────────────────────────────────────

        var namedLibraryEl = document.getElementById('se-named-library');
        if (namedLibraryEl) {
            Cruinn.loadNamedBlocks(namedLibraryEl);
        }

        // ── Session snapshot (for Revert) ───────────────────────────
        // Captured once at page load — represents the last server-saved state.

        var _snapshot = (function () {
            var blocks = [];
            document.querySelectorAll('.block-editor-item[data-block-id]').forEach(function (el) {
                var zoneEl = el.closest('.se-zone[data-zone]');
                blocks.push({
                    id: el.dataset.blockId,
                    content: el.dataset.content || '{}',
                    settings: el.dataset.settings || '{}',
                    zone: zoneEl ? zoneEl.dataset.zone : null,
                });
            });

            // Snapshot each zone's settings JSON
            var zones = {};
            document.querySelectorAll('.se-zone[data-zone]').forEach(function (z) {
                zones[z.dataset.zone] = z.dataset.zoneSettings || '{}';
            });

            // Snapshot all left-panel form fields
            var formFields = {};
            document.querySelectorAll('.site-editor-left input, .site-editor-left select').forEach(function (inp) {
                var key = inp.name || inp.id;
                if (!key) return;
                formFields[key] = inp.type === 'checkbox' ? inp.checked : inp.value;
            });

            return { blocks: blocks, zones: zones, formFields: formFields };
        })();

        // ── Unsaved changes guard ────────────────────────────────────
        // Block changes auto-save immediately; only the left-panel settings
        // form (name, slug, layout options, zone toggles) needs Save button.
        // Warn before leaving with unsaved settings.

        var _seDirty = false;

        // Allow programmatic reloads (addBlock, undo, drag-drop) to suppress the
        // beforeunload warning so the browser never shows the "Reload site?" dialog.
        // If there are unsaved left-panel changes (e.g. header_source changed but
        // Save not yet clicked), persist them to sessionStorage so they survive
        // the programmatic reload and are restored below.
        Cruinn.suppressBeforeUnload = function () {
            if (_seDirty) {
                var pKey = 'se_pending_' + window.location.pathname;
                var saved = {};
                document.querySelectorAll('.site-editor-left input, .site-editor-left select').forEach(function (inp) {
                    var k = inp.name || inp.id;
                    if (!k) return;
                    saved[k] = inp.type === 'checkbox' ? inp.checked : inp.value;
                });
                sessionStorage.setItem(pKey, JSON.stringify(saved));
            }
            _seDirty = false;
        };

        document.querySelectorAll('.site-editor-left input, .site-editor-left select').forEach(function (inp) {
            inp.addEventListener('change', function () { _seDirty = true; });
        });

        var _seForm = document.getElementById('templateSettingsForm') ||
            document.getElementById('pageSettingsForm');
        if (_seForm) {
            _seForm.addEventListener('submit', function () { _seDirty = false; });
        }

        window.addEventListener('beforeunload', function (e) {
            if (_seDirty) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        // ── Revert button ────────────────────────────────────────────

        var _revertBtn = document.getElementById('se-revert-btn');
        if (_revertBtn) {
            _revertBtn.addEventListener('click', function () {
                if (!confirm('Revert all changes since the last save? This cannot be undone.')) return;

                _revertBtn.disabled = true;
                _revertBtn.textContent = 'Reverting…';
                _seDirty = false; // suppress beforeunload during the reload

                var ctx = window.Cruinn && Cruinn.blockContext;
                if (!ctx) { location.reload(); return; }

                // IDs that existed on load
                var originalIds = _snapshot.blocks.map(function (b) { return b.id; });

                // IDs that exist now (may include newly added blocks)
                var currentIds = [];
                document.querySelectorAll('.block-editor-item[data-block-id]').forEach(function (el) {
                    currentIds.push(el.dataset.blockId);
                });

                // Step 1: delete any blocks that were added this session
                var addedIds = currentIds.filter(function (id) { return originalIds.indexOf(id) === -1; });
                var deletePromises = addedIds.map(function (id) {
                    return fetch('/admin/blocks/' + id + '/delete', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': ctx.csrfToken },
                        body: new URLSearchParams({ _csrf_token: ctx.csrfToken }),
                    }).then(function (r) { return r.json(); });
                });

                // Step 2: restore original content+settings for each original block
                var restorePromises = _snapshot.blocks.map(function (snap) {
                    return fetch('/admin/blocks/' + snap.id, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': ctx.csrfToken },
                        body: new URLSearchParams({
                            content: snap.content,
                            settings: snap.settings,
                            _csrf_token: ctx.csrfToken,
                        }),
                    }).then(function (r) { return r.json(); });
                });

                // Step 3: restore zone settings on the template
                var isTemplate = siteEditor && siteEditor.dataset.editorFor === 'template';
                var zonePromises = [];
                if (isTemplate) {
                    Object.keys(_snapshot.zones).forEach(function (zoneName) {
                        zonePromises.push(fetch('/admin/templates/' + ctx.parentId + '/zone-settings', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': ctx.csrfToken },
                            body: new URLSearchParams({ zone: zoneName, settings: _snapshot.zones[zoneName], _csrf_token: ctx.csrfToken }),
                        }).then(function (r) { return r.json(); }));
                    });
                }

                Promise.all(deletePromises.concat(restorePromises).concat(zonePromises))
                    .then(function () {
                        // Step 4: restore form field values then submit the settings form
                        Object.keys(_snapshot.formFields).forEach(function (key) {
                            // For checkboxes, prefer the actual checkbox over a preceding hidden sibling
                            // (e.g. show_header has <input type="hidden"> then <input type="checkbox">)
                            var el = document.querySelector('input[type="checkbox"][name="' + key + '"]') ||
                                document.querySelector('input[type="checkbox"]#' + key) ||
                                document.querySelector('[name="' + key + '"], #' + key);
                            if (!el) return;
                            if (el.type === 'checkbox') {
                                el.checked = _snapshot.formFields[key];
                            } else {
                                el.value = _snapshot.formFields[key];
                            }
                        });
                        if (_seForm) {
                            _seForm.submit();
                        } else {
                            location.reload();
                        }
                    })
                    .catch(function (err) {
                        alert('Revert failed: ' + err.message);
                        _revertBtn.disabled = false;
                        _revertBtn.textContent = 'Revert';
                    });
            });
        }
        // ── Restore pending left-panel fields after a programmatic reload ─────
        // If suppressBeforeUnload saved field values before a block-add reload,
        // restore them now so the user's unsaved changes are not lost.
        (function () {
            var pKey = 'se_pending_' + window.location.pathname;
            var pJson = sessionStorage.getItem(pKey);
            if (!pJson) return;
            sessionStorage.removeItem(pKey);
            try {
                var pending = JSON.parse(pJson);
                Object.keys(pending).forEach(function (k) {
                    var el = document.querySelector('input[type="checkbox"][name="' + k + '"]') ||
                        document.querySelector('input[type="checkbox"]#' + k) ||
                        document.querySelector('[name="' + k + '"], #' + k);
                    if (!el) return;
                    if (el.type === 'checkbox') { el.checked = pending[k]; }
                    else { el.value = pending[k]; }
                });
                _seDirty = true; // still unsaved — keep the dirty guard active
            } catch (e) { }
        })();

        // ── Header source selector ────────────────────────────────

        var headerSourceSel = document.getElementById('seHeaderSource');
        if (headerSourceSel) {
            var editGlobalHeaderBtn = document.getElementById('seEditGlobalHeaderBtn');
            function applyHeaderSource(val) {
                var headerZone = document.querySelector('.se-zone[data-zone="header"]');
                if (headerZone) {
                    headerZone.classList.toggle('se-zone-inactive', val !== 'custom');
                }
                if (editGlobalHeaderBtn) {
                    editGlobalHeaderBtn.style.display = val === 'default' ? '' : 'none';
                }
            }
            // Apply initial state (picks up any value restored from sessionStorage above)
            applyHeaderSource(headerSourceSel.value);
            headerSourceSel.addEventListener('change', function () {
                applyHeaderSource(this.value);
                _seDirty = true;
            });
        }

    };

    // ── Public: Load named blocks into a container ─────────────

    Cruinn.loadNamedBlocks = function (container) {
        if (!container) return;
        fetch('/admin/blocks/named', {
            headers: { 'Accept': 'application/json' },
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                container.innerHTML = '';
                var list = data.named_blocks || [];
                if (!list.length) {
                    container.innerHTML = '<p style="font-size:11px;color:#999;padding:6px 10px">No saved blocks yet.</p>';
                    return;
                }
                list.forEach(function (nb) {
                    var item = document.createElement('div');
                    item.className = 'se-named-block-item';
                    var nameSpan = document.createElement('span');
                    nameSpan.textContent = nb.name;
                    if (nb.description) nameSpan.title = nb.description;
                    var insertBtn = document.createElement('button');
                    insertBtn.type = 'button';
                    insertBtn.className = 'btn btn-small';
                    insertBtn.textContent = '+ Insert';
                    insertBtn.addEventListener('click', function () {
                        Cruinn.insertNamedBlock(nb.id, window.__seActiveZone);
                    });
                    item.appendChild(nameSpan);
                    item.appendChild(insertBtn);
                    container.appendChild(item);
                });
            })
            .catch(function () {
                if (container) container.innerHTML = '<p style="font-size:11px;color:#c00;padding:6px 10px">Failed to load library.</p>';
            });
    };

    // ── Public: Insert a named block into the active zone ──────

    Cruinn.insertNamedBlock = function (namedBlockId, zone) {
        var ctx = Cruinn.blockContext;
        if (!ctx) return;
        fetch('/admin/blocks/named/' + namedBlockId + '/insert', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': ctx.csrfToken,
            },
            body: new URLSearchParams({
                parent_type: ctx.parentType,
                parent_id: ctx.parentId,
                zone: zone || ctx.activeZone,
                _csrf_token: ctx.csrfToken,
            }),
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) { Cruinn.suppressBeforeUnload(); window.location.reload(); }
                else alert('Insert failed: ' + (data.error || 'Unknown error'));
            })
            .catch(function (err) { alert('Error: ' + err.message); });
    };

    // ── Public: Save a block as a named block ──────────────────

    Cruinn.saveAsNamedBlock = function (blockId, name, description) {
        var ctx = Cruinn.blockContext;
        if (!ctx) return;
        fetch('/admin/blocks/named', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': ctx.csrfToken,
            },
            body: new URLSearchParams({
                source_block_id: blockId,
                name: name,
                description: description || '',
                _csrf_token: ctx.csrfToken,
            }),
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    alert('Saved as "' + name + '"');
                    var lib = document.getElementById('se-named-library');
                    if (lib) Cruinn.loadNamedBlocks(lib);
                } else {
                    alert('Save failed: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(function (err) { alert('Error: ' + err.message); });
    };

})(window.Cruinn = window.Cruinn || {});
