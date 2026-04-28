// Content Set Rows — add/edit row panel + drag-to-reorder
(function () {
    var app = document.getElementById('rows-app');
    if (!app) { return; }

    var setId       = app.dataset.setId || '';
    var initEditId  = app.dataset.editRowId ? parseInt(app.dataset.editRowId) : null;

    function openAddRow() {
        document.getElementById('row-form-title').textContent = 'Add Row';
        var addForm = document.getElementById('add-row-form');
        if (addForm) { addForm.style.display = ''; }
        document.querySelectorAll('[id^="edit-row-form-"]').forEach(function (f) { f.style.display = 'none'; });
        document.querySelectorAll('.cse-data-row').forEach(function (r) { r.classList.remove('selected'); });
    }

    function openEditRow(id) {
        document.getElementById('row-form-title').textContent = 'Edit Row';
        var addForm = document.getElementById('add-row-form');
        if (addForm) { addForm.style.display = 'none'; }
        document.querySelectorAll('[id^="edit-row-form-"]').forEach(function (f) { f.style.display = 'none'; });
        var form = document.getElementById('edit-row-form-' + id);
        if (form) { form.style.display = ''; }
        document.querySelectorAll('.cse-data-row').forEach(function (r) {
            r.classList.toggle('selected', parseInt(r.dataset.rowId) === id);
        });
    }

    // If arriving with ?edit= pre-selected
    if (initEditId) { openEditRow(initEditId); }

    // + Add Row button
    var addBtn = document.getElementById('rows-add-btn');
    if (addBtn) { addBtn.addEventListener('click', openAddRow); }

    // Edit buttons and Cancel buttons (delegated)
    document.addEventListener('click', function (e) {
        var editBtn = e.target.closest('[data-edit-row]');
        if (editBtn && app.contains(editBtn)) { openEditRow(parseInt(editBtn.dataset.editRow)); return; }
        if (e.target.closest('.rows-cancel-btn')) { openAddRow(); }
    });

    // Row click to open edit
    document.querySelectorAll('.cse-data-row').forEach(function (row) {
        row.addEventListener('click', function (e) {
            if (e.target.closest('form') || e.target.closest('button') || e.target.closest('a')) { return; }
            openEditRow(parseInt(this.dataset.rowId));
        });
    });

    // Drag-to-reorder rows (AJAX)
    (function () {
        var tbody = document.getElementById('row-tbody');
        if (!tbody || !setId) { return; }
        var dragging = null;

        tbody.addEventListener('dragstart', function (e) {
            dragging = e.target.closest('tr');
            if (dragging) { dragging.classList.add('dragging'); }
        });
        tbody.addEventListener('dragend', function () {
            if (dragging) { dragging.classList.remove('dragging'); }
            dragging = null;
            saveRowOrder();
        });
        tbody.addEventListener('dragover', function (e) {
            e.preventDefault();
            var over = e.target.closest('tr');
            if (over && over !== dragging) {
                var rect = over.getBoundingClientRect();
                var after = e.clientY > rect.top + rect.height / 2;
                tbody.insertBefore(dragging, after ? over.nextSibling : over);
            }
        });

        function saveRowOrder() {
            var order = Array.from(tbody.querySelectorAll('tr[data-row-id]'))
                .map(function (r) { return parseInt(r.dataset.rowId); });
            fetch('/admin/content/' + setId + '/rows/reorder', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': (document.querySelector('meta[name="csrf-token"]') || {}).content || ''
                },
                body: JSON.stringify({ order: order })
            });
        }
    }());

}());
