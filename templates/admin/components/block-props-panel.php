<?php
/**
 * Block Properties Panel — Phase 16 rebuild
 *
 * 7-group accordion: Identity | Layout | Size | Spacing | Typography | Background | Border
 *
 * Shared between standalone block editor and embedded site editor.
 * The wrapping <div id="block-props-panel"> is provided by the caller.
 */
\Cruinn\Template::requireCss('admin-block-editor.css');
?>

    <div class="block-props-header">
        <h3>Block Properties <span class="block-props-target-label"></span></h3>
        <button type="button" class="block-props-close" title="Close">&times;</button>
    </div>
    <div class="block-props-nav">
        <select class="prop-input prop-full" id="block-props-selector">
            <option value="">— Select block —</option>
        </select>
        <span class="block-props-save-indicator" style="display:none">Saved</span>
    </div>
    <div class="block-props-body">

        <!-- ── 1. Identity ────────────────────────────────── -->
        <div class="props-group open" data-group="identity">
            <div class="props-group-header">
                Identity <span class="pg-chevron">&#9650;</span>
            </div>
            <div class="props-group-body">
                <div class="prop-row">
                    <label>Name</label>
                    <input type="text" class="prop-input" data-prop="blockName" placeholder="e.g. Main Intro Text" style="flex:1">
                </div>
                <div class="prop-row">
                    <label>CSS ID</label>
                    <input type="text" class="prop-input" data-prop="cssId" placeholder="e.g. hero-section" style="flex:1">
                </div>
                <div class="prop-row">
                    <label>Classes</label>
                    <input type="text" class="prop-input" data-prop="cssClass" placeholder="e.g. pull-quote shadow" style="flex:1">
                </div>
                <div class="prop-row">
                    <label>Role</label>
                    <select class="prop-input" data-prop="role" style="flex:1">
                        <option value="">(none)</option>
                        <option value="header">Header</option>
                        <option value="footer">Footer</option>
                        <option value="hero">Hero</option>
                        <option value="page-title">Page Title</option>
                        <option value="post-title">Post Title</option>
                        <option value="sub-heading">Sub-heading</option>
                        <option value="call-to-action">Call to Action</option>
                        <option value="nav">Navigation</option>
                        <option value="sidebar">Side-Bar</option>
                        <option value="content-container">Content Container</option>
                        <option value="caption">Caption</option>
                        <option value="event-date">Event Date</option>
                        <option value="venue">Venue</option>
                    </select>
                </div>
                <div class="prop-row" style="gap:4px;flex-wrap:wrap">
                    <label style="width:80px;flex-shrink:0">Order</label>
                    <button type="button" class="btn btn-small btn-move-block" data-dir="first" title="Move to first">&#8676; First</button>
                    <button type="button" class="btn btn-small btn-move-block" data-dir="forward" title="Move forward">&#8593; Up</button>
                    <button type="button" class="btn btn-small btn-move-block" data-dir="back" title="Move back">&#8595; Down</button>
                    <button type="button" class="btn btn-small btn-move-block" data-dir="last" title="Move to last">Last &#8677;</button>
                </div>
            </div>
        </div>

        <!-- ── 1b. Content (block-type specific) ───────────── -->
        <div class="props-group open" data-group="content" style="display:none">
            <div class="props-group-header">
                Content <span class="pg-chevron">&#9650;</span>
            </div>
            <div class="props-group-body">

                <!-- nav-menu -->
                <div class="content-section" data-for-type="nav-menu" style="display:none">
                    <div class="prop-row">
                        <label>Menu</label>
                        <select class="prop-input" data-content-prop="menu_id" style="flex:1">
                            <option value="0">(none)</option>
                        </select>
                    </div>
                </div>

                <!-- site-logo -->
                <div class="content-section" data-for-type="site-logo" style="display:none">
                    <div class="prop-row">
                        <label>Logo URL</label>
                        <input type="text" class="prop-input" data-content-prop="src" placeholder="(use site default)" style="flex:1">
                        <button type="button" class="btn btn-small prop-browse-logo" title="Browse media">&#128269;</button>
                    </div>
                    <div class="prop-row">
                        <label>Alt Text</label>
                        <input type="text" class="prop-input" data-content-prop="alt" placeholder="(site name)" style="flex:1">
                    </div>
                    <div class="prop-row">
                        <label>Link URL</label>
                        <input type="text" class="prop-input" data-content-prop="linkUrl" placeholder="/" style="flex:1">
                    </div>
                </div>

                <!-- site-title -->
                <div class="content-section" data-for-type="site-title" style="display:none">
                    <div class="prop-row">
                        <label>Tagline</label>
                        <input type="text" class="prop-input" data-content-prop="tagline" placeholder="Sub-heading / tagline..." style="flex:1">
                    </div>
                    <div class="prop-row">
                        <label>Tag</label>
                        <select class="prop-input" data-content-prop="taglineTag" style="flex:1">
                            <option value="p">&lt;p&gt;</option>
                            <option value="span">&lt;span&gt;</option>
                            <option value="h2">&lt;h2&gt;</option>
                            <option value="h3">&lt;h3&gt;</option>
                        </select>
                    </div>
                </div>

            </div>
        </div>

        <!-- ── 2. Layout (section/columns only) ───────────── -->
        <div class="props-group" data-group="layout" style="display:none">
            <div class="props-group-header">
                Layout <span class="pg-chevron">&#9660;</span>
            </div>
            <div class="props-group-body">
                <div class="prop-row">
                    <label>Display</label>
                    <div style="flex:1;display:flex;gap:8px;align-items:center">
                        <label style="width:auto;font-size:12px"><input type="radio" name="pg-display" data-prop="display" value="block"> Block</label>
                        <label style="width:auto;font-size:12px"><input type="radio" name="pg-display" data-prop="display" value="flex"> Flex</label>
                        <label style="width:auto;font-size:12px"><input type="radio" name="pg-display" data-prop="display" value="grid"> Grid</label>
                    </div>
                </div>
                <!-- Grid sub-section -->
                <div class="layout-grid-section" style="display:none">
                    <div class="prop-row">
                        <label>Preset</label>
                        <div class="col-preset-picker">
                            <button type="button" class="btn btn-small col-preset-btn" data-cols="1fr 1fr" title="2 equal columns">&frac12; &frac12;</button>
                            <button type="button" class="btn btn-small col-preset-btn" data-cols="1fr 1fr 1fr" title="3 equal columns">&frac13; &frac13; &frac13;</button>
                            <button type="button" class="btn btn-small col-preset-btn" data-cols="2fr 1fr" title="2/3 + 1/3">&frac23; &frac13;</button>
                            <button type="button" class="btn btn-small col-preset-btn" data-cols="1fr 2fr" title="1/3 + 2/3">&frac13; &frac23;</button>
                            <button type="button" class="btn btn-small col-preset-btn" data-cols="1fr 1fr 1fr 1fr" title="4 equal columns">&frac14;&times;4</button>
                        </div>
                    </div>
                    <div class="prop-row">
                        <label>Columns</label>
                        <input type="text" class="prop-input" data-prop="gridCols" placeholder="e.g. 1fr 1fr" style="flex:1">
                    </div>
                    <div class="prop-row">
                        <label>Gap</label>
                        <input type="text" class="prop-input" data-prop="gridGap" placeholder="e.g. 16px" style="flex:1">
                    </div>
                </div>
                <!-- Flex sub-section -->
                <div class="layout-flex-section" style="display:none">
                    <div class="prop-row">
                        <label>Direction</label>
                        <select class="prop-input" data-prop="flexDir" style="flex:1">
                            <option value="row">Row</option>
                            <option value="row-reverse">Row Reverse</option>
                            <option value="column">Column</option>
                            <option value="column-reverse">Column Reverse</option>
                        </select>
                    </div>
                    <div class="prop-row">
                        <label>Wrap</label>
                        <select class="prop-input" data-prop="flexWrap" style="flex:1">
                            <option value="nowrap">No Wrap</option>
                            <option value="wrap">Wrap</option>
                            <option value="wrap-reverse">Wrap Reverse</option>
                        </select>
                    </div>
                    <div class="prop-row">
                        <label>Gap</label>
                        <input type="text" class="prop-input" data-prop="flexGap" placeholder="e.g. 16px" style="flex:1">
                    </div>
                </div>
                <!-- Shared grid+flex controls -->
                <div class="layout-grid-section layout-flex-section" style="display:none">
                    <div class="prop-row">
                        <label>Align Items</label>
                        <select class="prop-input" data-prop="alignItems" style="flex:1">
                            <option value="">—</option>
                            <option value="flex-start">Start</option>
                            <option value="center">Center</option>
                            <option value="flex-end">End</option>
                            <option value="stretch">Stretch</option>
                            <option value="baseline">Baseline</option>
                        </select>
                    </div>
                    <div class="prop-row">
                        <label>Justify</label>
                        <select class="prop-input" data-prop="justifyContent" style="flex:1">
                            <option value="">—</option>
                            <option value="flex-start">Start</option>
                            <option value="center">Center</option>
                            <option value="flex-end">End</option>
                            <option value="space-between">Space Between</option>
                            <option value="space-around">Space Around</option>
                            <option value="space-evenly">Space Evenly</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── 3. Size ────────────────────────────────────── -->
        <div class="props-group" data-group="size">
            <div class="props-group-header">
                Size <span class="pg-chevron">&#9660;</span>
            </div>
            <div class="props-group-body">
                <div class="prop-row">
                    <label>Width</label>
                    <input type="text" class="prop-input prop-small" data-prop="width" placeholder="auto" style="flex:1">
                    <select class="prop-input prop-unit-sel" data-unit="widthUnit">
                        <option value="px">px</option>
                        <option value="%">%</option>
                        <option value="vw">vw</option>
                        <option value="em">em</option>
                        <option value="rem">rem</option>
                    </select>
                </div>
                <div class="prop-row">
                    <label>Height</label>
                    <input type="text" class="prop-input prop-small" data-prop="height" placeholder="auto" style="flex:1">
                    <select class="prop-input prop-unit-sel" data-unit="heightUnit">
                        <option value="px">px</option>
                        <option value="%">%</option>
                        <option value="vh">vh</option>
                        <option value="em">em</option>
                        <option value="rem">rem</option>
                    </select>
                </div>
                <div class="prop-row">
                    <label>Max Width</label>
                    <input type="text" class="prop-input prop-small" data-prop="maxWidth" placeholder="none" style="flex:1">
                    <span class="prop-unit">px</span>
                </div>
                <div class="prop-row">
                    <label>Min Width</label>
                    <input type="text" class="prop-input prop-small" data-prop="minWidth" placeholder="0" style="flex:1">
                    <span class="prop-unit">px</span>
                </div>
                <div class="prop-row">
                    <label>Min Height</label>
                    <input type="text" class="prop-input prop-small" data-prop="minHeight" placeholder="0" style="flex:1">
                    <span class="prop-unit">px</span>
                </div>
                <div class="prop-row">
                    <label>Max Height</label>
                    <input type="text" class="prop-input prop-small" data-prop="maxHeight" placeholder="none" style="flex:1">
                    <span class="prop-unit">px</span>
                </div>
            </div>
        </div>

        <!-- ── 4. Spacing ────────────────────────────────── -->
        <div class="props-group" data-group="spacing">
            <div class="props-group-header">
                Spacing <span class="pg-chevron">&#9660;</span>
            </div>
            <div class="props-group-body">
                <div class="prop-row" style="margin-bottom:4px">
                    <label>Margin unit</label>
                    <select class="prop-input prop-unit-sel" data-unit="marginUnit" style="flex:1">
                        <option value="px">px</option>
                        <option value="%">%</option>
                        <option value="em">em</option>
                        <option value="rem">rem</option>
                        <option value="vw">vw</option>
                        <option value="vh">vh</option>
                    </select>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px;margin-bottom:8px">
                    <?php foreach (['mt'=>'Top','mr'=>'Right','mb'=>'Bottom','ml'=>'Left'] as $key => $lbl): ?>
                    <div class="prop-field-labeled">
                        <span class="prop-field-label"><?= $lbl ?></span>
                        <div class="prop-field-auto-wrap">
                            <input type="text" class="prop-input prop-small" data-prop="<?= $key ?>" placeholder="0">
                            <button type="button" class="prop-auto-btn" data-target="<?= $key ?>" title="Set to auto">A</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="prop-row" style="margin-bottom:4px">
                    <label>Padding unit</label>
                    <select class="prop-input prop-unit-sel" data-unit="paddingUnit" style="flex:1">
                        <option value="px">px</option>
                        <option value="%">%</option>
                        <option value="em">em</option>
                        <option value="rem">rem</option>
                        <option value="vw">vw</option>
                        <option value="vh">vh</option>
                    </select>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px">
                    <?php foreach (['pt'=>'Top','pr'=>'Right','pb'=>'Bottom','pl'=>'Left'] as $key => $lbl): ?>
                    <div class="prop-field-labeled">
                        <span class="prop-field-label"><?= $lbl ?></span>
                        <input type="text" class="prop-input prop-small" data-prop="<?= $key ?>" placeholder="0">
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- ── 5. Typography ─────────────────────────────── -->
        <div class="props-group" data-group="typography">
            <div class="props-group-header">
                Typography <span class="pg-chevron">&#9660;</span>
            </div>
            <div class="props-group-body">
                <div class="prop-row">
                    <label>Text Colour</label>
                    <input type="color" class="prop-input" data-prop="textColor" value="#000000" style="width:36px;padding:2px">
                    <input type="text" class="prop-input" data-prop="textColor" placeholder="#000 or inherit" style="flex:1">
                </div>
                <div class="prop-row">
                    <label>Font Size</label>
                    <input type="text" class="prop-input prop-small" data-prop="fontSize" placeholder="auto" style="flex:1">
                    <select class="prop-input prop-unit-sel" data-unit="fontSizeUnit">
                        <option value="px">px</option>
                        <option value="em">em</option>
                        <option value="rem">rem</option>
                        <option value="%">%</option>
                        <option value="vw">vw</option>
                    </select>
                </div>
                <div class="prop-row">
                    <label>Font Weight</label>
                    <select class="prop-input" data-prop="fontWeight" style="flex:1">
                        <option value="">—</option>
                        <option value="300">300 Light</option>
                        <option value="400">400 Normal</option>
                        <option value="500">500 Medium</option>
                        <option value="600">600 Semi-Bold</option>
                        <option value="700">700 Bold</option>
                        <option value="800">800 Extra Bold</option>
                    </select>
                </div>
                <div class="prop-row">
                    <label>Text Align</label>
                    <select class="prop-input" data-prop="textAlign" style="flex:1">
                        <option value="">—</option>
                        <option value="left">Left</option>
                        <option value="center">Center</option>
                        <option value="right">Right</option>
                        <option value="justify">Justify</option>
                    </select>
                </div>
                <div class="prop-row">
                    <label>Line Height</label>
                    <input type="text" class="prop-input" data-prop="lineHeight" placeholder="normal" style="flex:1">
                </div>
            </div>
        </div>

        <!-- ── 6. Background ─────────────────────────────── -->
        <div class="props-group" data-group="background">
            <div class="props-group-header">
                Background <span class="pg-chevron">&#9660;</span>
            </div>
            <div class="props-group-body">
                <div class="prop-row">
                    <label>BG Colour</label>
                    <input type="color" class="prop-input" data-prop="bgColor" value="#ffffff" style="width:36px;padding:2px">
                    <input type="text" class="prop-input" data-prop="bgColor" placeholder="transparent" style="flex:1">
                </div>
                <div class="prop-row">
                    <label>BG Image</label>
                    <input type="text" class="prop-input" data-prop="bgImage" placeholder="/uploads/..." style="flex:1">
                    <button type="button" class="btn btn-small prop-browse-bg">&#128269;</button>
                </div>
                <div class="prop-row">
                    <label>BG Size</label>
                    <select class="prop-input" data-prop="bgSize" style="flex:1">
                        <option value="cover">Cover</option>
                        <option value="contain">Contain</option>
                        <option value="auto">Auto</option>
                        <option value="100% 100%">Stretch</option>
                    </select>
                </div>
                <div class="prop-row">
                    <label>BG Position</label>
                    <select class="prop-input" data-prop="bgPos" style="flex:1">
                        <option value="center center">Center</option>
                        <option value="center top">Top</option>
                        <option value="center bottom">Bottom</option>
                        <option value="left center">Left</option>
                        <option value="right center">Right</option>
                    </select>
                </div>
                <div class="prop-row">
                    <label>BG Repeat</label>
                    <select class="prop-input" data-prop="bgRepeat" style="flex:1">
                        <option value="no-repeat">No Repeat</option>
                        <option value="repeat">Repeat</option>
                        <option value="repeat-x">Repeat X</option>
                        <option value="repeat-y">Repeat Y</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- ── 7. Border ─────────────────────────────────── -->
        <div class="props-group" data-group="border">
            <div class="props-group-header">
                Border <span class="pg-chevron">&#9660;</span>
            </div>
            <div class="props-group-body">
                <div class="prop-row">
                    <label>Width</label>
                    <input type="text" class="prop-input prop-small" data-prop="borderWidth" placeholder="0" style="flex:1">
                    <span class="prop-unit">px</span>
                </div>
                <div class="prop-row">
                    <label>Style</label>
                    <select class="prop-input" data-prop="borderStyle" style="flex:1">
                        <option value="solid">Solid</option>
                        <option value="dashed">Dashed</option>
                        <option value="dotted">Dotted</option>
                        <option value="double">Double</option>
                        <option value="none">None</option>
                    </select>
                </div>
                <div class="prop-row">
                    <label>Colour</label>
                    <input type="text" class="prop-input" data-prop="borderColor" placeholder="#ccc" style="flex:1">
                </div>
                <div class="prop-row">
                    <label>Radius</label>
                    <input type="text" class="prop-input prop-small" data-prop="borderRadius" placeholder="0" style="flex:1">
                    <select class="prop-input prop-unit-sel" data-unit="borderRadiusUnit">
                        <option value="px">px</option>
                        <option value="%">%</option>
                        <option value="em">em</option>
                    </select>
                </div>
            </div>
        </div>

    </div><!-- /.block-props-body -->

    <div class="block-props-footer">
        <button type="button" class="btn btn-small btn-danger btn-delete-from-panel">Delete Block</button>
        <button type="button" class="btn btn-small btn-save-named" style="display:none">Save as Named Block&hellip;</button>
    </div>
