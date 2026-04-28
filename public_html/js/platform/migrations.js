(function () {
    var applyAllButton = document.getElementById('migrations-apply-all');

    if (applyAllButton) {
        applyAllButton.addEventListener('click', function () {
            this.disabled = true;
            this.textContent = 'Applying\u2026';
        });
    }

    document.querySelectorAll('button.migrations-apply-row').forEach(function (button) {
        button.addEventListener('click', function () {
            this.disabled = true;
            this.textContent = '\u2026';
        });
    });
}());
