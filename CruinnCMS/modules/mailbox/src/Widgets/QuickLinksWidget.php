<?php

declare(strict_types=1);

namespace Cruinn\Module\Mailbox\Widgets;

class QuickLinksWidget
{
    public static function getData(array $settings, array $userContext): array
    {
        return [
            'links' => [
                ['label' => 'Open Mailboxes', 'url' => '/mail', 'icon' => '📬'],
                ['label' => 'Mailbox Settings', 'url' => '/admin/mailbox', 'icon' => '⚙️'],
                ['label' => 'Tags', 'url' => '/admin/mailbox/tags', 'icon' => '🏷️'],
            ],
        ];
    }
}
