<?php
/**
 * Block Renderer Component — Phase 16 clean rebuild
 *
 * Renders a single content block. Every CSS property is set explicitly via
 * inline style derived from the block's settings JSON. No magic translation
 * layer — what you set is what you get.
 *
 * Usage: set $block to the block array (with decoded content, settings, children)
 * and include this file. For child blocks the file recurses via include __FILE__.
 */

if (!function_exists('blockStyle')) {
    function blockStyle(array $s): string
    {
        $css = '';

        // Size
        if (isset($s['width'])) {
            $unit = $s['widthUnit'] ?? 'px';
            $css .= 'width:' . ($unit === 'auto' ? 'auto' : (float)$s['width'] . e($unit)) . ';';
        }
        if (isset($s['height'])) {
            $css .= 'height:' . (float)$s['height'] . e($s['heightUnit'] ?? 'px') . ';';
        }
        if (isset($s['maxWidth'])) {
            $css .= 'max-width:' . (float)$s['maxWidth'] . e($s['maxWidthUnit'] ?? 'px') . ';';
        }
        if (isset($s['minWidth'])) {
            $css .= 'min-width:' . (float)$s['minWidth'] . 'px;';
        }
        if (isset($s['minHeight'])) {
            $css .= 'min-height:' . (float)$s['minHeight'] . 'px;';
        }

        // Spacing
        $mu = e($s['marginUnit'] ?? 'px');
        foreach (['mt' => 'margin-top', 'mr' => 'margin-right', 'mb' => 'margin-bottom', 'ml' => 'margin-left'] as $k => $p) {
            if (isset($s[$k]) && $s[$k] !== '') {
                $css .= $p . ':' . ($s[$k] === 'auto' ? 'auto' : (float)$s[$k] . $mu) . ';';
            }
        }
        $pu = e($s['paddingUnit'] ?? 'px');
        foreach (['pt' => 'padding-top', 'pr' => 'padding-right', 'pb' => 'padding-bottom', 'pl' => 'padding-left'] as $k => $p) {
            if (isset($s[$k]) && $s[$k] !== '') {
                $css .= $p . ':' . (float)$s[$k] . $pu . ';';
            }
        }

        // Typography
        if (!empty($s['textColor']))  $css .= 'color:' . e($s['textColor']) . ';';
        if (!empty($s['fontSize']))   $css .= 'font-size:' . (float)$s['fontSize'] . e($s['fontSizeUnit'] ?? 'px') . ';';
        if (!empty($s['fontWeight'])) $css .= 'font-weight:' . e($s['fontWeight']) . ';';
        if (!empty($s['textAlign']))  $css .= 'text-align:' . e($s['textAlign']) . ';';
        if (!empty($s['lineHeight'])) $css .= 'line-height:' . e($s['lineHeight']) . ';';

        // Background
        if (!empty($s['bgColor'])) $css .= 'background-color:' . e($s['bgColor']) . ';';
        if (!empty($s['bgImage'])) {
            $css .= 'background-image:url(' . e($s['bgImage']) . ');';
            $css .= 'background-size:'     . e($s['bgSize']   ?? 'cover') . ';';
            $css .= 'background-position:' . e($s['bgPos']    ?? 'center center') . ';';
            $css .= 'background-repeat:'   . e($s['bgRepeat'] ?? 'no-repeat') . ';';
        }

        // Border
        if (!empty($s['borderWidth']) && (float)$s['borderWidth'] > 0) {
            $css .= 'border:' . (float)$s['borderWidth'] . 'px ' . e($s['borderStyle'] ?? 'solid') . ' ' . e($s['borderColor'] ?? '#000000') . ';';
        }
        if (isset($s['borderRadius']) && $s['borderRadius'] !== '') {
            $css .= 'border-radius:' . (float)$s['borderRadius'] . e($s['borderRadiusUnit'] ?? 'px') . ';';
        }

        // Layout (section / columns containers)
        if (!empty($s['display'])) {
            $css .= 'display:' . e($s['display']) . ';';
            if ($s['display'] === 'grid') {
                if (!empty($s['gridCols'])) $css .= 'grid-template-columns:' . e($s['gridCols']) . ';';
                if (!empty($s['gridRows'])) $css .= 'grid-template-rows:' . e($s['gridRows']) . ';';
                if (!empty($s['gridGap']))  $css .= 'gap:' . e($s['gridGap']) . ';';
            } elseif ($s['display'] === 'flex') {
                if (!empty($s['flexDir']))  $css .= 'flex-direction:' . e($s['flexDir']) . ';';
                if (!empty($s['flexWrap'])) $css .= 'flex-wrap:' . e($s['flexWrap']) . ';';
                if (!empty($s['flexGap']))  $css .= 'gap:' . e($s['flexGap']) . ';';
            }
            if (!empty($s['alignItems']))     $css .= 'align-items:' . e($s['alignItems']) . ';';
            if (!empty($s['justifyContent'])) $css .= 'justify-content:' . e($s['justifyContent']) . ';';
        }

        return $css;
    }
}

