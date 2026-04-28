/**
 * Cruinn Admin — Shared Utilities
 *
 * HTML escaping, safe JSON parsing, debounce, and CSRF helpers.
 * No dependencies — must be loaded first.
 */
(function (Cruinn) {

    /**
     * Escape a string for safe insertion as HTML text.
     */
    Cruinn.escapeHtml = function (str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    };

    /**
     * Escape a string for safe use in an HTML attribute value.
     */
    Cruinn.escapeAttr = function (str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    };

    /**
     * Safely parse a JSON settings string.
     * PHP may serialise an empty array as `[]` instead of `{}`; coerce to object.
     */
    Cruinn.parseSettings = function (raw) {
        var s = {};
        try { s = JSON.parse(raw || '{}'); } catch (e) { }
        if (Array.isArray(s)) s = {};
        return s;
    };

    /**
     * Read the CSRF token from the DOM.
     */
    Cruinn.getCSRFToken = function () {
        var el = document.querySelector('input[name="_csrf_token"]');
        return el ? el.value : '';
    };

    /**
     * Return a debounced version of fn that fires after `delay` ms of inactivity.
     */
    Cruinn.debounce = function (fn, delay) {
        var timer = null;
        return function () {
            var args = arguments;
            var ctx = this;
            clearTimeout(timer);
            timer = setTimeout(function () {
                fn.apply(ctx, args);
            }, delay);
        };
    };

    /**
     * Show a brief notification toast.
     * @param {string} msg   Message text.
     * @param {string} type  'success' | 'error' | 'warning' | 'info' (default).
     * @param {number} [duration]  Auto-dismiss after ms (default 4000).
     */
    Cruinn.notify = function (msg, type, duration) {
        var toast = document.createElement('div');
        toast.className = 'iga-toast iga-toast-' + (type || 'info');
        toast.textContent = msg;
        document.body.appendChild(toast);
        requestAnimationFrame(function () { toast.classList.add('iga-toast-visible'); });
        setTimeout(function () {
            toast.classList.remove('iga-toast-visible');
            setTimeout(function () { if (toast.parentNode) toast.parentNode.removeChild(toast); }, 300);
        }, duration || 4000);
    };

})(window.Cruinn = window.Cruinn || {});
