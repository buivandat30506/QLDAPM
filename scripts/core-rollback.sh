#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
docker compose stop worker mail-worker app
trap 'docker compose start app worker mail-worker' EXIT
python3 - <<'PY'
from pathlib import Path
import re
p=Path('backend/.env')
s=p.read_text(encoding='utf-8-sig')
assert re.search(r'^DB_DATABASE=airline_core_20261113\s*$',s,re.M),'Unexpected configuration; refusing rollback'
s,count=re.subn(r'^DB_DATABASE=.*$', 'DB_DATABASE=airline',s,count=1,flags=re.M)
assert count==1
p.write_text(s,encoding='utf-8')
print('Application now selects the preserved original airline schema; compact copy is retained.')
PY
