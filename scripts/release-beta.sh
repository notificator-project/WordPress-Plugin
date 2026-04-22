#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_FILE="$ROOT_DIR/notificator-companion.php"

BETA_VERSION="${1:-}"
PUSH_REMOTE="false"

if [[ -z "$BETA_VERSION" ]]; then
  echo "Usage: npm run release:beta -- <x.y.z-beta.N> [--push]"
  echo "Example: npm run release:beta -- 1.0.0-beta.2 --push"
  exit 1
fi

if [[ "${2:-}" == "--push" ]]; then
  PUSH_REMOTE="true"
fi

if [[ ! "$BETA_VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+-beta\.[0-9]+$ ]]; then
  echo "ERROR: Version must match x.y.z-beta.N (example: 1.2.0-beta.1)"
  exit 1
fi

if [[ ! -f "$PLUGIN_FILE" ]]; then
  echo "ERROR: Plugin file not found: $PLUGIN_FILE"
  exit 1
fi

if [[ -n "$(git -C "$ROOT_DIR" status --porcelain)" ]]; then
  echo "ERROR: Working tree is not clean. Commit or stash changes first."
  exit 1
fi

TAG="v$BETA_VERSION"

if git -C "$ROOT_DIR" rev-parse "$TAG" >/dev/null 2>&1; then
  echo "ERROR: Tag already exists: $TAG"
  exit 1
fi

# Keep plugin header version and runtime constant synchronized.
sed -i.bak -E "s/^\s*\*\s*Version:\s*.*/ * Version: $BETA_VERSION/" "$PLUGIN_FILE"
sed -i.bak -E "s/define\( 'NOTIFICATOR_COMPANION_VERSION', '[^']+' \);/define( 'NOTIFICATOR_COMPANION_VERSION', '$BETA_VERSION' );/" "$PLUGIN_FILE"
rm -f "$PLUGIN_FILE.bak"

if ! grep -q "^ \* Version: $BETA_VERSION$" "$PLUGIN_FILE"; then
  echo "ERROR: Failed to update plugin header Version in $PLUGIN_FILE"
  exit 1
fi

if ! grep -q "define( 'NOTIFICATOR_COMPANION_VERSION', '$BETA_VERSION' );" "$PLUGIN_FILE"; then
  echo "ERROR: Failed to update NOTIFICATOR_COMPANION_VERSION in $PLUGIN_FILE"
  exit 1
fi

git -C "$ROOT_DIR" add "$PLUGIN_FILE"
git -C "$ROOT_DIR" commit -m "chore(release): beta $BETA_VERSION"
git -C "$ROOT_DIR" tag "$TAG"

echo "Created beta release commit and tag: $TAG"

echo "Next: git push origin HEAD && git push origin $TAG"

if [[ "$PUSH_REMOTE" == "true" ]]; then
  CURRENT_BRANCH="$(git -C "$ROOT_DIR" rev-parse --abbrev-ref HEAD)"
  git -C "$ROOT_DIR" push origin "$CURRENT_BRANCH"
  git -C "$ROOT_DIR" push origin "$TAG"
  echo "Pushed branch and tag to origin. GitHub Actions will create the pre-release."
fi
