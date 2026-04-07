<?php

use Cruinn\BlockTypes\BlockRegistry;

BlockRegistry::register([
    'slug'      => 'image',
    'label'     => 'Image',
    'tag'       => 'figure',
    'dynamic'   => false,
    'container' => false,
    'isLayout'  => false,
]);
