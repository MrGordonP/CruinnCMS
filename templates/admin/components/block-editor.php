<?php
/**
 * Block Editor Component — Phase 16 rebuild
 *
 * Expected variables:
 *   $editorParentType — 'page', 'article', or 'template'
 *   $editorParentId   — numeric ID
 *   $editorMode       — 'structured' (freeform removed)
 *   $blocks           — flat array of block rows (JSON-decoded content/settings)
 *   $editorEmbedded   — (optional) true when inside site editor
 *   $editorZones      — (optional) array of zone names ['header','body','footer']
 *   $editorShowZones  — (optional) assoc array of which zones are visible
 */
\Cruinn\Template::requireCss('admin-block-editor.css');


// ── blockStyle (duplicated here to avoid including the public block renderer) ──

if (!function_exists('blockStyle')) {
    function blockStyle(array $s): string
    {
        $css = '';
        if (isset($s['width'])) {
            $unit = $s['widthUnit'] ?? 'px';
            $css .= 'width:' . ($unit === 'auto' ? 'auto' : (float)$s['width'] . e($unit)) . ';';
        }
        if (isset($s['height']))   $css .= 'height:'    . (float)$s['height']   . e($s['heightUnit']   ?? 'px') . ';';
        if (isset($s['maxWidth'])) $css .= 'max-width:' . (float)$s['maxWidth'] . e($s['maxWidthUnit'] ?? 'px') . ';';
        if (isset($s['minWidth'])) $css .= 'min-width:' . (float)$s['minWidth'] . 'px;';
        if (isset($s['minHeight']))$css .= 'min-height:'. (float)$s['minHeight']. 'px;';
        $mu = e($s['marginUnit'] ?? 'px');
        foreach (['mt'=>'margin-top','mr'=>'margin-right','mb'=>'margin-bottom','ml'=>'margin-left'] as $k => $p) {
            if (isset($s[$k]) && $s[$k] !== '') $css .= $p.':'.($s[$k]==='auto'?'auto':(float)$s[$k].$mu).';';
        }
        $pu = e($s['paddingUnit'] ?? 'px');
        foreach (['pt'=>'padding-top','pr'=>'padding-right','pb'=>'padding-bottom','pl'=>'padding-left'] as $k => $p) {
            if (isset($s[$k]) && $s[$k] !== '') $css .= $p.':'.(float)$s[$k].$pu.';';
        }
        if (!empty($s['textColor']))  $css .= 'color:'       . e($s['textColor'])  . ';';
        if (!empty($s['fontSize']))   $css .= 'font-size:'   . (float)$s['fontSize']   . e($s['fontSizeUnit'] ?? 'px') . ';';
        if (!empty($s['fontWeight'])) $css .= 'font-weight:' . e($s['fontWeight']) . ';';
        if (!empty($s['textAlign']))  $css .= 'text-align:'  . e($s['textAlign'])  . ';';
        if (!empty($s['lineHeight'])) $css .= 'line-height:' . e($s['lineHeight']) . ';';
        if (!empty($s['bgColor']))    $css .= 'background-color:' . e($s['bgColor']) . ';';
        if (!empty($s['bgImage'])) {
            $css .= 'background-image:url(' . e($s['bgImage']) . ');';
            $css .= 'background-size:'      . e($s['bgSize']   ?? 'cover')          . ';';
            $css .= 'background-position:'  . e($s['bgPos']    ?? 'center center')  . ';';
            $css .= 'background-repeat:'    . e($s['bgRepeat'] ?? 'no-repeat')      . ';';
        }
        if (!empty($s['borderWidth']) && (float)$s['borderWidth'] > 0) {
            $css .= 'border:' . (float)$s['borderWidth'] . 'px ' . e($s['borderStyle'] ?? 'solid') . ' ' . e($s['borderColor'] ?? '#000000') . ';';
        }
        if (isset($s['borderRadius']) && $s['borderRadius'] !== '') {
            $css .= 'border-radius:' . (float)$s['borderRadius'] . e($s['borderRadiusUnit'] ?? 'px') . ';';
        }
        if (!empty($s['display'])) {
            $css .= 'display:' . e($s['display']) . ';';
            if ($s['display'] === 'grid') {
                if (!empty($s['gridCols'])) $css .= 'grid-template-columns:' . e($s['gridCols']) . ';';
                if (!empty($s['gridRows'])) $css .= 'grid-template-rows:'    . e($s['gridRows']) . ';';
                if (!empty($s['gridGap']))  $css .= 'gap:'                   . e($s['gridGap'])  . ';';
            } elseif ($s['display'] === 'flex') {
                if (!empty($s['flexDir']))  $css .= 'flex-direction:' . e($s['flexDir'])  . ';';
                if (!empty($s['flexWrap'])) $css .= 'flex-wrap:'      . e($s['flexWrap']) . ';';
                if (!empty($s['flexGap']))  $css .= 'gap:'            . e($s['flexGap'])  . ';';
            }
            if (!empty($s['alignItems']))     $css .= 'align-items:'     . e($s['alignItems'])     . ';';
            if (!empty($s['justifyContent'])) $css .= 'justify-content:' . e($s['justifyContent']) . ';';
        }
        return $css;
    }
}


