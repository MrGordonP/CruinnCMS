/**
 * Cruinn Admin — Entry Point
 *
 * DOMContentLoaded orchestrator. Initialises all modules in dependency order.
 *
 * Loading order (must match <script> tags in layout.php):
 *   utils → api → media-browser → rte → gallery
 *   → menu-editor → dashboard-config → nav-config
 *   → template-editor → index  (this file)
 */
(function (Cruinn) {

    document.addEventListener('DOMContentLoaded', function () {

        // ── Media browser is always needed ─────────────────────────

        Cruinn.initMediaBrowser();

        // ── Slug auto-generation (shared) ──────────────────────────

        var titleInput = document.getElementById('title');
        var slugInput = document.getElementById('slug');
        if (titleInput && slugInput && !slugInput.value) {
            titleInput.addEventListener('input', function () {
                slugInput.value = this.value
                    .toLowerCase()
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/-+/g, '-')
                    .replace(/^-|-$/g, '');
            });
        }

        // ── Init admin modules ───────────────────────────────────

        Cruinn.initRichTextEditors();
        Cruinn.initDashboardConfig();
        Cruinn.initNavConfig();
        Cruinn.initMenuEditor();
        Cruinn.initTemplateEditor();
    });

})(window.Cruinn = window.Cruinn || {});
