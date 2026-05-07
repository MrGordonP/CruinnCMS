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
            // Blog articles use date-based slugs (YYYY-MM-DD-##)
            var isBlogArticle = window.location.pathname.includes('/admin/articles');
            
            if (isBlogArticle) {
                // Generate date-based slug on focus (don't auto-update while typing)
                slugInput.addEventListener('focus', function () {
                    if (!this.value) {
                        var today = new Date();
                        var yyyy = today.getFullYear();
                        var mm = String(today.getMonth() + 1).padStart(2, '0');
                        var dd = String(today.getDate()).padStart(2, '0');
                        this.value = yyyy + '-' + mm + '-' + dd + '-01';
                        this.placeholder = 'Change to YYYY-MM-DD-## or custom';
                    }
                }, { once: true });
            } else {
                // Other content: auto-generate slug from title
                titleInput.addEventListener('input', function () {
                    slugInput.value = this.value
                        .toLowerCase()
                        .replace(/[^a-z0-9]+/g, '-')
                        .replace(/-+/g, '-')
                        .replace(/^-|-$/g, '');
                });
            }
        }

        // ── Init admin modules ───────────────────────────────────

        Cruinn.initRichTextEditors();
        Cruinn.initDashboardConfig();
        Cruinn.initNavConfig();
        Cruinn.initMenuEditor();
        Cruinn.initTemplateEditor();
    });

})(window.Cruinn = window.Cruinn || {});
