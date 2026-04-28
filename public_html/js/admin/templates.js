// Template-type radio toggle — hide per-page fields for content templates
(function () {
    var radios = document.querySelectorAll('input[name="template_type"]');
    var pageFields = document.getElementById('tpl_page_fields');
    if (!pageFields) { return; }
    function toggle() {
        var checked = document.querySelector('input[name="template_type"]:checked');
        if (!checked) { return; }
        pageFields.style.display = checked.value === 'content' ? 'none' : '';
    }
    radios.forEach(function (r) { r.addEventListener('change', toggle); });
}());