// ── Build block tree ──────────────────────────────────────────

function buildBlockTree(array $flat, $parentBlockId = null): array
{
    $tree = [];
    foreach ($flat as $b) {
        $pid    = isset($b['parent_block_id']) ? (int)$b['parent_block_id'] : 0;
        $target = $parentBlockId === null ? 0 : (int)$parentBlockId;
        if ($pid === $target) {
            $b['children'] = buildBlockTree($flat, $b['id']);
            $tree[] = $b;
        }
    }
    usort($tree, fn($a, $b) => ($a['sort_order'] ?? 0) - ($b['sort_order'] ?? 0));
    return $tree;
}

// ── RTE toolbar ───────────────────────────────────────────────

function renderRteToolbar(): string
{
    $h  = '<div class="rte-toolbar">';
    $h .= '<button type="button" class="rte-btn" data-cmd="bold" title="Bold"><b>B</b></button>';
    $h .= '<button type="button" class="rte-btn" data-cmd="italic" title="Italic"><i>I</i></button>';
    $h .= '<button type="button" class="rte-btn" data-cmd="underline" title="Underline"><u>U</u></button>';
    $h .= '<button type="button" class="rte-btn" data-cmd="strikethrough" title="Strikethrough"><s>S</s></button>';
    $h .= '<span class="rte-sep"></span>';
    $h .= '<select class="rte-select rte-block-format" title="Format">';
    foreach (['p' => 'Paragraph', 'h2' => 'Heading 2', 'h3' => 'Heading 3', 'h4' => 'Heading 4', 'blockquote' => 'Blockquote', 'pre' => 'Code'] as $v => $l) {
        $h .= '<option value="' . $v . '">' . $l . '</option>';
    }
    $h .= '</select>';
    $h .= '<span class="rte-sep"></span>';
    $h .= '<button type="button" class="rte-btn" data-cmd="insertUnorderedList" title="Bullet list">&#8226;</button>';
    $h .= '<button type="button" class="rte-btn" data-cmd="insertOrderedList" title="Numbered list">1.</button>';
    $h .= '<span class="rte-sep"></span>';
    $h .= '<button type="button" class="rte-btn rte-btn-link" data-cmd="createLink" title="Insert link">&#128279;</button>';
    $h .= '<button type="button" class="rte-btn" data-cmd="unlink" title="Remove link">&#10060;</button>';
    $h .= '<button type="button" class="rte-btn rte-btn-image" title="Insert image">&#128247;</button>';
    $h .= '<span class="rte-sep"></span>';
    $h .= '<button type="button" class="rte-btn" data-cmd="removeFormat" title="Clear formatting">&#8709;</button>';
    $h .= '<button type="button" class="rte-btn rte-btn-source" title="View source">&lt;/&gt;</button>';
    $h .= '</div>';
    return $h;
}

// ── Editor block renderer (inline-edit mode) ──────────────────

