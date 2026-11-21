#!/usr/bin/env bash
set -euo pipefail
umask 077
cd "$(dirname "$0")/.."
private=backend/storage/app/private/core-pruning
python3 scripts/verify-core-pruning.py
python3 scripts/core-cutover-gate.py
bash scripts/core-pruning.sh grant
# Verify the normal application DB user, without running the website as root.
bash scripts/core-pruning.sh app-run php artisan airline:relay
docker compose stop worker mail-worker app
trap 'docker compose start app worker mail-worker' EXIT
bash scripts/core-pruning.sh source php -d memory_limit=512M artisan airline:core-prune inventory storage/app/private/core-pruning/original-final.json
python3 scripts/core-cutover-gate.py
test ! -f "$private/pre-cutover.env"
cp backend/.env "$private/pre-cutover.env"
python3 - <<'PY'
from pathlib import Path
import re
p=Path('backend/.env')
s=p.read_text(encoding='utf-8-sig')
assert re.search(r'^DB_DATABASE=airline\s*$',s,re.M),'Original local configuration changed; refusing overwrite'
s,count=re.subn(r'^DB_DATABASE=.*$', 'DB_DATABASE=airline_core_20261113',s,count=1,flags=re.M)
assert count==1
p.write_text(s,encoding='utf-8')
print('Switched only DB_DATABASE to the verified compact schema. Original database retained.')
PY