// ── Render this block ─────────────────────────────────────────

$type     = $block['block_type'] ?? 'text';
$content  = $block['content']    ?? [];
$settings = $block['settings']   ?? [];
$children = $block['children']   ?? [];

$style   = blockStyle($settings);
$classes = 'content-block block-' . e($type);
if (!empty($settings['cssClass'])) {
    $classes .= ' ' . e($settings['cssClass']);
}

$idAttr = '';
if (!empty($settings['cssId'])) {
    $idAttr = ' id="' . e($settings['cssId']) . '"';
}

echo '<div' . $idAttr . ' class="' . $classes . '"' . ($style ? ' style="' . $style . '"' : '') . '>';

switch ($type) {

    case 'section':
    case 'columns':
        foreach ($children as $_child) {
            $block = $_child;
            include __FILE__;
        }
        break;

    case 'text':
        echo '<div class="block-text">' . sanitise_html($content['html'] ?? '') . '</div>';
        break;

    case 'heading':
        $tag = 'h' . max(1, min(6, (int)($content['level'] ?? 2)));
        echo '<' . $tag . '>' . e($content['text'] ?? '') . '</' . $tag . '>';
        break;

    case 'image':
        if (!empty($content['src'])) {
            echo '<img src="' . e($content['src']) . '" alt="' . e($content['alt'] ?? '') . '" loading="lazy">';
        }
        break;

    case 'gallery':
        if (!empty($content['images'])) {
            echo '<div class="block-gallery">';
            foreach ($content['images'] as $img) {
                echo '<figure class="gallery-item">';
                echo '<a href="' . e($img['url'] ?? '') . '" class="gallery-link">';
                echo '<img src="' . e($img['url'] ?? '') . '" alt="' . e($img['alt'] ?? '') . '" loading="lazy">';
                echo '</a>';
                if (!empty($img['caption'])) {
                    echo '<figcaption>' . e($img['caption']) . '</figcaption>';
                }
                echo '</figure>';
            }
            echo '</div>';
        }
        break;

    case 'html':
        echo '<div class="block-html">' . sanitise_html($content['raw'] ?? '') . '</div>';
        break;

    case 'site-logo':
        $logo    = !empty($content['src']) ? $content['src'] : \Cruinn\App::config('site.logo', '');
        $logoAlt = !empty($content['alt']) ? $content['alt'] : \Cruinn\App::config('site.name', 'Home');
        $logoHref = !empty($content['linkUrl']) ? $content['linkUrl'] : '/';
        if ($logo) {
            echo '<a href="' . e($logoHref) . '" class="site-logo">';
            echo '<img src="' . e($logo) . '" alt="' . e($logoAlt) . '" loading="eager">';
            echo '</a>';
        }
        break;

    case 'site-title':
        $siteName  = e(\Cruinn\App::config('site.name', 'Portal'));
        $tagline   = !empty($content['tagline']) ? $content['tagline'] : '';
        $taglineTag = $content['taglineTag'] ?? 'p';
        $allowedTags = ['p', 'span', 'h2', 'h3'];
        if (!in_array($taglineTag, $allowedTags, true)) $taglineTag = 'p';
        echo '<a href="/" class="site-title-link"><span class="site-title">' . $siteName . '</span></a>';
        if ($tagline !== '') {
            echo '<' . $taglineTag . ' class="site-tagline">' . htmlspecialchars($tagline, ENT_QUOTES, 'UTF-8') . '</' . $taglineTag . '>';
        }
        break;

    case 'nav-menu':
        $menuId = (int)($content['menu_id'] ?? 0);
        if ($menuId) {
            $db = \Cruinn\Database::getInstance();
            $menuRows = $db->fetchAll(
                'SELECT mi.*, p.slug AS page_slug
                 FROM menu_items mi
                 LEFT JOIN pages_index p ON mi.page_id = p.id
                 WHERE mi.menu_id = ? AND mi.is_active = 1 AND (mi.parent_id IS NULL OR mi.parent_id = 0)
                 ORDER BY mi.sort_order ASC',
                [$menuId]
            );
            if ($menuRows) {
                echo '<nav class="block-nav-menu" aria-label="Navigation"><ul class="nav-list">';
                foreach ($menuRows as $mi) {
                    $href = match ($mi['link_type'] ?? 'url') {
                        'page'  => '/' . ($mi['page_slug'] ?? ''),
                        'route' => $mi['route'] ?? '/',
                        default => $mi['url'] ?? '#',
                    };
                    echo '<li><a href="' . e($href) . '">' . e($mi['label']) . '</a></li>';
                }
                echo '</ul></nav>';
            }
        }
        break;

    case 'event-list':
        $eventCount = max(1, (int)($content['count'] ?? 5));
        $eventDb    = \Cruinn\Database::getInstance();
        $events     = $eventDb->fetchAll(
            'SELECT * FROM events WHERE date_start >= NOW() AND status = ?
             ORDER BY date_start ASC LIMIT ?',
            ['published', $eventCount]
        );
        echo '<div class="block-events">';
        if (empty($events)) {
            echo '<p>No upcoming events scheduled.</p>';
        } else {
            echo '<ul class="event-list">';
            foreach ($events as $evt) {
                echo '<li class="event-item">';
                echo '<time datetime="' . e($evt['date_start']) . '">' . format_date($evt['date_start']) . '</time> ';
                echo '<a href="' . url('/events/' . $evt['slug']) . '">' . e($evt['title']) . '</a>';
                if (!empty($evt['location'])) {
                    echo ' <span class="event-location">' . e($evt['location']) . '</span>';
                }
                echo '</li>';
            }
            echo '</ul>';
        }
        echo '<p><a href="' . url('/events') . '" class="btn btn-small">View all events</a></p>';
        echo '</div>';
        break;

    case 'map':
        $lat     = (float)($content['lat'] ?? 53.3498);
        $lng     = (float)($content['lng'] ?? -6.2603);
        $caption = $content['caption'] ?? '';
        printf(
            '<div class="block-map"><iframe src="https://www.openstreetmap.org/export/embed.html?bbox=%s,%s,%s,%s&amp;layer=mapnik&amp;marker=%s,%s"'
            . ' width="100%%" height="400" style="border:0" loading="lazy" title="%s"></iframe>',
            e((string)($lng - 0.05)), e((string)($lat - 0.03)),
            e((string)($lng + 0.05)), e((string)($lat + 0.03)),
            e((string)$lat), e((string)$lng),
            e($caption ?: 'Location map')
        );
        if ($caption) echo '<p class="map-caption">' . e($caption) . '</p>';
        echo '</div>';
        break;

    default:
        // Unknown type — silent on the public side
        break;
}

echo '</div>';
