(function () {
    // Settings/site-builder panel layout toggle (2-col vs 1-col).
    // Works for both .acp-wrapper and .sb-wrapper targets.
    // menus/edit also uses this for `.menu-item-editor` stacking.
    document.querySelectorAll('.acp-layout-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var layout  = this.dataset.layout;
            var wrapper = document.querySelector('.acp-wrapper');
            document.querySelectorAll('.acp-layout-btn').forEach(function (b) { b.classList.remove('active'); });
            this.classList.add('active');

            // Settings pages: toggle .acp-two-col on the wrapper
            if (wrapper) {
                if (layout === '2') {
                    wrapper.classList.add('acp-two-col');
                } else {
                    wrapper.classList.remove('acp-two-col');
                }
            }

            // menus/edit: toggle .menu-editor-stacked on the editor panel
            var editor = document.querySelector('.menu-item-editor');
            if (editor) {
                editor.classList.toggle('menu-editor-stacked', layout === '1');
            }

            // Persist to session
            var csrfInput = document.querySelector('input[name="_csrf_token"]');
            if (!csrfInput) return;
            var fd = new FormData();
            fd.append('layout', layout);
            fd.append('_csrf_token', csrfInput.value);
            fetch('/admin/settings/layout', { method: 'POST', body: fd });
        });
    });
}());
