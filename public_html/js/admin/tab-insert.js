(function () {
    // Generic Tab-key handler for textareas.
    // Add data-tab-insert="true" on any <textarea> to enable 4-space tab insertion.
    document.querySelectorAll('textarea[data-tab-insert]').forEach(function (ta) {
        ta.addEventListener('keydown', function (e) {
            if (e.key !== 'Tab') return;
            e.preventDefault();
            var start = this.selectionStart;
            var end   = this.selectionEnd;
            this.value = this.value.substring(0, start) + '    ' + this.value.substring(end);
            this.selectionStart = this.selectionEnd = start + 4;
        });
    });
}());
