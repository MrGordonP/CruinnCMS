# Media Browser — Image Preview & Crop Feature Plan

## Summary

Add a "Preview" button beneath each image tile in the media browser. Clicking it opens a pop-out sub-panel showing the full image with image properties, a drag-to-crop selector, a resize input, a filename field, and save options (overwrite or save as new file).

---

## Files to Change

| File | Change |
|---|---|
| `CruinnCMS/templates/admin/editor.php` | Add preview sub-panel HTML inside `.editor-media-inner` |
| `public_html/css/editor.css` | Preview panel layout, crop overlay, drag handle styles |
| `public_html/js/editor.js` | Open/close preview, crop drag logic, input sync, save fetch call |
| `CruinnCMS/src/Admin/Controllers/MediaController.php` | Add `processMedia()` method |
| `CruinnCMS/config/routes.php` | Add `POST /admin/media/process` route |

---

## 1. HTML — `editor.php`

Add inside `<div class="editor-media-inner">`, after `#editor-media-grid` and before `.editor-media-footer`:

```html
<!-- Image preview / crop sub-panel (hidden by default) -->
<div id="editor-media-preview" style="display:none">
    <div class="editor-media-preview-header">
        <button type="button" id="editor-media-preview-back">← Back</button>
        <span id="editor-media-preview-title"></span>
    </div>
    <div class="editor-media-preview-body">
        <div class="editor-media-crop-wrap">
            <img id="editor-media-preview-img" src="" alt="">
            <div id="editor-media-crop-box"></div>
        </div>
        <div class="editor-media-preview-props">
            <dl>
                <dt>Filename</dt><dd id="editor-media-info-name"></dd>
                <dt>Dimensions</dt><dd id="editor-media-info-dims"></dd>
                <dt>File size</dt><dd id="editor-media-info-size"></dd>
            </dl>
            <hr>
            <label>Crop (px) — drag on image or enter manually</label>
            <div class="editor-media-crop-inputs">
                <label>X <input type="number" id="crop-x" min="0" value="0"></label>
                <label>Y <input type="number" id="crop-y" min="0" value="0"></label>
                <label>W <input type="number" id="crop-w" min="1" value="0"></label>
                <label>H <input type="number" id="crop-h" min="1" value="0"></label>
            </div>
            <label>Resize width (px, 0 = no resize)
                <input type="number" id="crop-resize" min="0" value="0">
            </label>
            <label>Save as
                <input type="text" id="crop-filename" placeholder="Leave blank to overwrite">
            </label>
            <div class="editor-media-preview-actions">
                <button type="button" class="btn btn-primary" id="editor-media-save-btn">Save</button>
                <button type="button" class="btn btn-outline" id="editor-media-insert-original-btn">Insert as-is</button>
            </div>
        </div>
    </div>
</div>
```

Also add a "Preview" button inside each rendered image tile in `loadMediaGrid()` (JS, not PHP).

---

## 2. CSS — `editor.css`

Add to the bottom of the file:

```css
/* ── Media preview / crop panel ──────────────────────────────── */

#editor-media-preview {
    position: absolute;
    inset: 0;
    background: #1a1f2e;
    color: #e2e8f0;
    display: flex;
    flex-direction: column;
    z-index: 10;
}

.editor-media-preview-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.5rem 1rem;
    border-bottom: 1px solid #2d3549;
    flex-shrink: 0;
}

.editor-media-preview-body {
    display: flex;
    flex: 1;
    overflow: hidden;
    gap: 1rem;
    padding: 1rem;
}

.editor-media-crop-wrap {
    position: relative;
    flex: 1;
    overflow: auto;
    background: #0c1614;
    display: flex;
    align-items: flex-start;
    justify-content: flex-start;
}

.editor-media-crop-wrap img {
    display: block;
    max-width: 100%;
    user-select: none;
}

#editor-media-crop-box {
    position: absolute;
    border: 2px dashed #1d9e75;
    pointer-events: none;
    box-shadow: 0 0 0 9999px rgba(0,0,0,0.45);
    display: none;
}

.editor-media-preview-props {
    width: 220px;
    flex-shrink: 0;
    overflow-y: auto;
    font-size: 0.85rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.editor-media-crop-inputs {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.4rem;
}

.editor-media-crop-inputs input,
.editor-media-preview-props input[type="number"],
.editor-media-preview-props input[type="text"] {
    width: 100%;
    padding: 0.25rem 0.4rem;
    background: #0c1614;
    border: 1px solid #2d3549;
    color: #e2e8f0;
    border-radius: 3px;
}

.editor-media-preview-actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-top: auto;
    padding-top: 0.5rem;
}
```

