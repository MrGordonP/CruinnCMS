<?php

use Cruinn\BlockTypes\BlockRegistry;

BlockRegistry::register([
    'slug'       => 'site-logo',
    'label'      => 'Site Logo',
    'tag'        => 'div',
    'dynamic'    => false,
    'container'  => false,
    'isLayout'   => false,
    'hasContent' => true,   // shows Content group in block-editor properties panel
]);
