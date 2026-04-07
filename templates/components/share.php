<?php
/**
 * Social sharing buttons component.
 * 
 * Uses the current page URL and title. Include on any detail page.
 * No third-party scripts — uses native share URLs for privacy.
 */

$shareUrl = urlencode((\Cruinn\App::config('site.url', '') ?: '') . ($_SERVER['REQUEST_URI'] ?? ''));
$shareTitle = urlencode($title ?? '');
$shareText = urlencode($meta_description ?? $title ?? '');
?>
<div class="share-buttons" aria-label="Share this page">
    <span class="share-label">Share:</span>
    <a href="https://www.facebook.com/sharer/sharer.php?u=<?= $shareUrl ?>"
       target="_blank" rel="noopener noreferrer"
       class="share-btn share-facebook" title="Share on Facebook">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
        <span>Facebook</span>
    </a>
    <a href="https://twitter.com/intent/tweet?url=<?= $shareUrl ?>&text=<?= $shareText ?>"
       target="_blank" rel="noopener noreferrer"
       class="share-btn share-twitter" title="Share on Twitter / X">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
        <span>Twitter</span>
    </a>
    <a href="mailto:?subject=<?= $shareTitle ?>&body=<?= $shareText ?>%0A%0A<?= $shareUrl ?>"
       class="share-btn share-email" title="Share via Email">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
        <span>Email</span>
    </a>
    <button type="button" class="share-btn share-copy" title="Copy link" onclick="copyShareLink(this)">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></svg>
        <span>Copy Link</span>
    </button>
</div>
<script>
function copyShareLink(btn) {
    navigator.clipboard.writeText(window.location.href).then(function() {
        var span = btn.querySelector('span');
        var orig = span.textContent;
        span.textContent = 'Copied!';
        setTimeout(function() { span.textContent = orig; }, 2000);
    });
}
</script>
