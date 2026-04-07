<?php

use Cruinn\BlockTypes\BlockRegistry;

BlockRegistry::register([
    'slug'      => 'columns',
    'label'     => 'Columns',
    'tag'       => 'div',
    'dynamic'   => false,
    'container' => true,
    'isLayout'  => true,
]);