The `.editor-media-inner` must be `position: relative` for the absolute overlay to work — check if it already is; add if not.

---

## 3. JS — `editor.js` (Section H additions)

### 3a. Add a "Preview" button to each image tile in `loadMediaGrid()`

Inside the `files.forEach` block, after the click/dblclick listeners, before `mediaGrid.appendChild(item)`:

```js
var prevBtn = document.createElement('button');
prevBtn.type = 'button';
prevBtn.className = 'btn btn-tiny btn-outline editor-media-preview-btn';
prevBtn.textContent = 'Preview';
prevBtn.addEventListener('click', function (e) {
    e.stopPropagation();
    openMediaPreview(fileUrl);
});
item.appendChild(prevBtn);
```

### 3b. State variables (add near top of Section H)

```js
var mediaPreviewPanel  = document.getElementById('editor-media-preview');
var previewCurrentFile = null;   // web path of the image being previewed
var cropDragging       = false;
var cropStartX, cropStartY;
```

### 3c. `openMediaPreview(fileUrl)` function

```js
function openMediaPreview(fileUrl) {
    previewCurrentFile = fileUrl;
    var img = document.getElementById('editor-media-preview-img');
    img.src = fileUrl;
    document.getElementById('editor-media-preview-title').textContent = fileUrl.split('/').pop();
    document.getElementById('editor-media-info-name').textContent = fileUrl.split('/').pop();
    document.getElementById('crop-filename').value = '';
    // Reset crop
    ['crop-x','crop-y','crop-w','crop-h'].forEach(function(id){ document.getElementById(id).value = 0; });
    document.getElementById('editor-media-crop-box').style.display = 'none';
    img.onload = function () {
        document.getElementById('editor-media-info-dims').textContent = img.naturalWidth + ' × ' + img.naturalHeight + 'px';
        document.getElementById('crop-w').value = img.naturalWidth;
        document.getElementById('crop-h').value = img.naturalHeight;
        document.getElementById('crop-resize').value = 0;
    };
    // File size: fetch HEAD for Content-Length
    fetch(fileUrl, { method: 'HEAD' }).then(function(r){
        var cl = r.headers.get('Content-Length');
        document.getElementById('editor-media-info-size').textContent = cl ? Math.round(cl/1024) + ' KB' : '—';
    }).catch(function(){
        document.getElementById('editor-media-info-size').textContent = '—';
    });
    mediaPreviewPanel.style.display = 'flex';
}
```

### 3d. Back button

```js
document.getElementById('editor-media-preview-back').addEventListener('click', function () {
    mediaPreviewPanel.style.display = 'none';
});
```

### 3e. Insert as-is button

```js
document.getElementById('editor-media-insert-original-btn').addEventListener('click', function () {
    if (previewCurrentFile && mediaCallback) {
        mediaSelected = previewCurrentFile;
        mediaCallback(previewCurrentFile);
        closeMediaPanel();
    }
});
```

### 3f. Drag-to-crop on the image

