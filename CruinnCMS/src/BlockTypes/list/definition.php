<?php

use Cruinn\BlockTypes\BlockRegistry;

BlockRegistry::register([
    'slug'      => 'list',
    'label'     => 'List',
    'tag'       => 'ul',
    'dynamic'   => false,
    'container' => true,
    'isLayout'  => true,
]);
