<?php

use Cruinn\BlockTypes\BlockRegistry;

BlockRegistry::register([
    'slug'      => 'heading',
    'label'     => 'Heading',
    'tag'       => 'h2',
    'dynamic'   => false,
    'container' => false,
    'isLayout'  => false,
]);