function renderEditorBlock(array $block, string $parentType, int $parentId, int $depth = 0, bool $zoned = false): void
{
    $content  = $block['content'] ?? [];
    $settings = $block['settings'] ?? [];
    $children = $block['children'] ?? [];
    $blockId  = (int)$block['id'];
    $type     = $block['block_type'];

    $settingsJson = e(json_encode($settings));
    $depthClass   = $depth > 0 ? ' block-nested block-depth-' . $depth : '';
    $blockName    = !empty($settings['blockName']) ? $settings['blockName'] : '';
    $blockSource  = $settings['source'] ?? '';   // 'imported' | 'native' | ''
    ?>
    <div class="block-editor-item<?= $depthClass ?>"
         data-block-id="<?= $blockId ?>"
         data-block-type="<?= e($type) ?>"
         data-settings="<?= $settingsJson ?>"
         <?= $blockSource ? 'data-source="' . e($blockSource) . '"' : '' ?>>

        <span class="block-handle-move" title="Drag to reorder">&#x2630;</span>
        <span class="block-info-bar">
            <?= e(ucfirst($type)) ?>
            <span class="block-info-id">#<?= $blockId ?></span>
            <?php if ($blockName): ?>
                <span class="block-info-name"><?= e($blockName) ?></span>
            <?php endif; ?>
        </span>

        <div class="block-editor-content">
            <?php
            switch ($type) {

                case 'section':
                    echo '<div class="block-section-editor">';
                    foreach ($children as $child) {
                        renderEditorBlock($child, $parentType, $parentId, $depth + 1, $zoned);
                    }
                    if (empty($children)) {
                        echo '<p class="block-empty-hint">Empty section — add child blocks below.</p>';
                    }
                    echo '<button type="button" class="btn btn-small btn-add-child"'
                        . ' data-parent-block="' . $blockId . '" data-column="0">+ Add block</button>';
                    echo '</div>';
                    break;

                case 'columns':
                    $gridCols = $settings['gridCols'] ?? '1fr 1fr';
                    $colParts = preg_split('/\s+/', trim($gridCols), -1, PREG_SPLIT_NO_EMPTY);
                    $colCount = max(1, count($colParts));
                    $childrenByCol = [];
                    foreach ($children as $child) {
                        // Clamp column index to valid range so orphaned blocks
                        // always appear in the last column rather than outside the grid.
                        $col = max(0, min((int)($child['settings']['column'] ?? 0), $colCount - 1));
                        $childrenByCol[$col][] = $child;
                    }
                    echo '<div class="block-section-editor block-columns-editor"'
                        . ' style="display:grid;grid-template-columns:' . e($gridCols) . ';gap:8px">';
                    for ($ci = 0; $ci < $colCount; $ci++) {
                        echo '<div class="block-col-slot">';
                        $colChildren = $childrenByCol[$ci] ?? [];
                        if (empty($colChildren)) {
                            echo '<p class="block-empty-hint">Col ' . ($ci + 1) . ' — empty</p>';
                        } else {
                            foreach ($colChildren as $child) {
                                renderEditorBlock($child, $parentType, $parentId, $depth + 1, $zoned);
                            }
                        }
                        echo '<button type="button" class="btn btn-small btn-add-child"'
                            . ' data-parent-block="' . $blockId . '" data-column="' . $ci . '">+ Add to col ' . ($ci + 1) . '</button>';
                        echo '</div>';
                    }
                    echo '</div>';
                    break;

                case 'text':
                    echo '<div class="rte-wrap">';
                    echo renderRteToolbar();
                    echo '<div class="rte-editor" contenteditable="true">' . ($content['html'] ?? '<p>Enter your content here.</p>') . '</div>';
                    echo '<textarea class="block-html-input rte-source-textarea" name="content_html" style="display:none">' . e($content['html'] ?? '') . '</textarea>';
                    echo '</div>';
                    break;

                case 'heading':
                    echo '<select name="heading_level" class="block-level-input" style="width:4em">';
                    for ($lvl = 1; $lvl <= 6; $lvl++) {
                        $sel = ((int)($content['level'] ?? 2) === $lvl) ? ' selected' : '';
                        echo '<option value="' . $lvl . '"' . $sel . '>H' . $lvl . '</option>';
                    }
                    echo '</select> ';
                    echo '<input type="text" class="block-text-input" name="heading_text" value="' . e($content['text'] ?? '') . '" placeholder="Heading text">';
                    break;

                case 'image':
                    echo '<div class="block-image-fields">';
                    echo '<div class="block-image-url-row">';
                    echo '<input type="text" class="block-url-input" name="content_src" value="' . e($content['src'] ?? '') . '" placeholder="Image URL">';
                    echo '<button type="button" class="btn btn-small btn-browse-media">Browse&hellip;</button>';
                    echo '<label class="btn btn-small btn-upload-inline">Upload <input type="file" class="block-file-upload" accept="image/*" hidden></label>';
                    echo '</div>';
                    echo '<input type="text" class="block-alt-input" name="content_alt" value="' . e($content['alt'] ?? '') . '" placeholder="Alt text">';
                    if (!empty($content['src'])) {
                        echo '<img src="' . e($content['src']) . '" alt="" class="block-image-preview">';
                    }
                    echo '</div>';
                    break;

                case 'gallery':
                    echo '<div class="block-gallery-editor" data-images="' . e(json_encode($content['images'] ?? [])) . '">';
                    echo '<div class="gallery-thumbs"></div>';
                    echo '<div class="gallery-add-row">';
                    echo '<button type="button" class="btn btn-small btn-browse-media">Browse&hellip;</button>';
                    echo '<label class="btn btn-small btn-upload-inline">Upload <input type="file" class="block-file-upload" accept="image/*" multiple hidden></label>';
                    echo '</div>';
                    echo '</div>';
                    break;

                case 'html':
                    echo '<textarea class="block-raw-input" name="content_raw" rows="5">' . e($content['raw'] ?? '') . '</textarea>';
                    break;

                case 'site-logo':
                    $cfgLogo    = \Cruinn\App::config('site.logo', '');
                    $logoSrc    = $content['src'] ?? '';
                    $logoAlt    = $content['alt'] ?? '';
                    $logoLink   = $content['linkUrl'] ?? '/';
                    $displaySrc = $logoSrc ?: $cfgLogo;
                    echo '<div class="block-image-fields block-logo-fields">';
                    echo '<p class="block-info-hint" style="margin:0 0 6px">Logo source: override below, or leave blank to use the site logo from Settings &rsaquo; Site.</p>';
                    echo '<div class="block-image-url-row">';
                    echo '<input type="text" class="block-url-input" name="content_src" value="' . e($logoSrc) . '" placeholder="' . e($cfgLogo ?: '/uploads/logo.png') . '">';
                    echo '<button type="button" class="btn btn-small btn-browse-media">Browse&hellip;</button>';
                    echo '<label class="btn btn-small btn-upload-inline">Upload <input type="file" class="block-file-upload" accept="image/*" hidden></label>';
                    echo '</div>';
                    echo '<input type="text" class="block-alt-input" name="content_alt" value="' . e($logoAlt) . '" placeholder="Alt text (defaults to site name)" style="margin-top:4px">';
                    echo '<input type="text" class="prop-input" name="content_link_url" value="' . e($logoLink) . '" placeholder="Link URL (default: /)" style="margin-top:4px">';
                    if ($displaySrc) {
                        echo '<img src="' . e($displaySrc) . '" alt="" class="block-image-preview" style="max-height:48px;margin-top:6px">';
                    }
                    echo '</div>';
                    break;

                case 'site-title':
                    $siteName  = \Cruinn\App::config('site.name', 'Portal');
                    $tagline   = $content['tagline'] ?? '';
                    $tagSel    = $content['taglineTag'] ?? 'p';
                    $allowedTT = ['p', 'span', 'h2', 'h3'];
                    if (!in_array($tagSel, $allowedTT)) $tagSel = 'p';
                    echo '<div class="block-site-title-editor">';
                    echo '<p class="block-info-hint" style="margin:0 0 6px">Site name: <strong>' . e($siteName) . '</strong> (configured in Settings &rsaquo; Site)</p>';
                    echo '<div class="prop-row" style="display:flex;align-items:center;gap:6px;margin-bottom:4px">';
                    echo '<label style="white-space:nowrap;font-size:12px;width:60px">Tagline</label>';
                    echo '<input type="text" class="prop-input" name="content_tagline" value="' . e($tagline) . '" placeholder="Sub-heading / tagline..." style="flex:1">';
                    echo '</div>';
                    echo '<div class="prop-row" style="display:flex;align-items:center;gap:6px">';
                    echo '<label style="white-space:nowrap;font-size:12px;width:60px">Tag</label>';
                    echo '<select name="content_tagline_tag" class="prop-input" style="width:5em">';
                    foreach (['p' => 'p', 'span' => 'span', 'h2' => 'h2', 'h3' => 'h3'] as $v => $l) {
                        $sel = ($tagSel === $v) ? ' selected' : '';
                        echo '<option value="' . $v . '"' . $sel . '>' . $l . '</option>';
                    }
                    echo '</select>';
                    echo '</div>';
                    echo '</div>';
                    break;

                case 'nav-menu':
                    // Load available menus for the selector
                    $db    = \Cruinn\Database::getInstance();
                    $menus = $db->fetchAll('SELECT id, name, location FROM menus ORDER BY name');
                    $selMenuId = (int)($content['menu_id'] ?? 0);
                    echo '<div class="block-nav-menu-editor">';
                    echo '<label>Menu ';
                    echo '<select name="menu_id" class="block-menu-id">';
                    echo '<option value="0">(none)</option>';
                    foreach ($menus as $m) {
                        $sel = ($m['id'] == $selMenuId) ? ' selected' : '';
                        echo '<option value="' . (int)$m['id'] . '"' . $sel . '>' . e($m['name']) . ' (' . e($m['location']) . ')</option>';
                    }
                    echo '</select></label>';
                    echo '</div>';
                    break;

                case 'event-list':
                    echo '<label>Show <input type="number" name="content_count" value="' . (int)($content['count'] ?? 5) . '" min="1" max="50" style="width:4em"> upcoming events</label>';
                    break;

                case 'map':
                    echo '<div class="block-map-fields">';
                    echo '<label>Lat <input type="text" class="block-lat-input" name="content_lat" value="' . e((string)($content['lat'] ?? '53.3498')) . '" style="width:8em"></label> ';
                    echo '<label>Lng <input type="text" class="block-lng-input" name="content_lng" value="' . e((string)($content['lng'] ?? '-6.2603')) . '" style="width:8em"></label>';
                    echo '<input type="text" class="block-caption-input" name="content_caption" value="' . e($content['caption'] ?? '') . '" placeholder="Caption (optional)" style="margin-top:4px;width:100%">';
                    echo '</div>';
                    break;

                default:
                    echo '<code class="block-raw-debug">' . e(json_encode($content, JSON_PRETTY_PRINT)) . '</code>';
            }
            ?>
        </div><!-- .block-editor-content -->

        <div class="block-actions">
            <button type="button" class="btn btn-small btn-danger btn-delete-block"
                    data-block-id="<?= $blockId ?>" title="Delete block">&times;</button>
        </div>

    </div><!-- .block-editor-item -->
    <?php
}

