#!/usr/bin/env bash
#
# Builds the MTNIMA site from this repository in a local DDEV project:
# Drupal is installed from config/sync, Drupal's Arabic/French interface
# translations are downloaded, and the content is created by seed.php from
# the files in web/modules/custom/mtnma_site/content and the theme images.
# No database dump is needed.
#
# Usage:
#   scripts/setup-site.sh              Build the site in an empty DDEV database.
#   scripts/setup-site.sh --reinstall  Replace an existing site: takes a DDEV
#                                      snapshot first, then ERASES the database.
#
# The admin account (user 1) gets a random password that is not shown or
# stored; sign in with "ddev drush user:login" and create personal accounts
# (see HANDOFF.md).

set -euo pipefail
cd "$(dirname "$0")/.."

reinstall=false
case "${1:-}" in
  '') ;;
  --reinstall) reinstall=true ;;
  *) echo "Unknown option: $1 (use --reinstall or nothing)" >&2; exit 2 ;;
esac

if ! command -v ddev >/dev/null; then
  echo "DDEV is required: https://docs.ddev.com/en/stable/" >&2
  exit 1
fi

echo "==> Starting DDEV and installing PHP dependencies"
ddev start
ddev composer install --no-interaction

echo "==> Pointing Drupal at the committed configuration (config/sync)"
settings=web/sites/default/settings.php
if [ ! -f "$settings" ]; then
  echo "$settings is missing; DDEV normally creates it on 'ddev start'." >&2
  exit 1
fi
if ! grep -q "config_sync_directory'\] = '../config/sync'" "$settings"; then
  printf "\n\$settings['config_sync_directory'] = '../config/sync';\n" >> "$settings"
fi

if ddev drush status --field=bootstrap 2>/dev/null | grep -q Successful; then
  if ! $reinstall; then
    echo "A Drupal site is already installed in this DDEV project. Nothing was changed." >&2
    echo "To replace it (this ERASES its database after a snapshot), run: $0 --reinstall" >&2
    exit 1
  fi
  snapshot="before-reinstall-$(date +%Y%m%d-%H%M%S)"
  echo "==> Saving the current database as DDEV snapshot '$snapshot'"
  ddev snapshot --name="$snapshot"
fi

echo "==> Installing Drupal from config/sync"
password="$(LC_ALL=C tr -dc 'A-Za-z0-9' </dev/urandom | head -c 32 || true)"
ddev drush site:install --existing-config --account-name=admin --account-pass="$password" --yes 2>&1 \
  | grep -v -i "password" || true
unset password
if ! ddev drush status --field=bootstrap 2>/dev/null | grep -q Successful; then
  echo "Drupal installation failed; see the messages above." >&2
  exit 1
fi
# The installer skips the English language (the site default is Arabic) and
# with it the English configuration translations; a second import adds them.
ddev drush config:import --yes

echo "==> Downloading Drupal's Arabic and French interface translations"
ddev drush locale:check
ddev drush locale:update

echo "==> Creating the content (pages, news, services, menus, images)"
ddev drush php:script web/modules/custom/mtnma_site/scripts/seed.php

echo "==> Importing the theme's translations"
for langcode in ar fr; do
  ddev drush locale:import "$langcode" "/var/www/html/web/themes/custom/mtnma/translations/$langcode.po" --type=customized --override=all
done
ddev drush cache:rebuild

cat <<EOF

Done. Open the site with:  ddev launch /ar   (or /fr)
Sign in as the admin account with a one-time link:  ddev drush user:login
Then create a personal account with the Administrator role (HANDOFF.md, "Accounts").
EOF
