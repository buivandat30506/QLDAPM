import hashlib
import json
from pathlib import Path

directory=Path(__file__).resolve().parents[1]/'backend/storage/app/private/core-pruning'
read=lambda name: json.loads((directory/name).read_text(encoding='utf-8'))
values=lambda item: item.values() if isinstance(item,dict) else item
after_name='candidate-final.json' if (directory/'candidate-final.json').exists() else 'after.json'
original, restored, after, plan=map(read,['original.json','restored.json',after_name,'plan.json'])
summary=[]
for node, before in original['nodes'].items():
    copy=restored['nodes'][node]
    assert set(before['tables'])==set(copy['tables'])
    for table, info in before['tables'].items():
        assert all(info[k]==copy['tables'][table][k] for k in ['count','sha256','ids_sha256']), (node,table,'restore')
    final=after['nodes'][node]
    assert final['db']==plan['target']
    assert set(final['tables'])==set(plan['preserved'][node])
    for table, expected in plan['preserved'][node].items():
        assert all(expected[k]==final['tables'][table][k] for k in ['count','sha256','ids_sha256']), (node,table,'retained row mismatch')
    assert not any(values(final['fk_orphans'])), (node,'FK orphan')
    expected_keys=[key for key in before['foreign_keys'] if key['TABLE_NAME'] in final['tables'] and key['CONSTRAINT_NAME']!='aircraft_model_id_foreign']
    assert expected_keys==final['foreign_keys'], (node,'unexpected FK change')
    summary.append({'node':node,'tables_before':len(before['tables']),'tables_after':len(final['tables']),'rows_before':sum(t['count'] for t in before['tables'].values()),'rows_after':sum(t['count'] for t in final['tables'].values()),'allocated_before':sum(t['allocated_bytes'] for t in before['tables'].values()),'allocated_restored_before_pruning':sum(t['allocated_bytes'] for t in copy['tables'].values()),'allocated_after':sum(t['allocated_bytes'] for t in final['tables'].values())})
assert not any(after['cross_node_orphans'].values())
assert original['flight_states']==after['flight_states']
assert after.get('published_without_inventory',0)==0
for node in ['db1','db2','db3']:
    data=(directory/'backup'/f'{node}.sql').read_bytes()
    assert b'FOREIGN_KEY_CHECKS' not in data
result={'passed':True,'restore_verified_tables':sum(r['tables_before'] for r in summary),'preserved_tables':sum(r['tables_after'] for r in summary),'dropped_tables':sum(r['tables_before']-r['tables_after'] for r in summary),'archived_demo_groups':len(plan['booking_ids']),'summary':summary,'backup_hashes':{n:hashlib.sha256((directory/'backup'/f'{n}.sql').read_bytes()).hexdigest() for n in ['db1','db2','db3']}}
(directory/'preservation.json').write_text(json.dumps(result,indent=2),encoding='utf-8')
print(json.dumps(result,indent=2))
