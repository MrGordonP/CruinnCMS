document.addEventListener('DOMContentLoaded', function () {
    (function () {
        var layout = document.getElementById('menus-layout');
        if (!layout) return;

        var csrfToken = layout.dataset.csrf;
        var locations = JSON.parse(layout.dataset.locations || '[]');

        var menuLinks         = document.querySelectorAll('.pl-nav-item[data-menu-id]');
        var mainTitle         = document.getElementById('menus-main-title');
        var mainPlaceholder   = document.getElementById('menus-main-placeholder');
        var itemsPanel        = document.getElementById('menus-items-panel');
        var detailPlaceholder = document.getElementById('menus-detail-placeholder');
        var detailContent     = document.getElementById('menus-detail-content');

        var activeMenuId = null;

        // Override the reload hook so item mutations re-fetch the fragment
        Cruinn.menuPanelRefresh = function () {
            if (activeMenuId) loadItemsPanel(activeMenuId);
        };

        menuLinks.forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                menuLinks.forEach(function (l) { l.classList.remove('active'); });
                link.classList.add('active');
                var m = JSON.parse(link.dataset.menu);
                activeMenuId = m.id;
                showDetail(m);
                loadItemsPanel(m.id);
            });
        });

        function loadItemsPanel(menuId) {
            mainTitle.textContent = '';
            mainPlaceholder.style.display = 'none';
            itemsPanel.style.display = '';
            itemsPanel.innerHTML = '<div class="pl-empty" style="padding:2rem 1rem">Loading\u2026</div>';

            fetch('/admin/menus/' + menuId + '/items-panel', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (r) { return r.text(); })
                .then(function (html) {
                    itemsPanel.innerHTML = html;
                    var activeLink = document.querySelector('.pl-nav-item[data-menu-id].active');
                    if (activeLink) {
                        mainTitle.textContent = JSON.parse(activeLink.dataset.menu).name;
                    }
                    Cruinn.initMenuEditor();
                })
                .catch(function () {
                    itemsPanel.innerHTML = '<div class="pl-empty" style="padding:1rem;color:#dc2626">Failed to load items.</div>';
                });
        }

        function showDetail(m) {
            detailPlaceholder.style.display = 'none';

            var locationOptions = locations.map(function (l) {
                return '<option value="' + escHtml(l.slug) + '"' + (m.location === l.slug ? ' selected' : '') + '>' + escHtml(l.label) + '</option>';
            }).join('');

            detailContent.innerHTML = ''
                + '<div class="pl-detail-icon">&#9776;</div>'
                + '<div class="pl-detail-title">' + escHtml(m.name) + '</div>'
                + '<div class="pl-detail-subtitle">' + escHtml(m.loc_label) + '</div>'
                + '<form method="POST" action="/admin/menus/' + m.id + '" class="pl-detail-settings">'
                +   '<input type="hidden" name="csrf_token" value="' + escHtml(csrfToken) + '">'
                +   '<div class="pl-detail-settings-section">Settings</div>'
                +   '<div class="form-group"><label>Name</label>'
                +     '<input type="text" name="name" value="' + escHtml(m.name) + '" class="form-input" required></div>'
                +   '<div class="form-group"><label>Location</label>'
                +     '<select name="location" class="form-input">' + locationOptions + '</select></div>'
                +   '<div class="form-group"><label>Description</label>'
                +     '<input type="text" name="description" value="' + escHtml(m.description) + '" class="form-input" placeholder="Where this menu appears"></div>'
                +   '<button type="submit" class="btn btn-primary" style="width:100%">Save Settings</button>'
                + '</form>'
                + '<div class="pl-detail-settings-section" style="margin-top:1rem;border-top-color:#f87171;color:#f87171">Danger</div>'
                + '<form method="POST" action="/admin/menus/' + m.id + '/delete" data-confirm="Delete this menu and all its items?">'
                +   '<input type="hidden" name="csrf_token" value="' + escHtml(csrfToken) + '">'
                +   '<button type="submit" class="btn btn-danger" style="width:100%">Delete Menu</button>'
                + '</form>';
            detailContent.style.display = '';
        }

        function escHtml(s) {
            var d = document.createElement('div');
            d.textContent = String(s != null ? s : '');
            return d.innerHTML;
        }
    }());
});
