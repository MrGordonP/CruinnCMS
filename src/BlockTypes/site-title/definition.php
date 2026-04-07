<?php

use Cruinn\BlockTypes\BlockRegistry;

BlockRegistry::register([
    'slug'       => 'site-title',
    'label'      => 'Site Title',
    'tag'        => 'div',
    'dynamic'    => false,
    'container'  => false,
    'isLayout'   => false,
    'hasContent' => true,   // shows Content group in block-editor properties panel
]);
