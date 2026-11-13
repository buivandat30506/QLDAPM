import json
import re
from pathlib import Path

root=Path(__file__).resolve().parents[1]
p=root/'backend/storage/app/private/core-pruning'
read=lambda name:json.loads((p/name).read_text(encoding='utf-8'))
proof=read('preservation.json')
assert proof['passed'] and proof['dropped_tables']==39 and proof['preserved_tables']==84
for name in ['tests-before.log','tests-after.log','tests-final.log']:
    text=(p/name).read_text(encoding='utf-8')
    assert re.search(r'OK \(\d+ tests, \d+ assertions\)',text),name
for name in ['browser-workflows/results.json','browser-navigation/results.json']:
    result=read(name)
    assert result['passed'] and not result['errors'],name
assert read('browser-navigation/results.json')['base']=='http://127.0.0.1:8002'
if (p/'original-final.json').exists():
    old,new=read('original.json'),read('original-final.json')
    for node in old['nodes']:
        assert new['nodes'][node]['db']=='airline'
        assert set(old['nodes'][node]['tables'])==set(new['nodes'][node]['tables'])
        for table,info in old['nodes'][node]['tables'].items():
            assert all(info[k]==new['nodes'][node]['tables'][table][k] for k in ['count','sha256','ids_sha256']), f'Original changed during rehearsal: {node}.{table}; cutover refused'
print('All schema, row preservation, regression and browser gates passed.')
