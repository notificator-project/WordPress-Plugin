#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DIST_DIR="$ROOT_DIR/dist"
PACKAGE_SLUG="notificator-companion"
PACKAGE_DIR="$DIST_DIR/$PACKAGE_SLUG"
ZIP_PATH="$DIST_DIR/$PACKAGE_SLUG.zip"

SKIP_BUILD="${1:-}"

if [[ "$SKIP_BUILD" != "--skip-build" ]]; then
  echo "Building admin assets..."
  npm --prefix "$ROOT_DIR" run build
fi

echo "Preparing release directory..."
rm -rf "$PACKAGE_DIR"
mkdir -p "$PACKAGE_DIR"

# Copy only runtime/distribution files.
cp "$ROOT_DIR/notificator-companion.php" "$PACKAGE_DIR/"
cp "$ROOT_DIR/readme.txt" "$PACKAGE_DIR/"
cp "$ROOT_DIR/uninstall.php" "$PACKAGE_DIR/"

cp -R "$ROOT_DIR/admin" "$PACKAGE_DIR/"
cp -R "$ROOT_DIR/assets" "$PACKAGE_DIR/"
cp -R "$ROOT_DIR/includes" "$PACKAGE_DIR/"
cp -R "$ROOT_DIR/languages" "$PACKAGE_DIR/"

# Remove junk files that can trigger Plugin Check warnings.
find "$PACKAGE_DIR" -name '.DS_Store' -delete

mkdir -p "$DIST_DIR"
rm -f "$ZIP_PATH"

(
  cd "$DIST_DIR"
  zip -rq "$ZIP_PATH" "$PACKAGE_SLUG"
)

echo "Release package created: $ZIP_PATH"