```js
var cropImg   = document.getElementById('editor-media-preview-img');
var cropBox   = document.getElementById('editor-media-crop-box');
var cropWrap  = document.querySelector('.editor-media-crop-wrap');

cropImg.addEventListener('mousedown', function (e) {
    cropDragging = true;
    var rect = cropImg.getBoundingClientRect();
    cropStartX = e.clientX - rect.left;
    cropStartY = e.clientY - rect.top;
    cropBox.style.display = 'block';
    cropBox.style.left   = cropStartX + 'px';
    cropBox.style.top    = cropStartY + 'px';
    cropBox.style.width  = '0px';
    cropBox.style.height = '0px';
    e.preventDefault();
});

document.addEventListener('mousemove', function (e) {
    if (!cropDragging) { return; }
    var rect   = cropImg.getBoundingClientRect();
    var curX   = Math.max(0, Math.min(e.clientX - rect.left, cropImg.clientWidth));
    var curY   = Math.max(0, Math.min(e.clientY - rect.top,  cropImg.clientHeight));
    var x = Math.min(cropStartX, curX);
    var y = Math.min(cropStartY, curY);
    var w = Math.abs(curX - cropStartX);
    var h = Math.abs(curY - cropStartY);
    cropBox.style.left   = x + 'px';
    cropBox.style.top    = y + 'px';
    cropBox.style.width  = w + 'px';
    cropBox.style.height = h + 'px';
    // Scale to natural pixel coords
    var scaleX = cropImg.naturalWidth  / cropImg.clientWidth;
    var scaleY = cropImg.naturalHeight / cropImg.clientHeight;
    document.getElementById('crop-x').value = Math.round(x * scaleX);
    document.getElementById('crop-y').value = Math.round(y * scaleY);
    document.getElementById('crop-w').value = Math.round(w * scaleX);
    document.getElementById('crop-h').value = Math.round(h * scaleY);
});

document.addEventListener('mouseup', function () { cropDragging = false; });
```

### 3g. Save button

```js
document.getElementById('editor-media-save-btn').addEventListener('click', function () {
    if (!previewCurrentFile) { return; }
    var fd = new FormData();
    fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
    fd.append('file',   previewCurrentFile);
    fd.append('x',      document.getElementById('crop-x').value);
    fd.append('y',      document.getElementById('crop-y').value);
    fd.append('w',      document.getElementById('crop-w').value);
    fd.append('h',      document.getElementById('crop-h').value);
    fd.append('resize', document.getElementById('crop-resize').value);
    fd.append('saveas', document.getElementById('crop-filename').value.trim());
    fetch('/admin/media/process', { method: 'POST', body: fd })
        .then(function(r){ return r.json(); })
        .then(function(data){
            if (data.error) { alert('Save failed: ' + data.error); return; }
            // Reload preview with new/updated file
            openMediaPreview(data.url);
            // Refresh grid in background so it's up to date on back
            loadMediaGrid(mediaCurrentFolder);
        })
        .catch(function(){ alert('Save request failed.'); });
});
```

**Note on CSRF token:** The CSRF token needs to be available to JS. The existing approach in the codebase should already provide it (check how other panels POST — look for `document.querySelector('[name="csrf_token"]')` or a meta tag pattern, and match whatever is already used).

---

## 4. PHP — `MediaController.php`

Add a new public method `processMedia()`:

