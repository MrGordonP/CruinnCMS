<?php

use Cruinn\BlockTypes\BlockRegistry;

BlockRegistry::register([
    'slug'      => 'heading',
    'label'     => 'Heading',
    'tag'       => 'h2',
    'dynamic'   => false,
    'container' => false,
    'isLayout'  => false,
    'group'          => 'text',
    'group_label'    => 'Text',
    'palette_entries'=> [['label' => 'Heading']],
]);
