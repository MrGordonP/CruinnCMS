(function () {
    // Delete: button triggers associated hidden form
    document.querySelectorAll('button.admin-db-delete-btn').forEach(function (button) {
        button.addEventListener('click', function () {
            if (!window.confirm('Delete this row?')) return;
            var rowId = button.getAttribute('data-rowid');
            var form = document.getElementById(rowId + '-delete-form');
            if (form) form.submit();
        });
    });

    // Inline edit toggle — show edit row, hide display row
    document.querySelectorAll('button.admin-db-edit-btn').forEach(function (button) {
        button.addEventListener('click', function () {
            var rowId = button.getAttribute('data-rowid');
            document.getElementById(rowId + '-display').style.display = 'none';
            document.getElementById(rowId + '-edit').style.display = '';
        });
    });

    // Cancel — restore display row, hide edit row
    document.querySelectorAll('button.admin-db-cancel-btn').forEach(function (button) {
        button.addEventListener('click', function () {
            var rowId = button.getAttribute('data-rowid');
            document.getElementById(rowId + '-display').style.display = '';
            document.getElementById(rowId + '-edit').style.display = 'none';
        });
    });
}());
