/**
 * Cruinn Admin — Entry Point
 *
 * DOMContentLoaded orchestrator. Initialises all modules in dependency order.
 *
 * Loading order (must match <script> tags in layout.php):
 *   utils → api → media-browser → rte → gallery
 *   → block-editor/core → block-editor/undo → block-editor/properties → block-editor/drag
 *   → menu-editor → social-hub → dashboard-config → nav-config
 *   → site-editor → template-editor → index  (this file)
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

        // ── Non-block-editor pages ──────────────────────────────────

        var blockEditor = document.querySelector('.block-editor');

        if (!blockEditor) {
            Cruinn.initRichTextEditors();
            Cruinn.initSocialHub();
            Cruinn.initDashboardConfig();
            Cruinn.initNavConfig();
            Cruinn.initMenuEditor();
            Cruinn.initSiteEditor();
            Cruinn.initTemplateEditor();
            return;
        }

        // ── Block editor setup ──────────────────────────────────────

        var csrfToken = Cruinn.getCSRFToken();
        var parentType = blockEditor.dataset.parentType || 'page';
        var parentId = blockEditor.dataset.parentId;
        var editorMode = blockEditor.dataset.editorMode || 'structured';

        Cruinn.blockContext = {
            csrfToken: csrfToken,
            parentType: parentType,
            parentId: parentId,
            editorMode: editorMode,
            activeZone: 'body',

            // Mutable working state — set by sub-modules
            propsTargetBlock: null,
            propsTargetZone: null,
            propsPanel: null,
            propsSaveTimer: null,
            zoneSaveTimer: null,
            undoStack: [],
            redoStack: [],
            maxUndoSteps: 50,
        };

        // ── Block editor module init ────────────────────────────────

        Cruinn.initBlockEditorCore();
        Cruinn.initRichTextEditors();
        Cruinn.initGalleryEditors();
        Cruinn.initUndoSystem();
        Cruinn.initPropertiesPanel();

        // Drag is mode-specific (freeform / structured / zone-aware)
        if (editorMode === 'freeform') {
            Cruinn.initFreeformDrag();
        } else if (editorMode === 'structured') {
            Cruinn.initStructuredDrag();
        }
        Cruinn.initZoneDrag(); // always try — only activates when zone canvases are present

        // ── Other admin modules ─────────────────────────────────────

        Cruinn.initSocialHub();
        Cruinn.initDashboardConfig();
        Cruinn.initNavConfig();
        Cruinn.initMenuEditor();
        Cruinn.initSiteEditor();
        Cruinn.initTemplateEditor();
    });

})(window.Cruinn = window.Cruinn || {});
