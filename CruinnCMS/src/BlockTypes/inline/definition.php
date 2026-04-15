<?php

use Cruinn\BlockTypes\BlockRegistry;

BlockRegistry::register([
    'slug'      => 'inline',
    'label'     => 'Inline',
    'tag'       => 'span',
    'dynamic'   => false,
    'container' => false,
    'isLayout'  => false,
]);
