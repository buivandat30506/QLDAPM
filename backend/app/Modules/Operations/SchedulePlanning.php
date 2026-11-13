<?php

namespace App\Modules\Operations;

use App\Models\User;
use App\Modules\Finance\OutboxRelay;
use App\Modules\Shared\Access;
use App\Modules\Shared\Audit;
use App\Support\SchedulingDisplay;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class SchedulePlanning
{
    private function validateCandidate(object $flight): void
    {
        $db = DB::connection('ops');
        abort_unless($db->table('airport_resources')->where('airport_code', $flight->origin)->where('type', 'GATE')->where('code', $flight->gate)->where('active', true)->exists(), 422, 'Cổng đang hoạt động phải thuộc sân bay đi.');
        abort_if($db->table('flights')->where('number', $flight->number)->where('departure', $flight->departure)->where('id', '!=', $flight->id ?? 0)->exists(), 409, 'Số hiệu và thời điểm khởi hành đã tồn tại.');
        app(ScheduleConflicts::class)->assertValid($flight);
    }

    public function plan(User $user, int $id): object
    {
        Access::role($user, 'data_admin', 'operations', 'ops_manager');
        $plan = DB::connection('ops')->table('flight_seasons')->find($id) ?? abort(404);
        $route = DB::connection('ops')->table('routes')->find($plan->route_id) ?? abort(404);
        Access::airport($user, $route->origin);

        return $plan;
    }

    public function flight(User $user, int $id): object
    {
        Access::role($user, 'data_admin', 'operations', 'ops_manager');
        $row = DB::connection('ops')->table('flights')->find($id) ?? abort(404);
        Access::airport($user, $row->origin);

        return $row;
    }

    public function save(User $user, array $input, ?int $id = null): object
    {
        Access::role($user, 'data_admin', 'operations', 'ops_manager');

        return DB::connection('ops')->transaction(function () use ($user, $input, $id) {
            $db = DB::connection('ops');
            $old = $id ? $this->plan($user, $id) : null;
            if ($old) {
                $old = $db->table('flight_seasons')->where('id', $id)->lockForUpdate()->first();
            }
            $d = Validator::make($input, [
                'code' => 'required|string|max:60', 'number' => 'required|regex:/^[A-Z]{2}[0-9]{2,5}$/', 'route_id' => 'required|integer', 'aircraft_id' => 'required|integer',
                'from_date' => 'required|date_format:Y-m-d', 'to_date' => 'required|date_format:Y-m-d|after_or_equal:from_date', 'weekdays' => 'required|array|min:1|max:7', 'weekdays.*' => 'required|integer|between:1,7|distinct',
                'local_departure' => 'required|date_format:H:i', 'duration_minutes' => 'required|integer|between:15,720', 'sale_price' => 'required|integer|between:1000,100000000', 'gate' => 'required|string|max:20',
                'status' => ['required', Rule::in(['DRAFT', 'ARCHIVED'])], 'version' => $id ? 'required|integer|min:1' : 'nullable|integer',
            ])->validate();
            if ($old) {
                abort_unless((int) $d['version'] === (int) $old->version, 409, 'Kế hoạch đã thay đổi; tải lại trước khi lưu.');
            }
            unset($d['version']);
            $route = $db->table('routes')->find($d['route_id']) ?? abort(422, 'Đường bay không tồn tại.');
            Access::airport($user, $route->origin);
            $origin = $db->table('airports')->where('code', $route->origin)->first();
            abort_unless($route->active, 409, 'Đường bay đã ngừng khai thác.');
            abort_unless($db->table('aircraft')->where('id', $d['aircraft_id'])->exists(), 422, 'Tàu bay không tồn tại.');
            abort_unless($db->table('airport_resources')->where('airport_code', $route->origin)->where('type', 'GATE')->where('code', $d['gate'])->where('active', true)->exists(), 422, 'Cổng đang hoạt động phải thuộc sân bay đi.');
            abort_if($d['duration_minutes'] < $route->duration_minutes, 422, 'Thời lượng ngắn hơn thời gian bay dự kiến của đường bay.');
            if (! $old) {
                abort_unless($d['from_date'] > now($origin->timezone)->toDateString(), 422, 'Ngày hiệu lực phải sau ngày hiện tại tại sân bay đi.');
            }
            abort_if($db->table('flight_seasons')->where('code', $d['code'])->when($id, fn ($q) => $q->where('id', '!=', $id))->exists(), 409, 'Mã kế hoạch đã tồn tại.');
            sort($d['weekdays']);
            $d['weekdays'] = json_encode(array_map('intval', $d['weekdays']));
            $d['local_departure'] .= ':00';
            if ($old && $db->table('flight_season_occurrences')->where('season_id', $id)->exists()) {
                $normalized = (array) $old;
                $oldDays = json_decode($old->weekdays, true);
                sort($oldDays);
                $normalized['weekdays'] = json_encode(array_map('intval', $oldDays));
                $diff = array_diff_assoc($d, $normalized);
                unset($diff['status']);
                abort_if($diff !== [], 409, 'Kế hoạch đã phát sinh chuyến. Chỉ có thể lưu trữ kế hoạch; hãy tạo kế hoạch mới để đổi quy luật.');
            }
            if ($old) {
                $db->table('flight_seasons')->where('id', $id)->update($d + ['version' => $old->version + 1, 'updated_at' => now()]);
            } else {
                $id = $db->table('flight_seasons')->insertGetId($d + ['created_by' => $user->id, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
            }
            Audit::record($old ? 'season.updated' : 'season.created', (string) $id, $old ? json_encode($old) : null, json_encode($d), $user->id, 'ops');

            return $db->table('flight_seasons')->find($id);
        }, 3);
    }

    public function preview(User $user, int $id, string $from, string $to): array
    {
        $plan = $this->plan($user, $id);
        $db = DB::connection('ops');
        Validator::make(['from' => $from, 'to' => $to], ['from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from'])->validate();
        abort_if(Carbon::parse($from)->diffInDays(Carbon::parse($to)) > 30, 422, 'Mỗi đợt tối đa 31 ngày, gồm cả ngày bắt đầu và kết thúc.');
        abort_unless($from >= $plan->from_date && $to <= $plan->to_date, 422, 'Đợt phát sinh phải nằm trong khoảng hiệu lực của kế hoạch.');
        abort_if($plan->status === 'ARCHIVED', 409, 'Kế hoạch đã được lưu trữ.');
        abort_unless($plan->number && $plan->gate && $plan->aircraft_id, 422, 'Kế hoạch cũ cần bổ sung số hiệu, cổng và tàu bay trước khi phát sinh.');
        $route = $db->table('routes')->find($plan->route_id);
        $airports = $db->table('airports')->whereIn('code', [$route->origin, $route->destination])->get()->keyBy('code');
        $days = json_decode($plan->weekdays, true);
        $rows = [];
        for ($date = Carbon::parse($from); $date->toDateString() <= $to; $date->addDay()) {
            if (! in_array($date->isoWeekday(), $days)) {
                continue;
            }
            $localDate = $date->toDateString();
            $departure = SchedulingDisplay::local($localDate, $plan->local_departure, $airports[$route->origin]->timezone);
            $arrival = $departure->copy()->addMinutes($plan->duration_minutes);
            $owned = $db->table('flight_season_occurrences')->where('season_id', $id)->where('local_date', $localDate)->first();
            $duplicate = $db->table('flights')->where('number', $plan->number)->where('departure', $departure)->first();
            if ($duplicate && in_array($user->role, Access::SCOPED, true) && $duplicate->origin !== $user->airport_code) {
                $duplicate = (object) ['id' => null];
            }
            $candidate = (object) ['number' => $plan->number, 'route_id' => $plan->route_id, 'aircraft_id' => $plan->aircraft_id, 'origin' => $route->origin, 'destination' => $route->destination, 'departure' => $departure->toDateTimeString(), 'arrival' => $arrival->toDateTimeString()];
            $issues = $owned ? [] : app(ScheduleConflicts::class)->inspect($candidate, true, $user);
            $state = $owned ? 'EXISTS' : ($duplicate || collect($issues)->contains('severity', 'blocking') ? 'ERROR' : 'READY');
            $rows[] = ['date' => $localDate, 'departure' => $candidate->departure, 'arrival' => $candidate->arrival, 'origin_zone' => $airports[$route->origin]->timezone, 'destination_zone' => $airports[$route->destination]->timezone, 'status' => $state, 'existing_id' => $owned->flight_id ?? $duplicate->id ?? null, 'issues' => $issues, 'note' => $duplicate && ! $owned ? 'Số hiệu/thời điểm đã tồn tại ngoài kế hoạch này.' : null];
        }
        abort_if(! $rows, 422, 'Khoảng ngày không có ngày hoạt động đã chọn.');

        return ['plan' => $plan, 'rows' => $rows, 'from' => $from, 'to' => $to, 'route' => $route, 'aircraft' => $db->table('aircraft')->find($plan->aircraft_id)];
    }

    public function archive(User $user, int $id, int $version): object
    {
        return DB::connection('ops')->transaction(function () use ($user, $id, $version) {
            $old = $this->plan($user, $id);
            $db = DB::connection('ops');
            $old = $db->table('flight_seasons')->where('id', $id)->lockForUpdate()->first();
            abort_unless((int) $old->version === $version, 409, 'Kế hoạch đã thay đổi; tải lại trước khi lưu trữ.');
            if ($old->status === 'ARCHIVED') {
                return $old;
            }$db->table('flight_seasons')->where('id', $id)->update(['status' => 'ARCHIVED', 'version' => $old->version + 1, 'updated_at' => now()]);
            Audit::record('season.archived', (string) $id, json_encode($old), 'ARCHIVED', $user->id, 'ops');

            return $db->table('flight_seasons')->find($id);
        }, 3);
    }

    public function generate(User $user, int $id, string $from, string $to, int $version): array
    {
        Access::role($user, 'data_admin', 'operations');

        return DB::connection('ops')->transaction(function () use ($user, $id, $from, $to, $version) {
            $db = DB::connection('ops');
            $plan = $this->plan($user, $id);
            $plan = $db->table('flight_seasons')->where('id', $id)->lockForUpdate()->first();
            abort_unless($version === (int) $plan->version, 409, 'Kế hoạch đã đổi từ lúc xem trước.');
            $route = $db->table('routes')->find($plan->route_id);
            $db->table('airports')->where('code', $route->origin)->lockForUpdate()->first();
            $db->table('aircraft')->where('id', $plan->aircraft_id)->lockForUpdate()->first();
            $preview = $this->preview($user, $id, $from, $to);
            abort_if(collect($preview['rows'])->contains('status', 'ERROR'), 409, 'Đợt có dữ liệu hoặc xung đột không hợp lệ; chưa tạo chuyến nào.');
            $created = [];
            $skipped = [];
            foreach ($preview['rows'] as $row) {
                if ($row['status'] === 'EXISTS') {
                    $skipped[] = $row['existing_id'];

                    continue;
                }
                $flight = app(OperationsService::class)->execute($user, 'schedule', ['number' => $plan->number, 'route_id' => $plan->route_id, 'aircraft_id' => $plan->aircraft_id, 'departure' => $row['departure'].' UTC', 'arrival' => $row['arrival'].' UTC', 'sale_price' => $plan->sale_price, 'gate' => $plan->gate]);
                $db->table('flight_season_occurrences')->insert(['season_id' => $id, 'local_date' => $row['date'], 'flight_id' => $flight->id, 'plan_version' => $plan->version, 'source_snapshot' => json_encode($plan), 'created_at' => now()]);
                $created[] = $flight->id;
            }
            if ($created) {
                Audit::record('season.generated', (string) $id, null, json_encode(['created' => $created, 'from' => $from, 'to' => $to]), $user->id, 'ops');
            }

            return compact('created', 'skipped');
        }, 3);
    }

    public function batch(User $user, string $action, array $ids, bool $apply): array
    {
        abort_unless(in_array($action, ['submit', 'publish'], true), 422);
        Access::role($user, $action === 'submit' ? 'operations' : 'ops_manager');
        $ids = Validator::make(['ids' => $ids], ['ids' => 'required|array|min:1|max:31', 'ids.*' => 'required|integer|min:1|distinct'])->validate()['ids'];
        sort($ids);
        $rows = [];
        $pending = [];
        foreach ($ids as $id) {
            $f = $this->flight($user, (int) $id);
            $already = $action === 'publish' ? (bool) $f->published_at : ($f->status === 'PENDING' || (bool) $f->published_at);
            $issues = $already ? [] : app(ScheduleConflicts::class)->inspect($f, true, $user);
            if (! $already && $f->status !== ($action === 'submit' ? 'DRAFT' : 'PENDING')) {
                $issues[] = ['severity' => 'blocking', 'reason' => 'Trạng thái lịch không phù hợp thao tác.'];
            }
            if (! $already && $action === 'publish' && (int) $f->created_by === (int) $user->id) {
                $issues[] = ['severity' => 'blocking', 'reason' => 'Người tạo không được tự phê duyệt và công bố.'];
            }
            $rows[] = ['flight' => $f, 'already' => $already, 'issues' => $issues, 'result' => $already ? 'Đã xử lý trước đó' : 'Chưa áp dụng'];
            if (! $already) {
                $pending[] = $id;
            }
        }
        if (! $apply || collect($rows)->contains(fn ($r) => collect($r['issues'])->contains('severity', 'blocking'))) {
            return ['rows' => $rows, 'applied' => false, 'message' => 'Kết quả kiểm tra; chưa thay đổi lịch.'];
        }
        if (! $pending) {
            return ['rows' => $rows, 'applied' => true, 'message' => 'Các chuyến đã được xử lý trước đó; không công bố hoặc trình lại.'];
        }
        try {
            app(OperationsService::class)->execute($user, $action.'_batch', ['flight_ids' => implode(',', $pending)]);
        } catch (HttpExceptionInterface $error) {
            if (! in_array($error->getStatusCode(), [409, 422], true)) {
                throw $error;
            }
            foreach ($rows as &$row) {
                $row['result'] = $row['already'] ? 'Đã xử lý trước đó' : 'Không áp dụng: cả đợt đã hoàn tác. '.$error->getMessage();
            }

            return ['rows' => $rows, 'applied' => false, 'message' => 'Không có chuyến nào trong đợt được cập nhật.'];
        }
        if ($action === 'publish') {
            app(OutboxRelay::class)->drain();
        }
        foreach ($rows as &$row) {
            $row['result'] = $row['already'] ? 'Đã xử lý trước đó' : ($action === 'submit' ? 'Đã gửi phê duyệt' : 'Đã phê duyệt và công bố');
        }

        return ['rows' => $rows, 'applied' => true, 'message' => 'Đã hoàn tất cả đợt.'];
    }

    public function proposeEdit(User $user, int $id, array $input): int
    {
        Access::role($user, 'operations');

        return DB::connection('ops')->transaction(function () use ($user, $id, $input) {
            $db = DB::connection('ops');
            $f = $this->flight($user, $id);
            $f = $db->table('flights')->where('id', $id)->lockForUpdate()->first();
            abort_unless(! $f->published_at && in_array($f->status, ['DRAFT', 'PENDING'], true), 409, 'Lịch đã công bố cần đi qua quy trình điều hành gián đoạn M03.');
            $data = Validator::make($input, ['version' => 'required|integer|min:1', 'aircraft_id' => 'required|integer', 'departure' => 'required|date_format:Y-m-d\TH:i', 'arrival' => 'required|date_format:Y-m-d\TH:i', 'sale_price' => 'required|integer|between:1000,100000000', 'gate' => 'required|string|max:20', 'reason' => 'required|string|min:5|max:255'])->validate();
            abort_unless((int) $data['version'] === (int) $f->version, 409, 'Lịch đã thay đổi từ lúc mở biểu mẫu; tải lại trước khi đề nghị sửa.');
            unset($data['version']);
            $zones = $db->table('airports')->whereIn('code', [$f->origin, $f->destination])->pluck('timezone', 'code');
            foreach (['departure' => $f->origin, 'arrival' => $f->destination] as $field => $airport) {
                [$date,$time] = explode('T', $data[$field]);
                $data[$field] = SchedulingDisplay::local($date, $time, $zones[$airport])->toDateTimeString();
            }
            $reason = $data['reason'];
            unset($data['reason']);
            $candidate = (object) array_merge((array) $f, $data);
            abort_if(array_diff_assoc($data, (array) $f) === [], 422, 'Chưa có nội dung thay đổi.');
            $this->validateCandidate($candidate);
            $change = $db->table('flight_schedule_changes')->insertGetId(['flight_id' => $id, 'source_version' => $f->version, 'before_data' => json_encode($f), 'after_data' => json_encode($data), 'reason' => $reason, 'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
            Audit::record('schedule.edit.proposed', (string) $id, json_encode($f), json_encode($data), $user->id, 'ops');

            return $change;
        }, 3);
    }

    public function approveEdit(User $user, int $id): void
    {
        Access::role($user, 'ops_manager');
        DB::connection('ops')->transaction(function () use ($user, $id) {
            $db = DB::connection('ops');
            $change = $db->table('flight_schedule_changes')->where('id', $id)->lockForUpdate()->first() ?? abort(404);
            $f = $this->flight($user, $change->flight_id);
            $data = json_decode($change->after_data, true);
            $db->table('airports')->where('code', $f->origin)->lockForUpdate()->first();
            foreach (collect([$f->aircraft_id, $data['aircraft_id']])->unique()->sort() as $plane) {
                $db->table('aircraft')->where('id', $plane)->lockForUpdate()->first();
            }
            $f = $db->table('flights')->where('id', $f->id)->lockForUpdate()->first();
            abort_unless($change->status === 'PENDING' && (int) $change->created_by !== (int) $user->id, 409, 'Cần người duyệt độc lập.');
            abort_unless(! $f->published_at && in_array($f->status, ['DRAFT', 'PENDING'], true) && (int) $f->version === (int) $change->source_version && (array) $f === json_decode($change->before_data, true), 409, 'Lịch nguồn đã thay đổi; cần đề nghị mới.');
            $this->validateCandidate((object) array_merge((array) $f, $data));
            abort_if($db->table('resource_allocations')->where('flight_id', $f->id)->exists(), 409, 'Chuyến có lịch hạ tầng; cần xử lý theo quy trình điều hành hiện có.');
            $db->table('flights')->where('id', $f->id)->update($data + ['version' => $f->version + 1, 'status' => 'DRAFT']);
            $db->table('flight_schedule_changes')->where('id', $id)->update(['status' => 'APPROVED', 'approved_by' => $user->id, 'updated_at' => now()]);
            Audit::record('schedule.edit.approved', (string) $f->id, json_encode($f), json_encode($data), $user->id, 'ops');
        }, 3);
    }
}
