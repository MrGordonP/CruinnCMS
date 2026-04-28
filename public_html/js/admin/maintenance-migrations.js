// Disable the apply-migrations button on click to prevent double-submit
(function () {
    var btn = document.getElementById('admin-migrations-apply');
    if (!btn) { return; }
    btn.addEventListener('click', function () {
        this.disabled = true;
        this.textContent = 'Applying\u2026';
        this.form.submit();
    });
}());
