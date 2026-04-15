<?php
/**
 * HTML Page Template
 *
 * Rendered by PageController when render_mode = 'html'.
 * $body_html contains raw HTML authored in the ACP code editor.
 * Wrapped in the site layout (nav, header, footer) but NOT passed
 * through Cruinn's block renderer.
 */
?>
<div class="html-page container">
    <?= $body_html /* already trusted — stored by admin only */ ?>
</div>
