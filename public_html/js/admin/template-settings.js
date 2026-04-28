// Template settings — sidebar zone toggle
(function () {
    var toggle = document.getElementById('tpl_sidebar_toggle');
    if (!toggle) { return; }
    toggle.addEventListener('change', function () {
        var pos = document.getElementById('tpl_sidebar_pos');
        if (pos) { pos.disabled = !this.checked; }
        var inp = document.getElementById('tplZones');
        if (!inp) { return; }
        var z = JSON.parse(inp.value || '["main"]');
        if (this.checked) {
            if (z.indexOf('sidebar') < 0) { z.push('sidebar'); }
        } else {
            z = z.filter(function (v) { return v !== 'sidebar'; });
        }
        inp.value = JSON.stringify(z);
    });
}());
