#!/usr/bin/env bash
# CruinnCMS release package builder
# Usage: bash dev/build-release.sh [version]
# If version is omitted, the most recent git tag is used.
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

VERSION="${1:-$(git -C "$ROOT" describe --tags --abbrev=0 2>/dev/null || echo "dev")}"
PKG_NAME="cruinncms-${VERSION}"
RELEASE_DIR="$ROOT/release"
OUTPUT="$RELEASE_DIR/${PKG_NAME}.zip"

if [[ -f "$OUTPUT" ]]; then
    echo "Release already exists: $OUTPUT"
    echo "Delete it first if you want to rebuild."
    exit 1
fi

echo "==> Building $PKG_NAME..."

TMP="$(mktemp -d)"
PKG_DIR="$TMP/$PKG_NAME"
mkdir -p "$PKG_DIR"

# ── home_root/ — extract to /home/username/ ─────────────────────
# Engine dirs sit above public_html; never web-served
HOME_DIR="$PKG_DIR/home_root"
mkdir -p "$HOME_DIR"
cp -r "$ROOT/src"       "$HOME_DIR/src"
cp -r "$ROOT/templates" "$HOME_DIR/templates"
cp -r "$ROOT/schema"    "$HOME_DIR/schema"
cp -r "$ROOT/config"    "$HOME_DIR/config"
mkdir -p "$HOME_DIR/instance"

# Remove sensitive/local config that should never ship
rm -f "$HOME_DIR/config/config.local.php"
rm -f "$HOME_DIR/config/CruinnCMS.php"

# ── public_html/CruinnCMS/ — extract to public_html/CruinnCMS/ ──
PUB_DIR="$PKG_DIR/public_html/CruinnCMS"
mkdir -p "$PUB_DIR"
cp -r "$ROOT/public/." "$PUB_DIR/"

# Patch CRUINN_ROOT: public_html/CruinnCMS/ is two levels below home root
sed -i "s|define('CRUINN_ROOT', dirname(__DIR__))|define('CRUINN_ROOT', dirname(__DIR__, 2))|" "$PUB_DIR/index.php"

# Clean writable dirs — keep structure, strip any real content
find "$PUB_DIR/storage" -mindepth 1 ! -name '.gitkeep' -delete 2>/dev/null || true
find "$PUB_DIR/uploads" -mindepth 1 ! -name '.gitkeep' -delete 2>/dev/null || true

# Docs at zip root for reference
cp "$ROOT/dev/README.md" "$PKG_DIR/README.md"
cp "$ROOT/dev/SETUP.md"  "$PKG_DIR/SETUP.md"

# Package
mkdir -p "$RELEASE_DIR"
cd "$TMP"
zip -qr "$OUTPUT" "$PKG_NAME/"
rm -rf "$TMP"

echo "==> Done: $OUTPUT"