// ── Preview block renderer (shows blocks as they appear publicly) ──

function renderPreviewBlock(array $block, string $parentType, int $parentId, int $depth = 0, string $zone = ''): void
{
    $content  = $block['content'] ?? [];
    $settings = $block['settings'] ?? [];
    $children = $block['children'] ?? [];
    $blockId  = (int)$block['id'];
    $type     = $block['block_type'];

    $style   = blockStyle($settings);
    $classes = 'content-block block-' . e($type);
    if (!empty($settings['cssClass'])) $classes .= ' ' . e($settings['cssClass']);
    $idAttr = !empty($settings['cssId']) ? ' id="' . e($settings['cssId']) . '"' : '';

    $contentJson  = e(json_encode($content));
    $settingsJson = e(json_encode($settings));
    ?>
    <div class="block-editor-item block-preview-wrap"
         data-block-id="<?= $blockId ?>"
         data-block-type="<?= e($type) ?>"
         data-settings="<?= $settingsJson ?>"
         data-content="<?= $contentJson ?>">
        <div class="block-preview-toolbar">
            <span class="block-preview-handle" title="Drag to reorder">&#x2630;</span>
            <span class="block-preview-label"><?= e(ucfirst($type)) ?> #<?= $blockId ?></span>
            <?php if (!empty($settings['blockName'])): ?>
                <span class="block-preview-name"><?= e($settings['blockName']) ?></span>
            <?php endif; ?>
        </div>
        <div class="<?= $classes ?>"<?= $idAttr ?><?= $style ? ' style="' . $style . '"' : '' ?>>
    <?php

    switch ($type) {

        case 'section':
            foreach ($children as $child) {
                renderPreviewBlock($child, $parentType, $parentId, $depth + 1, $zone);
            }
            if (empty($children)) {
                echo '<div class="block-preview-placeholder block-preview-placeholder-small">Section — empty</div>';
            }
            echo '<button type="button" class="btn btn-small btn-add-child btn-add-child-preview"'
                . ' data-parent-block="' . $blockId . '" data-column="0" data-zone="' . e($zone) . '">+ Add to section</button>';
            break;

        case 'columns':
            $gridCols = $settings['gridCols'] ?? '1fr 1fr';
            $colParts = preg_split('/\s+/', trim($gridCols), -1, PREG_SPLIT_NO_EMPTY);
            $colCount = max(1, count($colParts));
            // Render per-column slots whether or not children exist, so the grid
            // structure is always visible and users can always target a specific column.
            $childrenByCol = [];
            foreach ($children as $child) {
                // Clamp column index so orphaned blocks always land in the last column.
                $col = max(0, min((int)($child['settings']['column'] ?? 0), $colCount - 1));
                $childrenByCol[$col][] = $child;
            }
            for ($ci = 0; $ci < $colCount; $ci++) {
                echo '<div class="block-col-slot">';
                $colChildren = $childrenByCol[$ci] ?? [];
                if (empty($colChildren)) {
                    echo '<div class="block-col-ghost">';
                    echo '<span class="block-col-ghost-label">Col ' . ($ci + 1) . '</span>';
                    echo '<button type="button" class="btn btn-small btn-add-child btn-add-child-preview"'
                        . ' data-parent-block="' . $blockId . '" data-column="' . $ci . '" data-zone="' . e($zone) . '">+ Add</button>';
                    echo '</div>';
                } else {
                    foreach ($colChildren as $child) {
                        renderPreviewBlock($child, $parentType, $parentId, $depth + 1, $zone);
                    }
                    echo '<button type="button" class="btn btn-small btn-add-child btn-add-child-preview btn-add-to-col"'
                        . ' data-parent-block="' . $blockId . '" data-column="' . $ci . '" data-zone="' . e($zone) . '">+ Add to col ' . ($ci + 1) . '</button>';
                }
                echo '</div>';
            }
            break;

        case 'text':
            echo '<div class="block-text">' . sanitise_html($content['html'] ?? '<p>Empty text block.</p>') . '</div>';
            break;

        case 'heading':
            $lvl = (int)($content['level'] ?? 2);
            echo '<h' . $lvl . '>' . e($content['text'] ?? 'Heading') . '</h' . $lvl . '>';
            break;

        case 'image':
            if (!empty($content['src'])) {
                echo '<img src="' . e($content['src']) . '" alt="' . e($content['alt'] ?? '') . '" loading="lazy">';
            } else {
                echo '<div class="block-preview-placeholder">&#128248; Image — no source set</div>';
            }
            break;

        case 'gallery':
            $images = $content['images'] ?? [];
            if (empty($images)) {
                echo '<div class="block-preview-placeholder">&#128247; Gallery — no images added</div>';
            } else {
                echo '<div class="block-gallery">';
                foreach ($images as $img) {
                    echo '<figure class="gallery-item"><img src="' . e($img['url'] ?? '') . '" alt="' . e($img['alt'] ?? '') . '" loading="lazy">';
                    if (!empty($img['caption'])) echo '<figcaption>' . e($img['caption']) . '</figcaption>';
                    echo '</figure>';
                }
                echo '</div>';
            }
            break;

        case 'html':
            $raw = $content['raw'] ?? '';
            echo $raw ? '<div class="block-html">' . sanitise_html($raw) . '</div>'
                      : '<div class="block-preview-placeholder">&lt;/&gt; HTML — empty</div>';
            break;

        case 'site-logo':
            $logoSrc = !empty($content['src']) ? $content['src'] : \Cruinn\App::config('site.logo', '');
            $logoAlt = !empty($content['alt']) ? $content['alt'] : \Cruinn\App::config('site.name', 'Home');
            echo $logoSrc
                ? '<div class="block-site-logo"><img src="' . e($logoSrc) . '" alt="' . e($logoAlt) . '" style="max-height:48px"></div>'
                : '<div class="block-preview-placeholder">&#127760; Site Logo &mdash; not set</div>';
            break;

        case 'site-title':
            $pvName    = e(\Cruinn\App::config('site.name', 'Portal'));
            $pvTagline = !empty($content['tagline']) ? e($content['tagline']) : '';
            $pvTag     = in_array($content['taglineTag'] ?? '', ['p', 'span', 'h2', 'h3']) ? $content['taglineTag'] : 'p';
            echo '<div class="block-site-title"><span class="site-title">' . $pvName . '</span>';
            if ($pvTagline) {
                echo '<' . $pvTag . ' class="site-tagline">' . $pvTagline . '</' . $pvTag . '>';
            }
            echo '</div>';
            break;

        case 'nav-menu':
            $menuId = (int)($content['menu_id'] ?? 0);
            if ($menuId) {
                $menuRow = \Cruinn\Database::getInstance()->fetch('SELECT name FROM menus WHERE id = ?', [$menuId]);
                echo '<div class="block-preview-placeholder">&#9776; Nav Menu — ' . e($menuRow['name'] ?? "Menu #{$menuId}") . '</div>';
            } else {
                echo '<div class="block-preview-placeholder">&#9776; Nav Menu — no menu selected</div>';
            }
            break;

        case 'event-list':
            echo '<div class="block-preview-placeholder">&#128197; Upcoming Events (showing ' . (int)($content['count'] ?? 5) . ')</div>';
            break;

        case 'map':
            $mapLat = number_format((float)($content['lat'] ?? 53.3498), 4);
            $mapLng = number_format((float)($content['lng'] ?? -6.2603), 4);
            $mapCaption = !empty($content['caption']) ? e($content['caption']) : 'Location';
            echo '<div class="block-preview-placeholder" style="padding:40px 0">&#128205; Map &mdash; ' . $mapCaption . ' (' . $mapLat . ', ' . $mapLng . ')</div>';
            break;

        default:
            echo '<div class="block-preview-placeholder">Block: ' . e($type) . '</div>';
    }
    ?>
        </div><!-- .content-block -->
    </div><!-- .block-editor-item -->
    <?php
}

