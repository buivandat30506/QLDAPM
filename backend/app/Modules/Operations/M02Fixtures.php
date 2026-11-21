<?php

namespace App\Modules\Operations;

use App\Models\User;
use App\Modules\Finance\OutboxRelay;
use App\Modules\Shared\Access;
use App\Support\CorePruning;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class M02Fixtures
{
    private string $key;

    private Carbon $start;

    private array $actors;

    private int $number;

    private string $prefix;

    public function guard(): void
    {
        abort_unless(app()->environment(['local', 'testing']) && config('m02.local_fixtures_enabled'), 403, 'Cần local/test và AIRLINE_M02_FIXTURES=true cho lệnh chuẩn bị M02.');
        foreach (['ops' => ['db1'], 'mysql' => ['db2'], 'finance' => ['db3']] as $node => $hosts) {
            abort_unless(in_array(config("database.connections.$node.host"), array_merge($hosts, ['localhost', '127.0.0.1', '::1']), true) && in_array(config("database.connections.$node.database"), ['airline', 'airline_test', CorePruning::TARGET], true), 403, 'Chỉ chạy trên database cục bộ riêng.');
        }
        abort_unless(config('payments.mode') === 'mock' && config('payments.bank.mode') === 'mock' && in_array(config('mail.mailers.smtp.host'), ['mailpit', '127.0.0.1', 'localhost'], true), 403, 'Cần thanh toán mock và Mailpit cục bộ.');
    }

    private function step(string $name, callable $action): mixed
    {
        return DB::connection('ops')->transaction(function () use ($name, $action) {
            $db = DB::connection('ops');
            $set = $db->table('m02_demo_sets')->where('key', $this->key)->lockForUpdate()->first();
            $manifest = json_decode($set->manifest, true);
            if (array_key_exists($name, $manifest)) {
                return $manifest[$name];
            }$result = $action();
            $manifest[$name] = $result;
            $db->table('m02_demo_sets')->where('key', $this->key)->update(['manifest' => json_encode($manifest), 'updated_at' => now()]);

            return $result;
        }, 3);
    }

    private function date(int $days, int $hour = 8): Carbon
    {
        return $this->start->copy()->addDays($days)->setTime($hour, 0)->utc();
    }

    private function execute(string $actor, string $action, array $data): object
    {
        return app(OperationsService::class)->execute($this->actors[$actor], $action, $data);
    }

    public function run(string $key, ?string $startsOn, array $actors): array
    {
        $this->guard();
        Validator::make(['key' => $key, 'start' => $startsOn], ['key' => 'required|regex:/^[A-Za-z0-9_-]{1,50}$/', 'start' => 'nullable|date_format:Y-m-d'])->validate();
        $this->key = $key;
        $this->actors = $actors;
        foreach (['creator' => 'data_admin', 'operator' => 'operations', 'reviewer' => 'ops_manager', 'fleet' => 'fleet_manager', 'crew' => 'crew_scheduler', 'technician' => 'maintenance'] as $actor => $role) {
            abort_unless(isset($actors[$actor]), 422, 'Thiếu tài khoản '.$actor);
            Access::role($actors[$actor], $role);
            if (in_array($actor, ['operator', 'reviewer'])) {
                Access::airport($actors[$actor], 'HAN');
            }
        }
        abort_if($actors['creator']->id === $actors['reviewer']->id || $actors['operator']->id === $actors['reviewer']->id, 422, 'Cần người tạo/gửi và người duyệt độc lập.');

        return Cache::lock('m02-fixtures:'.$key, 1800)->block(3, function () use ($key, $startsOn, $actors) {
            $db = DB::connection('ops');
            $set = $db->table('m02_demo_sets')->where('key', $key)->first();
            if ($set) {
                abort_if($startsOn && $startsOn !== $set->starts_on, 409, 'Mã bộ dữ liệu đã gắn với ngày khác.');
                if ($set->status !== 'PREPARING') {
                    return $this->report($key);
                }abort_unless(json_decode($set->actors, true) === array_map(fn ($u) => $u->id, $actors), 409, 'Cần cùng các tài khoản để tiếp tục bộ đang chuẩn bị.');
            } else {
                $date = $startsOn ?? now('Asia/Ho_Chi_Minh')->addDays(3)->toDateString();
                abort_unless($date > now('Asia/Ho_Chi_Minh')->toDateString(), 422, 'Ngày bắt đầu cần sau hôm nay.');
                $db->table('m02_demo_sets')->insert(['key' => $key, 'starts_on' => $date, 'status' => 'PREPARING', 'actors' => json_encode(array_map(fn ($u) => $u->id, $actors)), 'manifest' => '{}', 'created_at' => now(), 'updated_at' => now()]);
                $set = $db->table('m02_demo_sets')->where('key', $key)->first();
            }
            $this->start = Carbon::parse($set->starts_on, 'Asia/Ho_Chi_Minh');
            $this->prefix = 'M02-'.strtoupper(substr(hash('sha256', $key), 0, 6));
            $this->number = 70000 + (hexdec(substr(hash('sha256', $key), 0, 4)) % 19000);
            $route = $db->table('routes')->where('origin', 'HAN')->where('destination', 'SGN')->where('active', true)->first() ?? abort(409, 'Cần đường bay HAN → SGN đang khai thác.');
            $gate = $this->step('gate', fn () => $this->execute('creator', 'resource', ['airport_code' => 'HAN', 'code' => $this->prefix, 'type' => 'GATE'])->id);
            $duration = max(135, $route->duration_minutes);
            abort_if($duration > 180, 409, 'Thời lượng tuyến không phù hợp khung kịch bản; không tự sửa tuyến.');
            $plans = [];
            $flights = [];
            $teams = [];
            foreach (['daily', 'weekly', 'long', 'manual'] as $index => $kind) {
                $plane = $this->step('plane.'.$kind, fn () => $this->execute('fleet', 'aircraft', ['registration' => $this->prefix.'-'.strtoupper(substr($kind, 0, 2)), 'type' => 'A320', 'capacity' => 12])->id);
                $teams[$kind] = [];
                foreach (['PILOT', 'CABIN'] as $i => $position) {
                    $teams[$kind][] = $this->step('crew.'.$kind.'.'.$i, fn () => $this->execute('crew', 'crew', ['code' => $this->prefix.'-'.$kind.'-'.$i, 'name' => 'Tổ bay M02 '.$kind.' '.$position, 'position' => $position, 'qualification' => 'A320', 'license_expires' => $this->start->copy()->addYears(3)->toDateString()])->id);
                }
                $weekdays = $kind === 'weekly' ? [$this->start->isoWeekday(), $this->start->copy()->addDays(2)->isoWeekday(), $this->start->copy()->addDays(4)->isoWeekday()] : [1, 2, 3, 4, 5, 6, 7];
                $plans[$kind] = $this->step('plan.'.$kind, fn () => app(SchedulePlanning::class)->save($actors['creator'], ['code' => $this->prefix.'-'.strtoupper($kind), 'number' => 'AS'.($this->number + $index), 'route_id' => $route->id, 'aircraft_id' => $plane, 'from_date' => $set->starts_on, 'to_date' => $this->start->copy()->addDays($kind === 'long' ? 120 : 60)->toDateString(), 'weekdays' => $weekdays, 'local_departure' => $kind === 'weekly' ? '14:00' : ($kind === 'long' ? '18:00' : '08:00'), 'duration_minutes' => $duration, 'sale_price' => 1590000, 'gate' => $this->prefix, 'status' => 'DRAFT'])->id);
                if ($kind === 'manual') {
                    continue;
                }
                $from = $kind === 'long' ? 7 : 0;
                $to = $kind === 'daily' ? 4 : ($kind === 'weekly' ? 6 : 10);
                $flights[$kind] = $this->step('generated.'.$kind, function () use ($actors, $plans, $kind, $from, $to) {
                    $p = app(SchedulePlanning::class)->plan($actors['creator'], $plans[$kind]);

                    return app(SchedulePlanning::class)->generate($actors['creator'], $p->id, $this->start->copy()->addDays($from)->toDateString(), $this->start->copy()->addDays($to)->toDateString(), $p->version)['created'];
                });
                foreach ($flights[$kind] as $id) {
                    foreach ($teams[$kind] as $crew) {
                        $this->step('assigned.'.$id.'.'.$crew, fn () => $this->execute('crew', 'assign_crew', ['flight_id' => $id, 'crew_member_id' => $crew])->id);
                    }
                }
            }
            foreach (array_merge(array_slice($flights['daily'], 0, 3), array_slice($flights['weekly'], 0, 1)) as $id) {
                $this->step('submitted.'.$id, fn () => $this->execute('operator', 'submit', ['flight_id' => $id])->id);
            }
            foreach (array_slice($flights['long'], 0, 3) as $id) {
                $this->step('submitted.'.$id, fn () => $this->execute('operator', 'submit', ['flight_id' => $id])->id);
                $this->step('published.'.$id, fn () => $this->execute('reviewer', 'publish', ['flight_id' => $id])->id);
                abort_if(app(OutboxRelay::class)->drain() !== 0, 409, 'Cần xử lý outbox trước khi tiếp tục.');
            }
            foreach (array_slice($flights['long'], 0, 3) as $i => $related) {
                $f = $db->table('flights')->find($related);
                $this->step('problem.conflict.'.$i, fn () => $this->execute('operator', 'schedule', ['number' => 'AS'.($this->number + 10 + $i), 'route_id' => $route->id, 'aircraft_id' => $f->aircraft_id, 'departure' => $f->departure.' UTC', 'arrival' => $f->arrival.' UTC', 'sale_price' => 1590000, 'gate' => $this->prefix])->id);
            }
            foreach (['warning', 'condition'] as $kind) {
                $plane = $this->step('plane.'.$kind, fn () => $this->execute('fleet', 'aircraft', ['registration' => $this->prefix.'-'.strtoupper(substr($kind, 0, 2)), 'type' => 'A320', 'capacity' => 12])->id);
                for ($i = 0; $i < 2; $i++) {
                    $departure = $this->date($kind === 'warning' ? 3 : 2 + $i, 11);
                    $this->step('problem.'.$kind.'.'.$i, fn () => $this->execute('operator', 'schedule', ['number' => 'AS'.($this->number + 13 + ($kind === 'condition' ? 2 : 0) + $i), 'route_id' => $route->id, 'aircraft_id' => $plane, 'departure' => $departure->toIso8601String(), 'arrival' => $departure->copy()->addMinutes($duration)->toIso8601String(), 'sale_price' => 1590000, 'gate' => $this->prefix])->id);
                }
                if ($kind === 'condition') {
                    $this->step('work_order', fn () => $this->execute('technician', 'work_order', ['aircraft_id' => $plane, 'title' => 'Kiểm tra khả dụng tàu bay trong kịch bản M02', 'due_at' => $this->date(2, 10)->toIso8601String()])->id);
                }
            }
            $id = $flights['daily'][3];
            $change = $this->step('edit.proposal', function () use ($id, $actors, $db) {
                $f = $db->table('flights')->find($id);

                return app(SchedulePlanning::class)->proposeEdit($actors['operator'], $id, ['version' => $f->version, 'aircraft_id' => $f->aircraft_id, 'departure' => Carbon::parse($f->departure, 'UTC')->setTimezone('Asia/Ho_Chi_Minh')->addMinutes(30)->format('Y-m-d\TH:i'), 'arrival' => Carbon::parse($f->arrival, 'UTC')->setTimezone('Asia/Ho_Chi_Minh')->addMinutes(30)->format('Y-m-d\TH:i'), 'gate' => $f->gate, 'sale_price' => $f->sale_price, 'reason' => 'Điều chỉnh giờ trong kịch bản M02 local']);
            });
            $this->step('edit.approved', function () use ($actors, $change) {
                app(SchedulePlanning::class)->approveEdit($actors['reviewer'], $change);

                return true;
            });
            $this->step('plan_snapshots', function () use ($db, $plans) {
                $snapshots = [];
                foreach ($plans as $id) {
                    $snapshots[$id] = hash('sha256', json_encode($db->table('flight_seasons')->find($id)));
                }

                return $snapshots;
            });
            $db->table('m02_demo_sets')->where('key', $key)->update(['status' => 'READY', 'updated_at' => now()]);

            return $this->report($key);
        });
    }

    public function report(string $key): array
    {
        $db = DB::connection('ops');
        $set = $db->table('m02_demo_sets')->where('key', $key)->first() ?? abort(404);
        $m = json_decode($set->manifest, true);
        $ids = [];
        $planIds = [];
        foreach ($m as $name => $value) {
            if (str_starts_with($name, 'generated.')) {
                $ids = array_merge($ids, $value);
            }if (str_starts_with($name, 'problem.')) {
                $ids[] = $value;
            }if (str_starts_with($name, 'plan.')) {
                $planIds[] = $value;
            }
        }
        $ids = array_values(array_unique($ids));
        $rows = $db->table('flights')->whereIn('id', $ids)->get();
        $groups = ['conflict' => 0, 'condition' => 0, 'warning' => 0, 'valid' => 0];
        foreach ($rows as $f) {
            $issues = app(ScheduleConflicts::class)->inspect($f);
            $categories = array_unique(array_column($issues, 'category'));
            if (! $categories) {
                $categories = ['valid'];
            }foreach ($categories as $c) {
                $groups[$c]++;
            }
        }

        return ['key' => $key, 'status' => $set->status, 'starts_on' => $set->starts_on, 'plan_ids' => $planIds, 'flight_ids' => $ids, 'states' => $rows->countBy('status'), 'published' => $rows->whereNotNull('published_at')->count(), 'groups' => $groups, 'manifest' => $m];
    }

    public function archive(string $key, User $actor): array
    {
        $this->guard();
        Access::role($actor, 'data_admin', 'operations', 'ops_manager');

        return Cache::lock('m02-fixtures:'.$key, 1800)->block(3, function () use ($key, $actor) {
            $report = $this->report($key);
            abort_if($report['status'] === 'PREPARING', 409, 'Bộ dữ liệu đang chuẩn bị; hoàn tất trước khi lưu trữ.');
            $skipped = [];
            foreach ($report['plan_ids'] as $id) {
                $plan = app(SchedulePlanning::class)->plan($actor, $id);
                if ($plan->status === 'ARCHIVED') {
                    continue;
                }if (($report['manifest']['plan_snapshots'][$id] ?? null) !== hash('sha256', json_encode($plan))) {
                    $skipped[] = $id;

                    continue;
                }app(SchedulePlanning::class)->archive($actor, $id, $plan->version);
            }DB::connection('ops')->table('m02_demo_sets')->where('key', $key)->update(['status' => 'ARCHIVED', 'updated_at' => now()]);

            return $this->report($key) + ['preserved_user_plans' => $skipped];
        });
    }
}
