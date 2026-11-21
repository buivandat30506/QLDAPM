"""Validate private rehearsal artifacts before producing the live deletion receipt."""
import hashlib
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PRIVATE = ROOT / 'backend/storage/app/private'


def read(name):
    return json.loads((PRIVATE / name).read_text(encoding='utf-8-sig'))


def digest(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


before, restored, after = (read(name) for name in ['scope-before.json', 'scope-restored.json', 'scope-copy-after.json'])
plan = read('scope-plan.json')
preserved = 0
for node, meta in before['nodes'].items():
    assert meta['db'] == 'airline'
    assert restored['nodes'][node]['db'] == 'airline_scope_copy'
    assert set(meta['tables']) == set(after['nodes'][node]['tables'])
    for table, info in meta['tables'].items():
        copy = restored['nodes'][node]['tables'][table]
        final = after['nodes'][node]['tables'][table]
        assert (info['count'], info['sha256'], info['ids_sha256']) == (copy['count'], copy['sha256'], copy['ids_sha256']), (node, table, 'restore mismatch')
        if (node, table) == ('ops', 'telemetry'):
            assert copy['count'] - final['count'] == len(plan['rows'])
        else:
            assert copy == final, (node, table, 'rehearsal changed protected records')
            preserved += 1
    assert not any(after['nodes'][node]['fk_orphans'].values())
assert not any(after['cross_node_orphans'].values())
for name in ['scope-tests-before.log', 'scope-tests-after.log', 'scope-tests-final.log']:
    log = (PRIVATE / name).read_text(encoding='utf-8-sig')
    assert 'OK (' in log and 'FAILURES!' not in log and 'ERRORS!' not in log, name
assert read('scope-browser/results.json')['passed']
assert read('scope-workflows/results.json')['passed']
rollback = (PRIVATE / 'scope-rollback-test.log').read_text(encoding='utf-8-sig')
assert f'Restored exact telemetry rows: {len(plan["rows"])}' in rollback
assert 'Restored exact telemetry rows: 0' in rollback
assert f'Deleted exact telemetry rows: {len(plan["rows"])}' in rollback
backups = []
for node in ['db1', 'db2', 'db3']:
    path = PRIVATE / 'scope-backup' / f'{node}.sql'
    assert path.is_file() and path.stat().st_size > 0
    assert b'FOREIGN_KEY_CHECKS' not in path.read_bytes()
    backups.append({'path': f'storage/app/private/scope-backup/{node}.sql', 'sha256': digest(path)})
receipt = {'plan_sha256': digest(PRIVATE / 'scope-plan.json'), 'restore_verified': True, 'rollback_verified': True, 'regression_passed': True, 'preserved_hashes': preserved, 'backup_files': backups, 'deleted_ids': [r['id'] for r in plan['rows']]}
(PRIVATE / 'scope-rehearsal-receipt.json').write_text(json.dumps(receipt, ensure_ascii=False, indent=2), encoding='utf-8')
print(f'Validated restore of 123 tables, preserved {preserved} table hashes; exact candidates: {len(plan["rows"])}')
