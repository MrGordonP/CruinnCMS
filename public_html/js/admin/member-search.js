(function () {
    // Member search typeahead for users/show and users/edit.
    // Reads the search URL from data-search-url on #member-search-input.
    var input = document.getElementById('member-search-input');
    var list  = document.getElementById('member-search-list');
    if (!input || !list) return;

    var searchUrl = input.dataset.searchUrl;
    var timer, activeIdx = -1;

    function showList(members) {
        list.innerHTML = '';
        activeIdx = -1;
        if (!members.length) { list.style.display = 'none'; return; }
        members.forEach(function (m, i) {
            var li  = document.createElement('li');
            li.style.cssText = 'padding:0.45rem 0.75rem;cursor:pointer;border-bottom:1px solid #eef1ef;line-height:1.3';
            var num = m.membership_number ? ' #' + m.membership_number : '';
            li.innerHTML = '<strong>' + m.display_name.replace(/</g, '&lt;') + num + '</strong>'
                         + '<span style="color:#888;font-size:0.8rem;display:block">' + m.email.replace(/</g, '&lt;') + ' &mdash; ' + m.status + '</span>';
            li.addEventListener('mousedown', function (e) {
                e.preventDefault();
                input.value = m.email;
                list.style.display = 'none';
            });
            li.addEventListener('mouseover', function () { setActive(i); });
            list.appendChild(li);
        });
        list.style.display = 'block';
    }

    function setActive(i) {
        var items = list.querySelectorAll('li');
        items.forEach(function (el, idx) { el.style.background = idx === i ? '#e8f5ef' : ''; });
        activeIdx = i;
    }

    input.addEventListener('input', function () {
        clearTimeout(timer);
        var q = input.value.trim();
        if (q.length < 2) { list.style.display = 'none'; return; }
        timer = setTimeout(function () {
            fetch(searchUrl + '?q=' + encodeURIComponent(q))
                .then(function (r) { return r.json(); })
                .then(showList)
                .catch(function () { list.style.display = 'none'; });
        }, 220);
    });

    input.addEventListener('keydown', function (e) {
        var items = list.querySelectorAll('li');
        if (!items.length || list.style.display === 'none') return;
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setActive(Math.min(activeIdx + 1, items.length - 1));
            if (items[activeIdx]) items[activeIdx].scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setActive(Math.max(activeIdx - 1, 0));
            if (items[activeIdx]) items[activeIdx].scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'Enter' && activeIdx >= 0) {
            e.preventDefault();
            items[activeIdx].dispatchEvent(new MouseEvent('mousedown'));
        } else if (e.key === 'Escape') {
            list.style.display = 'none';
        }
    });

    document.addEventListener('click', function (e) {
        if (!input.contains(e.target) && !list.contains(e.target)) list.style.display = 'none';
    });
}());
