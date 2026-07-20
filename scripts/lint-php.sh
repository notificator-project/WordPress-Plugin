#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

while IFS= read -r -d '' file; do
	php -l "$file" >/dev/null
done < <(
	find "$ROOT_DIR" \
		-path "$ROOT_DIR/dist" -prune -o \
		-path "$ROOT_DIR/node_modules" -prune -o \
		-path "$ROOT_DIR/vendor" -prune -o \
		-name '*.php' -type f -print0
)

echo "PHP syntax checks passed."
