/**
 * Cruinn Admin — Template Visual Editor (TVE)
 *
 * Zone section selection with highlight, live preview updates for header/footer/
 * title/breadcrumbs visibility, title alignment picker, content width,
 * sidebar toggle and position, zones JSON synchronisation, and summary panel.
 *
 * No external Cruinn dependencies.
 */
(function (Cruinn) {

    Cruinn.initTemplateEditor = function () {
        var editor = document.getElementById('tveEditor');
        if (!editor) return;

        var preview = document.getElementById('tvePreview');
        var propsContainer = document.getElementById('tveProps');
        var zonesInput = document.getElementById('tveZones');
        var zonesText = document.getElementById('tpl_zones');

        var zones = preview.querySelectorAll('.tve-zone');
        var panels = propsContainer.querySelectorAll('.tve-panel');
        var selectedSection = null;

        // ── Section selection ───────────────────────────────────────

        zones.forEach(function (zone) {
            zone.addEventListener('click', function () {
                var section = zone.dataset.section;
                if (selectedSection === section) {
                    deselectAll();
                    return;
                }
                selectSection(section);
            });
        });

        document.addEventListener('click', function (e) {
            if (!preview.contains(e.target) && !propsContainer.contains(e.target)) {
                deselectAll();
            }
        });

        function selectSection(section) {
            selectedSection = section;
            zones.forEach(function (z) {
                z.classList.toggle('tve-selected', z.dataset.section === section);
            });
            panels.forEach(function (p) {
                p.classList.toggle('tve-panel-active', p.dataset.panel === section);
            });
        }

        function deselectAll() {
            selectedSection = null;
            zones.forEach(function (z) { z.classList.remove('tve-selected'); });
            panels.forEach(function (p) {
                p.classList.toggle('tve-panel-active', p.dataset.panel === 'overview');
            });
            updateSummary();
        }

        // ── Live preview: Header ────────────────────────────────────

        var showHeader = document.getElementById('tveShowHeader');
        if (showHeader) {
            showHeader.addEventListener('change', function () {
                var zone = preview.querySelector('.tve-zone-header');
                if (zone) zone.classList.toggle('tve-hidden', !this.checked);
            });
        }

        // ── Live preview: Footer ────────────────────────────────────

        var showFooter = document.getElementById('tveShowFooter');
        if (showFooter) {
            showFooter.addEventListener('change', function () {
                var zone = preview.querySelector('.tve-zone-footer');
                if (zone) zone.classList.toggle('tve-hidden', !this.checked);
            });
        }

        // ── Live preview: Title ─────────────────────────────────────

        var showTitle = document.getElementById('tveShowTitle');
        if (showTitle) {
            showTitle.addEventListener('change', function () {
                var zone = preview.querySelector('.tve-zone-title');
                if (zone) zone.classList.toggle('tve-hidden', !this.checked);
            });
        }

        // ── Live preview: Breadcrumbs ───────────────────────────────

        var showBreadcrumbs = document.getElementById('tveShowBreadcrumbs');
        if (showBreadcrumbs) {
            showBreadcrumbs.addEventListener('change', function () {
                var zone = preview.querySelector('.tve-zone-breadcrumbs');
                if (zone) zone.classList.toggle('tve-hidden', !this.checked);
            });
        }

        // ── Title alignment picker ──────────────────────────────────

        var alignPicker = document.getElementById('tveTitleAlignPicker');
        var alignInput = document.getElementById('tveTitleAlign');
        if (alignPicker) {
            alignPicker.addEventListener('click', function (e) {
                var btn = e.target.closest('.tve-align-btn');
                if (!btn) return;
                var align = btn.dataset.align;

                alignPicker.querySelectorAll('.tve-align-btn').forEach(function (b) {
                    b.classList.toggle('active', b === btn);
                });

                if (alignInput) alignInput.value = align;
                var titleZone = preview.querySelector('.tve-zone-title');
                if (titleZone) titleZone.style.textAlign = align;
            });
        }

        // ── Content width ───────────────────────────────────────────

        var contentWidth = document.getElementById('tveContentWidth');
        if (contentWidth) {
            contentWidth.addEventListener('change', function () {
                var layout = preview.querySelector('.tve-content-layout');
                if (layout) layout.dataset.width = this.value;
            });
        }

        // ── Sidebar toggle ──────────────────────────────────────────

        var sidebarToggle = document.getElementById('tveSidebarToggle');
        var sidebarOpts = document.getElementById('tveSidebarOpts');
        if (sidebarToggle) {
            sidebarToggle.addEventListener('change', function () {
                var layout = preview.querySelector('.tve-content-layout');
                if (layout) layout.classList.toggle('tve-has-sidebar', this.checked);
                if (sidebarOpts) sidebarOpts.classList.toggle('tve-disabled', !this.checked);
                updateZonesFromSidebar();
            });
        }

        // ── Sidebar position ────────────────────────────────────────

        var posRadios = editor.querySelectorAll('input[name="sidebar_position"]');
        posRadios.forEach(function (radio) {
            radio.addEventListener('change', function () {
                var layout = preview.querySelector('.tve-content-layout');
                if (layout) layout.classList.toggle('tve-sidebar-left', this.value === 'left');
            });
        });

        // ── Zones synchronisation ───────────────────────────────────

        function parseZones() {
            try {
                var parsed = JSON.parse(zonesInput.value);
                return Array.isArray(parsed) ? parsed : ['main'];
            } catch (e) {
                return ['main'];
            }
        }

        function updateZonesFromSidebar() {
            var current = parseZones();
            var hasSidebar = sidebarToggle && sidebarToggle.checked;
            var idx = current.indexOf('sidebar');

            if (hasSidebar && idx === -1) {
                current.push('sidebar');
            } else if (!hasSidebar && idx > -1) {
                current.splice(idx, 1);
            }

            var val = JSON.stringify(current);
            zonesInput.value = val;
            if (zonesText) zonesText.value = val;
        }

        if (zonesText) {
            zonesText.addEventListener('blur', function () {
                try {
                    var parsed = JSON.parse(this.value);
                    if (Array.isArray(parsed)) {
                        zonesInput.value = this.value;
                        var layout = preview.querySelector('.tve-content-layout');
                        var hasSidebar = parsed.indexOf('sidebar') > -1;
                        if (layout) layout.classList.toggle('tve-has-sidebar', hasSidebar);
                        if (sidebarToggle) sidebarToggle.checked = hasSidebar;
                        if (sidebarOpts) sidebarOpts.classList.toggle('tve-disabled', !hasSidebar);
                    }
                } catch (e) { }
            });
        }

        // ── Summary panel ───────────────────────────────────────────

        function updateSummary() {
            var h = document.getElementById('tveSumHeader');
            var b = document.getElementById('tveSumBreadcrumbs');
            var t = document.getElementById('tveSumTitle');
            var c = document.getElementById('tveSumContent');
            var f = document.getElementById('tveSumFooter');

            if (h) h.textContent = (showHeader && showHeader.checked) ? 'Visible' : 'Hidden';
            if (b) b.textContent = (showBreadcrumbs && showBreadcrumbs.checked) ? 'Visible' : 'Hidden';
            if (t) {
                if (showTitle && showTitle.checked) {
                    var al = alignInput ? alignInput.value : 'left';
                    t.textContent = 'Visible, ' + al.charAt(0).toUpperCase() + al.slice(1);
                } else {
                    t.textContent = 'Hidden';
                }
            }
            if (c) {
                var w = contentWidth ? contentWidth.value : 'default';
                var sb = sidebarToggle && sidebarToggle.checked;
                if (sb) {
                    var pos = '';
                    posRadios.forEach(function (r) { if (r.checked) pos = r.value; });
                    c.textContent = w.charAt(0).toUpperCase() + w.slice(1) + ' width, with sidebar (' + pos + ')';
                } else {
                    c.textContent = w.charAt(0).toUpperCase() + w.slice(1) + ' width';
                }
            }
            if (f) f.textContent = (showFooter && showFooter.checked) ? 'Visible' : 'Hidden';
        }
    };

})(window.Cruinn = window.Cruinn || {});
