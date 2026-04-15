/**
 * Cruinn Portal — Main JavaScript
 *
 * Vanilla JS only, no frameworks.
 * Handles: mobile nav toggle, flash message dismissal, gallery lightbox.
 */

document.addEventListener('DOMContentLoaded', function () {

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
