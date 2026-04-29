/**
 * Cruinn Portal — Main JavaScript
 *
 * Vanilla JS only, no frameworks.
 * Handles: mobile nav toggle, flash message dismissal, gallery lightbox.
 */

document.addEventListener('DOMContentLoaded', function () {
    var TABLET_BP = 1023;

    function isTabletOrMobile() {
        return window.innerWidth <= TABLET_BP;
    }

    // ── Mobile Navigation Toggle ──────────────────────────────
    const navToggle = document.querySelector('.nav-toggle');
    const mainNav = document.querySelector('.main-nav');

    if (navToggle && mainNav) {
        navToggle.addEventListener('click', function () {
            mainNav.classList.toggle('open');
            const expanded = mainNav.classList.contains('open');
            navToggle.setAttribute('aria-expanded', expanded);
        });

        // Close nav when clicking outside
        document.addEventListener('click', function (e) {
            if (!navToggle.contains(e.target) && !mainNav.contains(e.target)) {
                mainNav.classList.remove('open');
                navToggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    // ── Header/Menu accordion submenus (tablet/mobile) ───────

    function closeSubtree(li) {
        li.classList.remove('submenu-open');
        var btn = li.querySelector(':scope > .nav-submenu-toggle');
        if (btn) btn.setAttribute('aria-expanded', 'false');
        li.querySelectorAll('li.submenu-open').forEach(function (child) {
            child.classList.remove('submenu-open');
            var childBtn = child.querySelector(':scope > .nav-submenu-toggle');
            if (childBtn) childBtn.setAttribute('aria-expanded', 'false');
        });
    }

    function closeSiblingSubmenus(li) {
        var parent = li.parentElement;
        if (!parent) return;
        Array.prototype.forEach.call(parent.children, function (sib) {
            if (sib !== li && sib.classList && sib.classList.contains('submenu-open')) {
                closeSubtree(sib);
            }
        });
    }

    function setSubmenuState(li, open) {
        var btn = li.querySelector(':scope > .nav-submenu-toggle');
        li.classList.toggle('submenu-open', open);
        if (btn) btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    function hasNavigableHref(link) {
        if (!link) return false;
        var href = (link.getAttribute('href') || '').trim();
        return href !== '' && href !== '#' && !href.toLowerCase().startsWith('javascript:');
    }

    function enhanceSubmenuList(listEl) {
        listEl.querySelectorAll('li').forEach(function (li) {
            var submenu = li.querySelector(':scope > .nav-dropdown');
            var link = li.querySelector(':scope > a');
            if (!submenu || !link) return;

            li.classList.add('nav-has-children');
            if (li.querySelector(':scope > .nav-submenu-toggle')) return;

            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'nav-submenu-toggle';
            btn.setAttribute('aria-expanded', 'false');
            btn.setAttribute('aria-label', 'Toggle submenu');
            btn.innerHTML = '<span class="nav-submenu-caret" aria-hidden="true">▸</span>';
            li.insertBefore(btn, submenu);

            btn.addEventListener('click', function (event) {
                if (!isTabletOrMobile()) return;
                event.preventDefault();
                event.stopPropagation();
                var open = li.classList.contains('submenu-open');
                if (!open) closeSiblingSubmenus(li);
                setSubmenuState(li, !open);
            });

            link.addEventListener('click', function (event) {
                if (!isTabletOrMobile()) return;
                var open = li.classList.contains('submenu-open');

                if (!open) {
                    event.preventDefault();
                    closeSiblingSubmenus(li);
                    setSubmenuState(li, true);
                    return;
                }

                if (!hasNavigableHref(link)) {
                    event.preventDefault();
                    setSubmenuState(li, false);
                }
            });
        });
    }

    document.querySelectorAll('.main-nav .nav-list, .utility-nav, .site-header-custom .block-nav-menu > ul').forEach(function (listEl) {
        enhanceSubmenuList(listEl);
    });

    document.addEventListener('click', function (event) {
        if (!isTabletOrMobile()) return;
        if (event.target.closest('.site-header, .site-header-custom')) return;
        document.querySelectorAll('li.submenu-open').forEach(function (li) {
            closeSubtree(li);
        });
    });

    window.addEventListener('resize', function () {
        if (isTabletOrMobile()) return;
        document.querySelectorAll('li.submenu-open').forEach(function (li) {
            closeSubtree(li);
        });
    });

    // ── Generic responsive UI collapse (data-ui-collapse) ────
    function collapseBreakpoint(mode) {
        return mode === 'mobile' ? 767 : 1023;
    }

    function buildCollapseLabel(el) {
        var custom = (el.getAttribute('data-ui-collapse-label') || '').trim();
        if (custom) { return custom; }
        if (el.dataset.blockType === 'nav-menu') { return 'Menu'; }
        return 'Section';
    }

    document.querySelectorAll('[data-ui-collapse]').forEach(function (el) {
        var mode = (el.getAttribute('data-ui-collapse') || '').trim();
        if (mode !== 'tablet' && mode !== 'mobile') {
            return;
        }

        var bp = collapseBreakpoint(mode);
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'ui-collapse-toggle';
        btn.setAttribute('data-ui-collapse-mode', mode);
        btn.setAttribute('aria-expanded', 'false');
        btn.setAttribute('aria-label', 'Toggle ' + buildCollapseLabel(el));
        if (el.id) {
            btn.setAttribute('aria-controls', el.id);
        }
        var align = (el.getAttribute('data-ui-collapse-align') || '').trim();
        if (align) { btn.setAttribute('data-ui-collapse-align', align); }
        btn.innerHTML = '<span class="ui-collapse-toggle-icon" aria-hidden="true"><span></span><span></span><span></span></span>' +
            '<span class="ui-collapse-toggle-label">' + buildCollapseLabel(el) + '</span>';

        el.parentNode.insertBefore(btn, el);

        // nav-menu blocks need a positioning context so the open state can
        // overflow the header as an overlay rather than expanding in-flow.
        if (el.dataset.blockType === 'nav-menu' && el.parentNode) {
            if (!el.parentNode.style.position) {
                el.parentNode.style.position = 'relative';
            }
        }

        function closeTarget() {
            el.classList.remove('ui-collapse-open');
            btn.setAttribute('aria-expanded', 'false');
        }

        function syncCollapseMode() {
            var shouldCollapse = window.innerWidth <= bp;
            if (!shouldCollapse) {
                closeTarget();
            }
        }

        btn.addEventListener('click', function () {
            if (window.innerWidth > bp) {
                return;
            }
            var open = !el.classList.contains('ui-collapse-open');
            el.classList.toggle('ui-collapse-open', open);
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        document.addEventListener('click', function (event) {
            if (window.innerWidth > bp) {
                return;
            }
            if (btn.contains(event.target) || el.contains(event.target)) {
                return;
            }
            closeTarget();
        });

        window.addEventListener('resize', syncCollapseMode);
        syncCollapseMode();
    });

    // ── Flash Message Dismissal ───────────────────────────────
    document.querySelectorAll('.flash-close').forEach(function (btn) {
        btn.addEventListener('click', function () {
            this.parentElement.remove();
        });
    });

    // Auto-dismiss flash messages after 8 seconds
    document.querySelectorAll('.flash').forEach(function (flash) {
        setTimeout(function () {
            flash.style.transition = 'opacity 0.3s';
            flash.style.opacity = '0';
            setTimeout(function () { flash.remove(); }, 300);
        }, 8000);
    });

    // ── Gallery Lightbox ──────────────────────────────────────
    const galleryLinks = document.querySelectorAll('.gallery-link');
    if (galleryLinks.length > 0) {
        // Create lightbox overlay
        const overlay = document.createElement('div');
        overlay.className = 'lightbox-overlay';
        overlay.innerHTML = '<div class="lightbox-content"><img src="" alt=""><button class="lightbox-close" aria-label="Close">&times;</button></div>';
        overlay.style.cssText = 'display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.9);z-index:1000;justify-content:center;align-items:center;';
        document.body.appendChild(overlay);

        const lightboxImg = overlay.querySelector('img');
        const lightboxClose = overlay.querySelector('.lightbox-close');
        lightboxClose.style.cssText = 'position:absolute;top:20px;right:20px;background:none;border:none;color:white;font-size:2rem;cursor:pointer;';
        lightboxImg.style.cssText = 'max-width:90vw;max-height:90vh;object-fit:contain;';

        galleryLinks.forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                lightboxImg.src = this.href;
                lightboxImg.alt = this.querySelector('img')?.alt || '';
                overlay.style.display = 'flex';
            });
        });

        overlay.addEventListener('click', function (e) {
            if (e.target === overlay || e.target === lightboxClose) {
                overlay.style.display = 'none';
                lightboxImg.src = '';
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && overlay.style.display === 'flex') {
                overlay.style.display = 'none';
                lightboxImg.src = '';
            }
        });
    }

    // ── Cookie Consent Banner ─────────────────────────────────
    var banner = document.getElementById('cookie-consent');
    var acceptBtn = document.getElementById('cookie-accept');

    if (banner && acceptBtn) {
        // Show banner only if not previously accepted
        if (!localStorage.getItem('cookie_consent')) {
            banner.style.display = 'block';
        }

        acceptBtn.addEventListener('click', function () {
            localStorage.setItem('cookie_consent', '1');
            banner.style.display = 'none';

            // Record consent server-side (fire and forget)
            var csrfMeta = document.querySelector('input[name="_csrf_token"]');
            var token = csrfMeta ? csrfMeta.value : '';
            fetch('/gdpr/consent', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': token },
                body: 'type=cookies&granted=1&_csrf_token=' + encodeURIComponent(token)
            }).catch(function () { /* consent recorded locally is sufficient */ });
        });
    }

});
