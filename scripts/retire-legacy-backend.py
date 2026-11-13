"""One-time source reduction; preserves the exact previous files in a private archive."""
from pathlib import Path
import re
import shutil

root=Path(__file__).resolve().parents[1]
archive=root/'backend/storage/app/private/core-pruning/retired-source'

def read(path):
    p=root/path
    dest=archive/path
    if not dest.exists():
        dest.parent.mkdir(parents=True,exist_ok=True)
        shutil.copyfile(p,dest)
    return p.read_text(encoding='utf-8-sig')

def write(path,s):
    (root/path).write_text(s,encoding='utf-8')

def end_block(s,start):
    i=s.index('{',start); depth=0; quote=None
    while i<len(s):
        c=s[i]
        if quote:
            if c=='\\': i+=2; continue
            if c==quote: quote=None
        elif c in "'\"`": quote=c
        elif s.startswith('//',i):
            i=s.index('\n',i); continue
        elif s.startswith('/*',i):
            i=s.index('*/',i)+2; continue
        elif c=='{': depth+=1
        elif c=='}':
            depth-=1
            if depth==0: return i+1
        i+=1
    raise ValueError('Unbalanced PHP block')

def methods(s):
    return {m.group(1):(m.start(),end_block(s,m.start())) for m in re.finditer(r'^    (?:public|private|protected) function (\w+)\(',s,re.M)}

def replace_method(s,name,body):
    a,b=methods(s)[name];return s[:a]+body+s[b:]

p=Path('backend/app/Modules/Shared/UseCaseService.php');s=read(p);m=methods(s)
kept=['validate','ops','audit','result','airportUpdate','airportArea','seasonRule','actualTime']
head=s[:min(a for a,b in m.values())]
execute='''    public function execute(User $user, string $action, array $input): object
    {
        $method = match ($action) {
            'airport_update' => 'airportUpdate',
            'airport_area' => 'airportArea',
            'season_rule' => 'seasonRule',
            'actual_time' => 'actualTime',
            default => abort(403, 'Thao tác chưa được hỗ trợ trong phiên bản hiện tại.'),
        };
        return DB::connection('ops')->transaction(fn () => $this->{$method}($user, $input), 3);
    }
'''
write(p,head+execute+'\n'+'\n\n'.join(s[m[n][0]:m[n][1]] for n in kept)+'\n}\n')

p=Path('backend/app/Modules/Finance/FinanceService.php');s=read(p);m=methods(s)
legacy=s[:s.index('class FinanceService')].replace('namespace App\\Modules\\Finance;','namespace Database\\Seeders;')+'class LegacyReconciliation\n{\n'+'\n\n'.join(s[m[n][0]:m[n][1]] for n in ['reconcile','resolve','close'])+'\n}\n'
legacy_path=root/'backend/database/seeders/LegacyReconciliation.php'
assert not legacy_path.exists()
legacy_path.write_text(legacy,encoding='utf-8')
for a,b in sorted([m[n] for n in ['reconcile','resolve','close']],reverse=True):s=s[:a]+s[b:]
write(p,s)
p=Path('backend/database/seeders/AirportDemoSeeder.php');s=read(p)
s=s.replace('$fin->reconcile(', 'app(LegacyReconciliation::class)->reconcile(').replace('$fin->close(', 'app(LegacyReconciliation::class)->close(').replace('app(FinanceService::class)->reconcile(', 'app(LegacyReconciliation::class)->reconcile(')
needle="    public function generate(callable $progress, ?string $directory = null): void\n    {"
assert needle in s
s=s.replace(needle,needle+"\n        if (!\\Illuminate\\Support\\Facades\\Schema::connection('ops')->hasTable('devices')) {\n            throw new \\RuntimeException('Full-system demo generation is retired on the compact schema. Existing M01/M02/M03 data is preserved; use airline:m02-demo for scheduling fixtures.');\n        }")
write(p,s)

p=Path('backend/app/Http/Controllers/WorkspaceController.php');s=read(p)
# Remove the independent workspace SQL/form blocks, keeping M01/M02/M03 and system sections.
pattern=r"^        if \(\$section === '(?:fleet|crew|maintenance|ground|baggage|customer|finance|fare|iot|analytics)'"
while match:=re.search(pattern,s,re.M):
    finish=end_block(s,match.start());s=s[:match.start()]+s[finish:]
s=s.replace("in_array($section, ['catalog', 'fleet', 'crew'])", "$section === 'catalog'")
s=s.replace("'catalog' => ['airports', 'routes', 'airport_resources'], 'fleet' => ['aircraft'], 'crew' => ['crew_members']", "'catalog' => ['airports', 'routes', 'airport_resources']")
start=s.index('        $showPayments =');finish=s.index("        return view('workspace'",start)
s=s[:start]+"        $transfers = $payouts = collect();\n\n"+s[finish:]
for name in ['refundDecide','fare','fareClass']:
    a,b=methods(s)[name];s=s[:a]+s[b:]
s=replace_method(s,'conversation', '''    public function conversation(Request $r, int $id)
    {
        Access::role($r->user(), 'passenger', 'customer_service');
        return view('scope-empty', ['title' => 'Yêu cầu hỗ trợ']);
    }''')
