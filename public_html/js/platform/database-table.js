(function () {
    document.querySelectorAll('button.db-delete-btn').forEach(function (button) {
        button.addEventListener('click', function (event) {
            if (!window.confirm('Delete this row?')) {
                event.preventDefault();
            }
        });
    });
}());
