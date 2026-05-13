#!/usr/bin/env bash
set -euo pipefail

PLUGIN_SLUG="clientflow"
VERSION=$(grep -m1 "Version:" clientflow.php | sed 's/.*Version:[[:space:]]*//' | tr -d '[:space:]')
ZIP_NAME="${PLUGIN_SLUG}-${VERSION}.zip"
TMP_DIR=$(mktemp -d)
DEST="${TMP_DIR}/${PLUGIN_SLUG}"

echo "→ Building assets..."
npm run build

echo "→ Packaging ${ZIP_NAME}..."
mkdir -p "$DEST"

rsync -a \
  --exclude='.git' \
  --exclude='node_modules' \
  --exclude='.wp-env' \
  --exclude='.wp-env.json' \
  --exclude='tests' \
  --exclude='.gitignore' \
  --exclude='.eslintrc.json' \
  --exclude='package.json' \
  --exclude='package-lock.json' \
  --exclude='webpack.config.js' \
  --exclude='phpunit.xml.dist' \
  --exclude='bin' \
  --exclude='languages/.gitkeep' \
  --exclude='.DS_Store' \
  --exclude='assets/screenshot-*' \
  --exclude='assets/banner-*' \
  --exclude='assets/icon-*' \
  --exclude='*.map' \
  --exclude='admin/*.jsx' \
  --exclude='admin/components' \
  --exclude='admin/pages' \
  --exclude='admin/styles' \
  --exclude='client/index.jsx' \
  --exclude='portal/index.jsx' \
  --exclude='portal/portal-globals.js' \
  . "$DEST/"

cd "$TMP_DIR"
zip -r "${OLDPWD}/${ZIP_NAME}" "${PLUGIN_SLUG}/"
rm -rf "$TMP_DIR"

echo "✓ Created: ${ZIP_NAME}"