s=replace_method(s,'export', '''    public function export(Request $r, int $id)
    {
        Access::role($r->user(), 'finance');
        return view('scope-empty', ['title' => 'Đối soát doanh thu']);
    }''')
start=s.index('        $result = match ($action) {');finish=end_block(s,start)
s=s[:start]+'''        $result = match ($action) {
            'master_propose' => app(MasterDataService::class)->propose($u, $d),
            'master_approve' => app(MasterDataService::class)->approve($u, (int) ($d['change_id'] ?? 0)),
            'resource_propose' => app(FlightChangeService::class)->propose($u, $d),
            'resource_decide' => app(FlightChangeService::class)->decide($u, $d),
            'read_notice' => $this->readNotice($u, $d),
            'read_all_notices' => $this->readAllNotices($u),
            'airport_update', 'airport_area', 'season_rule', 'actual_time' => app(UseCaseService::class)->execute($u, $action, $d),
            default => app(OperationsService::class)->execute($u, $action, $d),
        }'''+s[finish:]
# Metadata and availability checks still run inside the unchanged domain services.
write(p,s)

p=Path('backend/app/Http/Controllers/BusinessController.php');s=read(p)
s=replace_method(s,'index', '''    public function index(Request $r, string $section)
    {
        $meta = self::AREAS[$section] ?? abort(404);
        Access::role($r->user(), ...$meta[1]);
        if ($section !== 'passenger-recovery') return view('scope-empty', ['title' => $meta[0]]);
        $r->validate(['flight' => 'nullable|integer|min:1']);
        $data = ['title' => $meta[0], 'section' => $section];
        $data['flights'] = DB::connection('ops')->table('flights')->where('origin', $r->user()->airport_code)->whereNotIn('status', ['DRAFT', 'PENDING'])->where('departure', '>', now()->subDays(2))->orderBy('departure')->limit(100)->get();
        $id = $r->integer('flight') ?: $data['flights']->first()?->id;
        $data['flight'] = $id ? app(FlightCatalog::class)->get($id) : null;
        if ($data['flight']) {
            Access::airport($r->user(), $data['flight']->origin);
            $data += app(PassengerRecovery::class)->impact($r->user(), $id);
        }
        return $r->expectsJson() ? response()->json($data) : view('business.passenger-recovery', $data);
    }''')
s=replace_method(s,'action', '''    public function action(Request $r, string $action)
    {
        abort_unless(in_array($action, ['recovery_propose', 'recovery_decide'], true), 403);
        $d = $r->all();
        if ($action === 'recovery_propose' && $r->filled('choice')) {
            $r->validate(['choice' => 'required|regex:/^\\d+:[A-Z0-9_]{1,20}$/']);
            [$d['target_flight_id'], $d['fare_class']] = explode(':', $r->input('choice'));
        }
        if ($action === 'recovery_decide') $r->validate(['id' => 'required|uuid', 'approve' => 'required|boolean']);
        $result = $action === 'recovery_propose'
            ? app(PassengerRecovery::class)->propose($r->user(), $d)
            : app(PassengerRecovery::class)->decide($r->user(), $d['id'], $r->boolean('approve'));
        return $r->expectsJson() ? response()->json($result) : back()->with('message', 'Đã ghi nhận thao tác.');
    }''')
a,b=methods(s)['escalate'];s=s[:a]+s[b:];write(p,s)

p=Path('backend/app/Http/Controllers/ModuleController.php');s=read(p)
start=s.index('    private const ROLES');stop=s.index('    private const ACTIONS')
roles=s[start:stop]
write(p,'''<?php

namespace App\\Http\\Controllers;

use App\\Modules\\Shared\\Access;
use App\\Support\\ReleaseScope;
use Illuminate\\Http\\Request;

/** Legacy aggregate URLs remain available without loading retired module tables. */
class ModuleController extends Controller
{
'''+roles+'''    public function index(Request $request, string $module)
    {
        Access::role($request->user(), ...(self::ROLES[$module] ?? abort(404)));
        $title = ['m1' => 'Quản lý cốt lõi', 'm2' => 'Đặt chỗ và vé', 'm3' => 'Cảnh báo thông minh'][$module];
        return $request->expectsJson() ? response()->json(['title' => $title, 'data' => [], 'status' => 'unavailable']) : view('scope-empty', compact('title'));
    }

    public function action(Request $request, string $module, string $action)
    {
        Access::role($request->user(), ...(self::ROLES[$module] ?? abort(404)));
        abort_unless(ReleaseScope::allowsAction($request, $action), 403);
        return app(WorkspaceController::class)->dispatch($request, $action);
    }
}
''')

p=Path('backend/routes/console.php');s=read(p)
start=s.index("Artisan::command('airline:iot-simulate")
s=s[:start];write(p,s)

for p in [Path('backend/app/Modules/Customer/SupportDesk.php'),Path('backend/app/Modules/Finance/FinanceOperations.php'),Path('backend/app/Modules/Finance/RevenueReport.php')]:
    read(p)
    (root/p).unlink()
print('Reduced shared controllers/services and removed retired support escalation, reconciliation admin and revenue services. Previous source archived privately.')
