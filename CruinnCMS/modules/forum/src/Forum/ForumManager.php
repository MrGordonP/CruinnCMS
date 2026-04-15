<?php

namespace Cruinn\Module\Forum\Forum;

use Cruinn\App;

class ForumManager
{
    public static function provider(): ForumProviderInterface
    {
        static $provider = null;

        if ($provider instanceof ForumProviderInterface) {
            return $provider;
        }

        $selected = (string)App::config('forum.provider', 'native');

        if ($selected === 'native') {
            $provider = new NativeForumProvider();
            return $provider;
        }

        // Fallback until external providers are implemented.
        $provider = new NativeForumProvider();
        return $provider;
    }
}
