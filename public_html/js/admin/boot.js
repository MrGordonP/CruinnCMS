(function () {
    try {
        if (localStorage.getItem('admin-layout-wide') === '1') {
            document.documentElement.classList.add('admin-layout-wide');
        }
    } catch (e) {
        // Ignore storage access failures.
    }
}());
