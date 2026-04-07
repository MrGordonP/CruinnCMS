<?php

use Cruinn\BlockTypes\BlockRegistry;

BlockRegistry::register([
    'slug'      => 'list-item',
    'label'     => 'List Item',
    'tag'       => 'li',
    'dynamic'   => false,
    'container' => true,
    'isLayout'  => false,
]);
