#!/bin/bash

###############################################################################
# Drupal 11 Production Deployment Script
#
# This script handles the full deployment pipeline for the Drupal 11 project
# with Tailwind CSS theme.
###############################################################################

set -e  # Exit on any error

# Get the directory where the script is located
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Trap to ensure maintenance mode is disabled on script exit
cleanup() {
    local exit_code=$?
    if [ $exit_code -ne 0 ]; then
        echo ""
        echo "=========================================="
        echo "ERROR: Deployment failed!"
        echo "Attempting to disable maintenance mode..."
        echo "=========================================="
        drush state:set system.maintenance_mode 0 2>/dev/null || true
    fi
    exit $exit_code
}
trap cleanup EXIT

echo "=========================================="
echo "Starting Drupal 11 Deployment Pipeline"
echo "=========================================="


# 1. Pull latest code from Git
git pull


# 2. Install Composer dependencies
composer install --no-dev --optimize-autoloader --no-interaction

# [Optional on standard hosting - START] Auto-applies noexec workaround if vendor/bin/drush can't run directly.
# Safe to remove if `vendor/bin/drush --version` works on your server without issues.
if ! "$SCRIPT_DIR/vendor/bin/drush" --version >/dev/null 2>&1; then
    source "$SCRIPT_DIR/drush-noexec-workaround.sh"
    if declare -f setup_drush_vendor_symlink >/dev/null 2>&1; then
        setup_drush_vendor_symlink
    fi
fi
# [Optional on standard hosting - END]


# 3. Enable maintenance mode
drush state:set system.maintenance_mode 1


# 4. Build theme assets
THEME_DIR="$SCRIPT_DIR/web/themes/custom/tailwind"
cd "$THEME_DIR"
npm ci --include=dev
npm run build
cd "$SCRIPT_DIR"


# 5. Run Drush deploy
drush deploy -y


# 6. Disable maintenance mode
drush state:set system.maintenance_mode 0


# 7. Verify deployment
drush status

echo ""
echo "=========================================="
echo "Deployment completed successfully!"
echo "=========================================="
echo ""
echo "Next steps:"
echo "  - Verify the site is working"
echo "  - Test critical functionality"
echo "  - Check dblog for any errors"
echo ""
