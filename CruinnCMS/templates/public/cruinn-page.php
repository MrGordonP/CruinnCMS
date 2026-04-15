<?php
/**
 * Cruinn CMS — Public Page Template
 *
 * Rendered by PageController when a page has published Cruinn blocks.
 * CSS is injected by layout.php via the cruinn_css global.
 * Falls through to the existing content_blocks renderer if no Cruinn blocks.
 */
?>
<div class="cruinn-page">
    <div class="container">
        <?= $content ?>
    </div>
</div>
