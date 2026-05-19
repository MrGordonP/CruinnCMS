// Template settings — layout zones are authoritative
(function () {
    var zonesInput = document.getElementById('tplZones');
    if (!zonesInput) { return; }

    function getZones() {
        try {
            var parsed = JSON.parse(zonesInput.value || '["main"]');
            return Array.isArray(parsed) ? parsed : ['main'];
        } catch (_e) {
            return ['main'];
        }
    }

    function setZones(zones) {
        var clean = zones.filter(function (z) {
            return typeof z === 'string' && /^[a-z0-9_-]+$/.test(z);
        });
        if (clean.indexOf('main') < 0) {
            clean.unshift('main');
        }
        zonesInput.value = JSON.stringify(clean);
    }

    function applyZoneToggle(zoneName, checked) {
        var zones = getZones();
        if (checked) {
            if (zones.indexOf(zoneName) < 0) { zones.push(zoneName); }
        } else {
            zones = zones.filter(function (z) { return z !== zoneName; });
        }
        setZones(zones);
    }

    function bindToggle(toggleId, zoneName, dependentIds, legacyHiddenId) {
        var toggle = document.getElementById(toggleId);
        if (!toggle) { return; }
        function sync() {
            applyZoneToggle(zoneName, toggle.checked);
            (dependentIds || []).forEach(function (id) {
                var el = document.getElementById(id);
                if (el) { el.disabled = !toggle.checked; }
            });
            if (legacyHiddenId) {
                var hidden = document.getElementById(legacyHiddenId);
                if (hidden) { hidden.value = toggle.checked ? '1' : '0'; }
            }
        }
        toggle.addEventListener('change', sync);
        sync();
    }

    bindToggle('tpl_header_toggle', 'header', ['tpl_header_source'], 'tpl_show_header');
    bindToggle('tpl_footer_toggle', 'footer', [], 'tpl_show_footer');
    bindToggle('tpl_sidebar_toggle', 'sidebar', ['tpl_sidebar_pos', 'tpl_sidebar_source']);
}());
