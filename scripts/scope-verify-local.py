import json
from pathlib import Path

private = Path(__file__).resolve().parents[1] / 'backend/storage/app/private'
read = lambda name: json.loads((private / name).read_text(encoding='utf-8-sig'))
before, after, plan = read('scope-local-before-delete.json'), read('scope-after.json'), read('scope-plan.json')
preserved = 0
for node, meta in before['nodes'].items():
    target = after['nodes'][node]
    assert meta['uuid'] == target['uuid'] and meta['db'] == target['db'] == 'airline'
    assert set(meta['tables']) == set(target['tables'])
    assert meta['foreign_keys'] == target['foreign_keys']
    assert not any(target['fk_orphans'].values())
    for table, info in meta['tables'].items():
        final = target['tables'][table]
        if (node, table) == ('ops', 'telemetry'):
            assert info['count'] - final['count'] == len(plan['rows'])
        else:
            assert info == final, (node, table, 'protected records changed')
            preserved += 1
assert not any(after['cross_node_orphans'].values())
assert before['flight_states'] == after['flight_states']
result = {'passed': True, 'preserved_tables': preserved, 'removed_rows': len(plan['rows']), 'foreign_key_orphans': 0, 'cross_node_orphans': 0, 'flight_states_preserved': True}
(private / 'scope-preservation.json').write_text(json.dumps(result, indent=2), encoding='utf-8')
print(json.dumps(result))
