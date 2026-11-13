#!/usr/bin/env bash
set -euo pipefail
umask 077
cd "$(dirname "$0")/.."
python3 scripts/scope-evidence.py
docker compose stop worker mail-worker app
trap 'docker compose start app worker mail-worker' EXIT
# Exclusive backup directory: the exporter refuses to overwrite an earlier snapshot.
docker compose run --rm --no-deps app php tools/scope-export.php storage/app/private/scope-final-backup
docker compose run --rm --no-deps app php artisan airline:scope-data inventory storage/app/private/scope-local-before-delete.json
docker compose run --rm --no-deps app php artisan airline:scope-data apply storage/app/private/scope-plan.json --receipt=storage/app/private/scope-rehearsal-receipt.json
docker compose run --rm --no-deps app php artisan airline:scope-data inventory storage/app/private/scope-after.json
python3 scripts/scope-verify-local.py
