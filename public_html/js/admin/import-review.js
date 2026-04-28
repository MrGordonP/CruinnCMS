// Sync the hidden skip field with the import-row checkbox
(function () {
    document.querySelectorAll('.import-row input[type=checkbox]').forEach(function (cb) {
        cb.addEventListener('change', function () {
            var idx = this.name.match(/\[(\d+)\]/)[1];
            document.getElementById('skip-' + idx).value = this.checked ? '0' : '1';
        });
    });
}());
