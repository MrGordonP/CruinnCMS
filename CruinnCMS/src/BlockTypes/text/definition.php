<?php

use Cruinn\BlockTypes\BlockRegistry;

BlockRegistry::register([
    'slug'      => 'text',
    'label'     => 'Text',
    'tag'       => 'div',
    'dynamic'   => false,
    'container' => false,
    'isLayout'  => false,
    'group'          => 'text',
    'group_label'    => 'Text',
    'palette_entries'=> [['label' => 'Text']],
]);
