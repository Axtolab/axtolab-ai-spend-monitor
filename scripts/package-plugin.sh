#!/usr/bin/env bash
# Builds the WP.org submission zip for AI Spend Monitor.
# Emits dist/<slug>-<version>.zip and dist/<slug>.zip (stable alias) + .sha256 files.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
REPO_ROOT="$(dirname "$SCRIPT_DIR")"
PLUGIN_FILE="axtolab-ai-spend-monitor.php"
PLUGIN_SLUG="axtolab-ai-spend-monitor"
WPORGIGNORE="$REPO_ROOT/.wporgignore"

VERSION=$(grep -m1 "Version:" "$REPO_ROOT/$PLUGIN_FILE" | sed "s/.*Version:[[:space:]]*//" | tr -d '[:space:]')
STABLE_TAG=$(grep -m1 "^Stable tag:" "$REPO_ROOT/readme.txt" | sed "s/.*Stable tag:[[:space:]]*//" | tr -d '[:space:]')

if [[ "$VERSION" != "$STABLE_TAG" ]]; then
  echo "ERROR: Version ($VERSION) in $PLUGIN_FILE != Stable tag ($STABLE_TAG) in readme.txt" >&2
  exit 1
fi

mkdir -p "$REPO_ROOT/dist"
rm -f "$REPO_ROOT/dist/$PLUGIN_SLUG"*.zip "$REPO_ROOT/dist/$PLUGIN_SLUG"*.sha256

STAGE_DIR="$(mktemp -d)"
trap 'rm -rf "$STAGE_DIR"' EXIT

mkdir -p "$STAGE_DIR/$PLUGIN_SLUG"
rsync -a --exclude-from="$WPORGIGNORE" "$REPO_ROOT/" "$STAGE_DIR/$PLUGIN_SLUG/"

# Sanity: zip must contain slug/main-file and no excluded artifacts.
[[ -f "$STAGE_DIR/$PLUGIN_SLUG/$PLUGIN_FILE" ]] || { echo "ERROR: staged main file missing" >&2; exit 1; }
if find "$STAGE_DIR/$PLUGIN_SLUG" \( -name "*.git*" -o -name "composer.*" -o -name "node_modules" -o -name "banner-*" -o -name "screenshot-*" \) | grep -q .; then
  echo "ERROR: excluded files leaked into stage" >&2
  exit 1
fi

OUTPUT_VERSIONED="$REPO_ROOT/dist/$PLUGIN_SLUG-$VERSION.zip"
OUTPUT_STABLE="$REPO_ROOT/dist/$PLUGIN_SLUG.zip"

( cd "$STAGE_DIR" && zip -rq "$OUTPUT_VERSIONED" "$PLUGIN_SLUG" )
cp "$OUTPUT_VERSIONED" "$OUTPUT_STABLE"

if command -v shasum >/dev/null 2>&1; then SHA="shasum -a 256"; else SHA="sha256sum"; fi
( cd "$REPO_ROOT/dist" && $SHA "$(basename "$OUTPUT_VERSIONED")" > "$(basename "$OUTPUT_VERSIONED").sha256" && $SHA "$(basename "$OUTPUT_STABLE")" > "$(basename "$OUTPUT_STABLE").sha256" )

echo "Built: $OUTPUT_VERSIONED"
echo "Built: $OUTPUT_STABLE (stable alias)"
unzip -l "$OUTPUT_STABLE" | tail -3
