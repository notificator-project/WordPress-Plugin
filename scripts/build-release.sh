#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DIST_DIR="$ROOT_DIR/dist"
PACKAGE_SLUG="notificator-project"
PLUGIN_FILE="notificator-project.php"
PACKAGE_DIR="$DIST_DIR/$PACKAGE_SLUG"
ZIP_PATH="$DIST_DIR/$PACKAGE_SLUG.zip"
SAMPLE_SLUG="notificator-sample-plugin"
SAMPLE_ZIP="$ROOT_DIR/examples/$SAMPLE_SLUG.zip"

SKIP_BUILD="${1:-}"

if [[ "$SKIP_BUILD" != "--skip-build" ]]; then
	echo "Formatting source files..."
	npm --prefix "$ROOT_DIR" run format

	echo "Type-checking and building admin assets..."
	npm --prefix "$ROOT_DIR" run check
fi

echo "Building sample integration package..."
rm -f "$ROOT_DIR/examples/notificator-companion-sample-plugin.zip"
rm -f "$SAMPLE_ZIP"
(
	cd "$ROOT_DIR/examples"
	zip -rq "$SAMPLE_ZIP" "$SAMPLE_SLUG"
)

echo "Preparing release directory..."
rm -rf "$PACKAGE_DIR"
mkdir -p "$PACKAGE_DIR"

# Copy only runtime/distribution files.
cp "$ROOT_DIR/$PLUGIN_FILE" "$PACKAGE_DIR/"
cp "$ROOT_DIR/readme.txt" "$PACKAGE_DIR/"
cp "$ROOT_DIR/uninstall.php" "$PACKAGE_DIR/"
cp "$ROOT_DIR/THIRD-PARTY-NOTICES.txt" "$PACKAGE_DIR/"

cp -R "$ROOT_DIR/admin" "$PACKAGE_DIR/"
mkdir -p "$PACKAGE_DIR/assets"
cp -R "$ROOT_DIR/assets/dist" "$PACKAGE_DIR/assets/"
cp -R "$ROOT_DIR/includes" "$PACKAGE_DIR/"
cp -R "$ROOT_DIR/languages" "$PACKAGE_DIR/"
mkdir -p "$PACKAGE_DIR/examples"
cp "$SAMPLE_ZIP" "$PACKAGE_DIR/examples/"

# Remove junk files that can trigger Plugin Check warnings.
find "$PACKAGE_DIR" -name '.DS_Store' -delete
find "$PACKAGE_DIR" -name '*.backup' -delete
find "$PACKAGE_DIR" -name 'methods_insert.txt' -delete

for required_file in "$PLUGIN_FILE" readme.txt uninstall.php THIRD-PARTY-NOTICES.txt assets/dist/admin.js assets/dist/admin.css assets/dist/admin-toast.js assets/dist/admin-toast.css examples/notificator-sample-plugin.zip; do
	if [[ ! -f "$PACKAGE_DIR/$required_file" ]]; then
		echo "Missing required release file: $required_file" >&2
		exit 1
	fi
done

if find "$PACKAGE_DIR" -type f \( -name '*.map' -o -name '*.backup' -o -name '.DS_Store' \) -print -quit | grep -q .; then
	echo "Development-only files remain in the release package." >&2
	exit 1
fi

mkdir -p "$DIST_DIR"
rm -rf "$DIST_DIR/notificator-companion"
rm -f "$DIST_DIR/notificator-companion.zip"
rm -rf "$DIST_DIR/notificator"
rm -f "$DIST_DIR/notificator.zip"
rm -f "$ZIP_PATH"

(
	cd "$DIST_DIR"
	zip -rq "$ZIP_PATH" "$PACKAGE_SLUG"
)

unzip -tq "$ZIP_PATH" >/dev/null

echo "Release package created: $ZIP_PATH"
