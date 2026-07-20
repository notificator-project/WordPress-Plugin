#!/usr/bin/env bash
set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PRETTIER_BIN="$PLUGIN_DIR/node_modules/.bin/prettier"
PHPCBF_BIN="$PLUGIN_DIR/vendor/bin/phpcbf"

if [[ ! -x "$PRETTIER_BIN" ]]; then
	echo "Prettier is unavailable. Run npm install in $PLUGIN_DIR." >&2
	exit 1
fi

echo "Formatting TypeScript, styles, and build configuration..."
"$PRETTIER_BIN" --no-error-on-unmatched-pattern --write \
	"$PLUGIN_DIR/assets/js/**/*.ts" \
	"$PLUGIN_DIR/assets/src/**/*.{ts,scss,css}" \
	"$PLUGIN_DIR"/*.config.{ts,js} \
	"$PLUGIN_DIR/package.json" \
	"$PLUGIN_DIR/tsconfig.json"

if [[ ! -x "$PHPCBF_BIN" ]]; then
	echo "PHPCBF is unavailable; skipping PHP formatting. Run composer install to enable it."
	exit 0
fi

echo "Formatting PHP with WordPress Coding Standards..."
set +e
"$PHPCBF_BIN" --standard="$PLUGIN_DIR/phpcs.xml.dist" --basepath="$PLUGIN_DIR"
PHPCBF_STATUS=$?
set -e

# PHPCBF returns 1 when it fixed violations and 0 when no changes were needed.
if (( PHPCBF_STATUS > 1 )); then
	exit "$PHPCBF_STATUS"
fi
