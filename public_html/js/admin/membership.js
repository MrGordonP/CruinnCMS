/**
 * membership.js — shared JS for membership admin pages.
 *
 * Handles:
 *   - Member list: bulk selection bar, sub-row toggle, row navigation
 *   - Subscriptions list: scroll selected row into view
 *   - Shared: data-confirm dialogs, data-stop-propagation, data-action buttons
 */
(function () {
    'use strict';

    /* ── Bulk selection bar ─────────────────────────────────────────── */
    function updateBulkBar() {
        var checked = document.querySelectorAll('.member-cb:checked');
        var bar     = document.getElementById('bulk-bar');
        var count   = document.getElementById('bulk-count');
        if (bar)   { bar.style.display = checked.length > 0 ? 'flex' : 'none'; }
        if (count) { count.textContent = checked.length + ' selected'; }
    }

    /* ── Subscription row expand/collapse ───────────────────────────── */
    function toggleSubRow(mid) {
        var row = document.getElementById('sub-row-' + mid);
        var btn = document.querySelector('.sub-expand-btn[data-mid="' + mid + '"]');
        if (!row) { return; }
        var open = row.style.display !== 'none';
        row.style.display = open ? 'none' : 'table-row';
        if (btn) {
            var parts = btn.textContent.trim().split(/\s+/);
            var n = parts.length > 1 ? parts[parts.length - 1] : '';
            btn.textContent = (open ? '\u25B8' : '\u25BE') + (n ? ' ' + n : '');
        }
    }

    /* ── Scroll selected row into view ──────────────────────────────── */
    function scrollToSelected() {
        var row = document.querySelector('.pl-main-scroll tr.selected');
        if (row) {
            row.scrollIntoView({ block: 'center', behavior: 'smooth' });
        }
    }

    /* ── DOM ready ───────────────────────────────────────────────────── */
    document.addEventListener('DOMContentLoaded', function () {
        scrollToSelected();

        /* Select-all checkbox */
        var selectAll = document.getElementById('select-all');
        if (selectAll) {
            selectAll.addEventListener('change', function () {
                document.querySelectorAll('.member-cb').forEach(function (cb) {
                    cb.checked = selectAll.checked;
                });
                updateBulkBar();
            });
        }

        /* Bulk checkbox change (event delegation) */
        document.addEventListener('change', function (e) {
            if (e.target.classList.contains('member-cb')) {
                updateBulkBar();
            }
        });

        /* Click delegation */
        document.addEventListener('click', function (e) {

            /* Sub-row expand button — must be first so it doesn't trigger row nav */
            var expandBtn = e.target.closest('.sub-expand-btn[data-mid]');
            if (expandBtn) {
                toggleSubRow(expandBtn.dataset.mid);
                return;
            }

            /* Elements that block row navigation (checkboxes, profile links) */
            if (e.target.closest('[data-stop-propagation]')) {
                return;
            }

            /* Confirmation dialogs */
            var confirmEl = e.target.closest('[data-confirm]');
            if (confirmEl) {
                if (!window.confirm(confirmEl.dataset.confirm)) {
                    e.preventDefault();
                    return;
                }
                /* Allow default action (form submit etc.) to continue */
                return;
            }

            /* Deselect-all button */
            if (e.target.closest('[data-action="deselect-all"]')) {
                document.querySelectorAll('.member-cb').forEach(function (cb) { cb.checked = false; });
                updateBulkBar();
                return;
            }

            /* Row navigation */
            var row = e.target.closest('[data-row-url]');
            if (row) {
                window.location = row.dataset.rowUrl;
                return;
            }
        });

        /* Double-click row navigation to profile page */
        document.addEventListener('dblclick', function (e) {
            var row = e.target.closest('[data-row-profile-url]');
            if (row) {
                e.preventDefault();
                window.location = row.dataset.rowProfileUrl;
            }
        });
    });
}());
