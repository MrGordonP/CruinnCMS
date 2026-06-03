<?php

use Cruinn\BlockTypes\BlockRegistry;

BlockRegistry::register([
    'slug'      => 'gallery',
    'label'     => 'Gallery',
    'tag'       => 'div',
    'dynamic'   => false,
    'container' => false,
    'isLayout'  => false,
    'group'          => 'media',
    'group_label'    => 'Media',
    'palette_entries'=> [['label' => 'Gallery']],
]);
