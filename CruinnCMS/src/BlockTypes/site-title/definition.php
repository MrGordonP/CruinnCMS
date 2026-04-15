<?php

use Cruinn\BlockTypes\BlockRegistry;
use Cruinn\App;
use Cruinn\Database;

BlockRegistry::register([
    'slug'       => 'site-title',
    'label'      => 'Site Title',
    'tag'        => 'a',
    'dynamic'    => true,
    'container'  => false,
    'isLayout'   => false,
    'hasContent' => false,
    'renderer'   => function (array $config, Database $db): string {
        $name    = App::config('site.name', 'Portal');
        $tagline = App::config('site.tagline', '');
        $url     = url('/');
        $html  = '<span class="logo-text">' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</span>';
        if ($tagline !== '') {
            $html .= '<span class="logo-tagline">' . htmlspecialchars($tagline, ENT_QUOTES, 'UTF-8') . '</span>';
        }
        return $html;
    },
]);
