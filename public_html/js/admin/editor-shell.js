(function () {
    var wrap = document.getElementById('editor-wrap');
    if (!wrap) return;

    var contentSetsJson = wrap.dataset.contentSets || '[]';
    try {
        window.CONTENT_SETS = JSON.parse(contentSetsJson);
    } catch (e) {
        window.CONTENT_SETS = [];
    }

    document.querySelectorAll('button.editor-zone-link, button.editor-zone-edit-link').forEach(function (button) {
        button.addEventListener('click', function () {
            var menu = this.nextElementSibling;
            if (!menu) return;
            menu.classList.toggle('open');
        });
    });

    document.querySelectorAll('.editor-site-nav-toggle').forEach(function (toggle) {
        toggle.addEventListener('click', function () {
            var section = this.closest('.editor-site-nav');
            if (!section) return;
            section.classList.toggle('collapsed');
        });
    });

    document.querySelectorAll('.editor-site-nav-label').forEach(function (label) {
        label.addEventListener('click', function () {
            var group = this.closest('.editor-site-nav-group');
            if (!group) return;
            group.classList.toggle('collapsed');
        });
    });

    document.querySelectorAll('.editor-panel-toggle').forEach(function (toggle) {
        toggle.addEventListener('click', function () {
            var section = this.closest('.editor-panel-section');
            if (!section) return;
            section.classList.toggle('collapsed');
        });
    });
}());

PanelCollapse.init([
    { panelId: 'editor-left', toggleId: 'pl-panel-left-toggle', storeKey: 'ed_left', side: 'left' },
    { panelId: 'editor-props', toggleId: 'pl-panel-right-toggle', storeKey: 'ed_right', side: 'right' },
]);
PanelCollapse.initResize('pl-panel-left-resize', 'editor-left', 'ed_left_w');
