#!/bin/bash

###############################################################################
# Full-site snapshot via `drush archive:dump` (DB + files + code).
# https://www.drupal.org/docs/updating-drupal/how-to-back-up-your-drupal-site
# Transient tables are structure-only so restore stays valid but caches/sessions stay empty.
# Output: snapshot_YYYY-MM-DD_HH-MM-SS.tar.gz in project root.
###############################################################################

set -e  # Exit on any error

# Get the directory where the script is located
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# [Optional on standard hosting - START] Auto-applies noexec workaround if vendor/bin/drush can't run directly.
# Safe to remove if `vendor/bin/drush --version` works on your server without issues.
if ! "$SCRIPT_DIR/vendor/bin/drush" --version >/dev/null 2>&1; then
    source "$SCRIPT_DIR/drush-noexec-workaround.sh"
fi
# [Optional on standard hosting - END]

DEST="$SCRIPT_DIR/snapshot_$(date '+%Y-%m-%d_%H-%M-%S').tar.gz"

echo "Creating archive (database, files, code): ${DEST}"
drush archive:dump \
    --destination="$DEST" \
    --structure-tables-list="cache,cache_*,flood,history,sessions,watchdog" \
    -y

echo ""
echo "Done!"
echo "Restore (from this machine, path as appropriate): drush archive:restore \"$DEST\""
