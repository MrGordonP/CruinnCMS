/**
 * Cruinn Admin — API Helpers
 *
 * Standard AJAX POST and file upload helpers with CSRF support.
 * Depends on: utils.js
 */
(function (Cruinn) {

    /**
     * POST form-encoded data to `url`.
     * Automatically appends the CSRF token to the body and header.
     *
     * @param {string}   url
     * @param {Object}   data       Key/value pairs (plain object).
     * @param {Function} onSuccess  Called with the parsed JSON response object.
     * @param {Function} [onError]  Optional. Called with the error message string.
     */
    Cruinn.apiPost = function (url, data, onSuccess, onError) {
        var csrfToken = Cruinn.getCSRFToken();
        data._csrf_token = csrfToken;

        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: new URLSearchParams(data),
        })
            .then(function (r) { return r.json(); })
            .then(function (resp) {
                if (resp.success) {
                    if (onSuccess) onSuccess(resp);
                } else {
                    var msg = 'Error: ' + (resp.error || 'Unknown error');
                    if (onError) onError(msg); else alert(msg);
                }
            })
            .catch(function (err) {
                var msg = 'Request failed: ' + err.message;
                if (onError) onError(msg); else alert(msg);
            });
    };

    /**
     * Upload a single file to /admin/upload and call back with the returned URL.
     *
     * @param {File}     file
     * @param {Function} callback  Called with the URL string on success.
     */
    Cruinn.uploadFile = function (file, callback) {
        var csrfToken = Cruinn.getCSRFToken();
        var formData = new FormData();
        formData.append('file', file);
        formData.append('_csrf_token', csrfToken);

        fetch('/admin/upload', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken },
            body: formData,
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success && data.url) {
                    if (callback) callback(data.url);
                } else {
                    alert('Upload failed: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(function (err) {
                alert('Upload error: ' + err.message);
            });
    };

})(window.Cruinn = window.Cruinn || {});