// ── Zone-aware block grouping ─────────────────────────────────

$useZones  = !empty($editorZones) && is_array($editorZones);
$zoneShow  = $editorShowZones ?? [];
$editorMode = $editorMode ?? 'structured';

if ($useZones) {
    $blocksByZone = [];
    foreach ($editorZones as $z) $blocksByZone[$z] = [];
    foreach ($blocks as $b) {
        $bZone = $b['zone'] ?? 'body';
        if (isset($blocksByZone[$bZone])) $blocksByZone[$bZone][] = $b;
        else $blocksByZone['body'][] = $b;
    }
    $zoneBlockTrees = [];
    foreach ($editorZones as $z) {
        $zoneBlockTrees[$z] = buildBlockTree($blocksByZone[$z]);
    }
} else {
    $blockTree = buildBlockTree($blocks);
}

$zoneLabels = ['header' => 'Header', 'body' => 'Body', 'footer' => 'Footer'];
?>

<!-- Block Editor — Phase 16 -->
<section class="block-editor mode-<?= e($editorMode) ?><?= !empty($editorEmbedded) ? ' editor-embedded' : '' ?><?= $useZones ? ' editor-zoned' : '' ?>"
         data-parent-type="<?= e($editorParentType) ?>"
         data-parent-id="<?= (int)$editorParentId ?>"
         data-editor-mode="<?= e($editorMode) ?>">

    <div class="block-editor-header">
        <h2><?= !empty($editorEmbedded) ? 'Page Builder' : 'Content Blocks' ?></h2>
        <div class="block-editor-toolbar">
            <button type="button" class="btn btn-small btn-undo" disabled title="Undo (Ctrl+Z)">&#8630; Undo</button>
            <button type="button" class="btn btn-small btn-redo" disabled title="Redo (Ctrl+Y)">&#8631; Redo</button>
            <span class="toolbar-sep"></span>
            <button type="button" class="btn btn-small btn-toggle-props" title="Show/Hide Properties Panel">Properties</button>
        </div>
    </div>

    <?php if ($useZones): ?>
    <!-- Zone-based canvas -->
    <div class="block-canvas block-canvas-zoned" id="block-list">
        <?php foreach ($editorZones as $zoneName):
            $zoneVisible = $zoneShow[$zoneName] ?? ($zoneName === 'body');
            if (!$zoneVisible && $zoneName !== 'body') continue;
            $ztree = $zoneBlockTrees[$zoneName] ?? [];
            $zoneSettings    = $tplZoneSettings[$zoneName] ?? [];
            $zoneSettingsJson = htmlspecialchars(json_encode($zoneSettings), ENT_QUOTES, 'UTF-8');
        ?>
        <div class="se-zone<?= $zoneName === 'body' ? ' se-zone-active' : '' ?>"
             data-zone="<?= e($zoneName) ?>"
             data-zone-settings="<?= $zoneSettingsJson ?>">
            <div class="se-zone-label"><?= e($zoneLabels[$zoneName] ?? ucfirst($zoneName)) ?></div>
            <div class="se-zone-canvas">
                <?php if (empty($ztree)): ?>
                    <p class="block-empty">No blocks yet. Use the palette on the left to add one.</p>
                <?php else: ?>
                    <?php foreach ($ztree as $block): renderPreviewBlock($block, $editorParentType, (int)$editorParentId, 0, $zoneName); endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="se-zone-add">
                <button type="button" class="btn btn-small btn-add-block" data-type="section" data-zone="<?= e($zoneName) ?>">+ Section</button>
                <button type="button" class="btn btn-small btn-add-block" data-type="text"    data-zone="<?= e($zoneName) ?>">+ Text</button>
                <button type="button" class="btn btn-small btn-add-block" data-type="heading" data-zone="<?= e($zoneName) ?>">+ Heading</button>
                <button type="button" class="btn btn-small btn-add-block" data-type="image"   data-zone="<?= e($zoneName) ?>">+ Image</button>
                <button type="button" class="btn btn-small btn-add-block" data-type="html"    data-zone="<?= e($zoneName) ?>">+ HTML</button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php else: ?>
    <!-- Single-zone canvas -->
    <div class="block-canvas" id="block-list">
        <?php if (empty($blockTree)): ?>
            <p class="block-empty">No content blocks yet. Add one below.</p>
        <?php else: ?>
            <?php foreach ($blockTree as $block): renderEditorBlock($block, $editorParentType, (int)$editorParentId); endforeach; ?>
        <?php endif; ?>
    </div>
    <div class="block-add">
        <span>Add block:</span>
        <div class="block-add-group">
            <span class="block-add-label">Containers</span>
            <button class="btn btn-small btn-add-block" data-type="section">Section</button>
            <button class="btn btn-small btn-add-block" data-type="columns">Columns</button>
        </div>
        <div class="block-add-group">
            <span class="block-add-label">Content</span>
            <button class="btn btn-small btn-add-block" data-type="text">Text</button>
            <button class="btn btn-small btn-add-block" data-type="heading">Heading</button>
            <button class="btn btn-small btn-add-block" data-type="image">Image</button>
            <button class="btn btn-small btn-add-block" data-type="gallery">Gallery</button>
            <button class="btn btn-small btn-add-block" data-type="html">HTML</button>
        </div>
        <div class="block-add-group">
            <span class="block-add-label">Dynamic</span>
            <button class="btn btn-small btn-add-block" data-type="event-list">Events</button>
            <button class="btn btn-small btn-add-block" data-type="map">Map</button>
            <button class="btn btn-small btn-add-block" data-type="nav-menu">Nav Menu</button>
        </div>
        <div class="block-add-group">
            <span class="block-add-label">Site</span>
            <button class="btn btn-small btn-add-block" data-type="site-logo">Site Logo</button>
            <button class="btn btn-small btn-add-block" data-type="site-title">Site Title</button>
        </div>
    </div>
    <?php endif; ?>
</section>

<?php if (empty($editorEmbedded)): ?>
<div class="block-props-panel" id="block-props-panel" style="display:none">
    <?php include __DIR__ . '/block-props-panel.php'; ?>
</div>
<?php endif; ?>