```php
/**
 * POST /admin/media/process — Crop and/or resize an existing media file.
 * Body: file (web path), x, y, w, h (crop in natural px, all 0 = no crop),
 *       resize (max width in px, 0 = no resize), saveas (filename, blank = overwrite)
 */
public function processMedia(): void
{
    \Cruinn\CSRF::validate();
    $publicRoot = CRUINN_PUBLIC;
    $slug       = $this->storageSlug();
    $mediaRoot  = realpath($publicRoot . '/storage/' . $slug . '/media');
    $fileParam  = trim($this->input('file', ''));

    if ($fileParam === '') {
        $this->json(['error' => 'No file specified'], 400);
        return;
    }

    $absFile = realpath($publicRoot . $fileParam);
    if (!$absFile || !str_starts_with($absFile, $mediaRoot) || !is_file($absFile)) {
        $this->json(['error' => 'Invalid file'], 400);
        return;
    }

    $x      = max(0, (int) $this->input('x', 0));
    $y      = max(0, (int) $this->input('y', 0));
    $w      = max(0, (int) $this->input('w', 0));
    $h      = max(0, (int) $this->input('h', 0));
    $resize = max(0, (int) $this->input('resize', 0));
    $saveas = trim($this->input('saveas', ''));

    // Validate saveas filename (no path traversal)
    if ($saveas !== '' && preg_match('/[\/\\\\.]{2}|[^a-zA-Z0-9_\-.]/', $saveas)) {
        $this->json(['error' => 'Invalid filename'], 400);
        return;
    }

    $info = getimagesize($absFile);
    if ($info === false) {
        $this->json(['error' => 'Not a valid image'], 400);
        return;
    }

    $mime     = $info['mime'];
    $srcW     = $info[0];
    $srcH     = $info[1];

    $src = match ($mime) {
        'image/jpeg' => imagecreatefromjpeg($absFile),
        'image/png'  => imagecreatefrompng($absFile),
        'image/gif'  => imagecreatefromgif($absFile),
        'image/webp' => imagecreatefromwebp($absFile),
        default      => null,
    };

    if ($src === null) {
        $this->json(['error' => 'Unsupported image type'], 400);
        return;
    }

    // Crop step
    $doCrop = ($w > 0 && $h > 0 && ($x > 0 || $y > 0 || $w < $srcW || $h < $srcH));
    if ($doCrop) {
        // Clamp to image bounds
        $w = min($w, $srcW - $x);
        $h = min($h, $srcH - $y);
        $cropped = imagecreatetruecolor($w, $h);
        if (in_array($mime, ['image/png', 'image/gif', 'image/webp'])) {
            imagealphablending($cropped, false);
            imagesavealpha($cropped, true);
        }
        imagecopy($cropped, $src, 0, 0, $x, $y, $w, $h);
        imagedestroy($src);
        $src  = $cropped;
        $srcW = $w;
        $srcH = $h;
    }

    // Resize step
    if ($resize > 0 && $srcW > $resize) {
        $ratio   = $resize / $srcW;
        $newW    = $resize;
        $newH    = (int) ($srcH * $ratio);
        $resized = imagecreatetruecolor($newW, $newH);
        if (in_array($mime, ['image/png', 'image/gif', 'image/webp'])) {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
        }
        imagecopyresampled($resized, $src, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);
        imagedestroy($src);
        $src = $resized;
    }

    // Determine output path
    if ($saveas !== '') {
        // Ensure correct extension
        $ext = strtolower(pathinfo($absFile, PATHINFO_EXTENSION));
        if (strtolower(pathinfo($saveas, PATHINFO_EXTENSION)) !== $ext) {
            $saveas .= '.' . $ext;
        }
        $outAbs = dirname($absFile) . DIRECTORY_SEPARATOR . basename($saveas);
    } else {
        $outAbs = $absFile; // overwrite
    }

    $ok = match ($mime) {
        'image/jpeg' => imagejpeg($src, $outAbs, 85),
        'image/png'  => imagepng($src, $outAbs, 6),
        'image/gif'  => imagegif($src, $outAbs),
        'image/webp' => imagewebp($src, $outAbs, 85),
        default      => false,
    };
    imagedestroy($src);

    if (!$ok) {
        $this->json(['error' => 'Failed to save image'], 500);
        return;
    }

    // Build web path for the output file
    $outRel = '/' . ltrim(str_replace('\\', '/', substr($outAbs, strlen($publicRoot))), '/');
    $this->json(['success' => true, 'url' => $outRel]);
}
```

---

## 5. Route — `routes.php`

Add inside the `adminMiddleware` block alongside the other media routes:

```php
$router->post('/admin/media/process', [MediaController::class, 'processMedia']);
```

---

## Notes

- **CSRF token in JS**: Check how other POSTs in `editor.js` supply the CSRF token (likely via a hidden field on the page or a `<meta>` tag). Use the same pattern — do not invent a new one.
- **No new dependencies**: All image manipulation uses PHP GD (already used by `uploadFile()`).
- **Security**: Path traversal is prevented by `realpath()` + `str_starts_with($absFile, $mediaRoot)` — same pattern as the rest of the controller. The `saveas` filename is additionally sanitised.
- **Overwrite vs new file**: If `saveas` is blank, the original file is overwritten in place. If provided, the new file is saved alongside the original in the same directory.
- **The preview overlay sits inside `.editor-media-inner`**: that div must have `position: relative` in the CSS for the absolute overlay to be scoped correctly.
