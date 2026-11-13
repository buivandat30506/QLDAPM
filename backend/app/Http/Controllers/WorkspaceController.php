<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Modules\Baggage\BaggageService;
use App\Modules\Finance\OutboxRelay;
use App\Modules\Operations\FlightChangeService;
use App\Modules\Operations\MasterDataService;
use App\Modules\Operations\MasterFileService;
use App\Modules\Operations\OperationsService;
use App\Modules\Shared\Access;
use App\Modules\Shared\UseCaseService;
use App\Support\WorkspaceFlow;
use App\Support\WorkspaceForms;
use App\Support\WorkspacePresentation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class WorkspaceController extends Controller
{
    public static function sections(): array
    {
        return [
            'catalog' => ['Danh mục sân bay & đường bay', ['data_admin']],
            'scheduling' => ['Lịch bay & điều hành', ['operations', 'ops_manager']],
            'fleet' => ['Đội tàu bay', ['fleet_manager']],
            'maintenance' => ['Bảo dưỡng', ['maintenance']],
            'recovery' => ['Đổi tàu & tổ bay', ['operations', 'ops_manager', 'crew_scheduler']],
            'crew' => ['Tổ bay', ['crew_scheduler']],
            'baggage' => ['Hành lý', ['airport_agent']],
            'ground' => ['Dịch vụ mặt đất', ['ground_agent', 'operations']],
            'customer' => ['Yêu cầu hỗ trợ', ['passenger', 'customer_service']],
            'finance' => ['Hoàn tiền & đối soát', ['finance']],
            'analytics' => ['Phân tích khai thác', ['analyst', 'operations', 'ops_manager']],
            'iot' => ['Thiết bị & cảnh báo', ['integration_admin']],
            'fare' => ['Giá vé & số chỗ', ['data_admin']],
            'resources' => ['Lịch sử dụng hạ tầng', ['operations']],
            'disruptions' => ['Xử lý gián đoạn', ['operations', 'ops_manager']],
            'notifications' => ['Thông báo', Access::ROLES],
            'audit' => ['Nhật ký kiểm toán', ['admin', 'auditor']],
            'integrations' => ['Giám sát kết nối', ['admin', 'integration_admin']],
        ] + BusinessController::AREAS;
    }

    private function scopedFlights(User $u)
    {
        $q = DB::connection('ops')->table('flights');
        if (in_array($u->role, Access::SCOPED)) {
            $q->where('origin', $u->airport_code);
        }

        return $q;
    }

    public function index(Request $r, string $section = 'scheduling')
    {
        if (! $r->expectsJson() && $section === 'scheduling' && $r->query('flow') === 'monitoring') {
            return app(FlightOperationsController::class)->index($r);
        }
        if (! $r->expectsJson() && $section === 'catalog' && $r->query('flow') === 'routes') {
            return app(RouteManagementController::class)->index($r);
        }
        if (! $r->expectsJson() && $section === 'scheduling' && $r->query('flow', 'planning') === 'planning') {
            return app(SchedulePlanningController::class)->index($r);
        }
        if ($section === 'catalog' && in_array($r->query('flow', 'airports'), ['airports', 'infrastructure'], true)) {
            return app(AirportController::class)->index($r);
        }
        if (isset(BusinessController::AREAS[$section])) {
            return app(BusinessController::class)->index($r, $section);
        }
        $meta = self::sections()[$section] ?? abort(404);
        $u = $r->user();
        $isAdmin = Access::isAdmin($u);
        Access::role($u, ...$meta[1]);
        $r->validate(['q' => 'nullable|string|max:100', 'page' => 'nullable|integer|min:1']);
        $flow = null;
        if ($r->has('flow')) {
            $r->validate(['flow' => 'required|string']);
            $flow = WorkspaceFlow::definitions($section)[$r->query('flow')] ?? abort(404);
        }
        $ops = DB::connection('ops');
        $tables = [];
        $forms = [];
        $kpis = [];
        // Form specifications are shared by Blade and the first-party REST workspace.
        $f = fn (string $name, string $label, string $type = 'text', array $options = []) => compact('name', 'label', 'type', 'options');
        $form = function (string $action, string $title, array $fields) use (&$forms, $flow) {
            if (! WorkspaceFlow::includesForm($flow, $action, $fields)) {
                return;
            }
            $forms[] = compact('action', 'title', 'fields');
        };
        $dataset = function (string $title, $query, array $columns) use (&$tables, $r, $flow) {
            if (isset($flow['tables']) && ! in_array($title, $flow['tables'], true)) {
                return;
            }
            $searchable = array_intersect(array_keys($columns), ['number', 'registration', 'name', 'code', 'tag', 'title', 'subject', 'reference', 'reason', 'body']);
            if ($r->filled('q') && $searchable) {
                $query->where(function ($q) use ($searchable, $r) {
                    foreach ($searchable as $column) {
                        $q->orWhere($column, 'like', '%'.addcslashes($r->query('q'), '\\%_').'%');
                    }
                });
            }
            $page = count($tables) === 0 ? 'page' : 'page_'.(count($tables) + 1);
            $tables[] = ['title' => $title, 'rows' => $query->orderByDesc(array_key_first($columns))->paginate(12, ['*'], $page)->withQueryString(), 'columns' => $columns];
        };
        $airports = $section === 'catalog' ? $ops->table('airports')->get()->mapWithKeys(fn ($a) => [$a->code => $a->code.' · '.$a->name])->all() : [];
        $flights = in_array($section, ['scheduling', 'crew', 'ground', 'resources', 'disruptions', 'analytics', 'recovery']) ? $this->scopedFlights($u)->where('departure', '>', now()->subDays(2))->orderByDesc('id')->limit(100)->get()->mapWithKeys(fn ($x) => [$x->id => $x->number.' · '.$x->origin.'→'.$x->destination.' · '.$x->status])->all() : [];
        if ($section === 'catalog') {
            $dataset('Sân bay', $ops->table('airports'), ['code' => 'Mã', 'name' => 'Tên sân bay', 'city' => 'Thành phố', 'timezone' => 'Múi giờ']);
            $dataset('Đường bay', $ops->table('routes'), ['id' => 'ID', 'origin' => 'Đi', 'destination' => 'Đến', 'duration_minutes' => 'Phút chuẩn', 'distance_km' => 'Km', 'active' => 'Khả dụng']);
            $dataset('Tài nguyên sân bay', $ops->table('airport_resources'), ['id' => 'ID', 'airport_code' => 'Sân bay', 'code' => 'Mã', 'type' => 'Loại', 'active' => 'Khả dụng']);
            $form('airport', 'Thêm sân bay', [$f('code', 'Mã IATA (3 chữ hoa)'), $f('name', 'Tên sân bay'), $f('city', 'Thành phố'), $f('timezone', 'Múi giờ IANA')]);
            $form('route', 'Thêm đường bay', [$f('origin', 'Đi', 'select', $airports), $f('destination', 'Đến', 'select', $airports), $f('duration_minutes', 'Thời gian chuẩn (phút)', 'number'), $f('distance_km', 'Khoảng cách (km)', 'number')]);
            $form('resource', 'Thêm tài nguyên', [$f('airport_code', 'Sân bay', 'select', $airports), $f('code', 'Mã tài nguyên'), $f('type', 'Loại', 'select', ['TERMINAL' => 'Nhà ga', 'GATE' => 'Cửa', 'STAND' => 'Vị trí đỗ', 'COUNTER' => 'Quầy'])]);
        }

        if ($section === 'scheduling') {
            $dataset('Lịch và trạng thái khai thác', $this->scopedFlights($u), ['id' => 'ID', 'number' => 'Số hiệu', 'origin' => 'Đi', 'destination' => 'Đến', 'departure' => 'Khởi hành UTC+7', 'arrival' => 'Đến UTC+7', 'aircraft_id' => 'Tàu bay', 'status' => 'Trạng thái', 'gate' => 'Cửa', 'version' => 'Phiên bản']);
            if ($u->role === 'operations' || $isAdmin) {
                $routeQuery = $ops->table('routes')->where('active', true);
                if (! $isAdmin) {
                    $routeQuery->where('origin', $u->airport_code);
                }
                $routes = $routeQuery->get()->mapWithKeys(fn ($x) => [$x->id => $x->origin.'→'.$x->destination])->all();
                $planes = $ops->table('aircraft')->where('status', 'AVAILABLE')->pluck('registration', 'id')->all();
                $form('schedule', 'Lập lịch nháp', [$f('number', 'Số hiệu (ví dụ AS201)'), $f('route_id', 'Đường bay', 'select', $routes), $f('aircraft_id', 'Tàu bay', 'select', $planes), $f('departure', 'Khởi hành (giờ Việt Nam)', 'datetime-local'), $f('arrival', 'Hạ cánh (giờ Việt Nam)', 'datetime-local'), $f('sale_price', 'Giá VND', 'number'), $f('gate', 'Cửa dự kiến')]);
                $form('season', 'Lập lịch lặp theo kỳ (mỗi đợt tối đa 31 ngày)', [$f('number', 'Số hiệu chuyến'), $f('route_id', 'Đường bay', 'select', $routes), $f('aircraft_id', 'Tàu bay', 'select', $planes), $f('from_date', 'Ngày bắt đầu', 'date'), $f('to_date', 'Ngày kết thúc', 'date'), $f('weekdays', 'Ngày trong tuần: 1=Thứ hai…7=Chủ nhật (ví dụ 1,3,5)'), $f('local_departure', 'Giờ địa phương tại sân bay đi', 'time'), $f('duration_minutes', 'Thời lượng phút', 'number'), $f('sale_price', 'Giá VND', 'number'), $f('gate', 'Cửa dự kiến')]);
                $form('submit_batch', 'Trình duyệt nhiều lịch', [$f('flight_ids', 'Danh sách ID cách nhau dấu phẩy (tối đa 31)')]);
                $form('submit', 'Trình duyệt lịch', [$f('flight_id', 'Chuyến nháp', 'select', $flights)]);
                $form('flight_state', 'Cập nhật khai thác', [$f('flight_id', 'Chuyến', 'select', $flights), $f('status', 'Trạng thái mới', 'select', ['BOARDING' => 'Đang đón khách', 'GATE_CLOSED' => 'Đã đóng cửa', 'DEPARTED' => 'Đã khởi hành', 'ARRIVED' => 'Đã hạ cánh']), $f('reason', 'Lý do')]);
            }
            if ($u->role === 'ops_manager' || $isAdmin) {
                $form('publish', 'Phê duyệt và công bố', [$f('flight_id', 'Chuyến chờ duyệt', 'select', $flights)]);
            }
        }
        if ($section === 'scheduling' && ($u->role === 'ops_manager' || $isAdmin)) {
            $form('publish_batch', 'Duyệt và công bố cả đợt', [$f('flight_ids', 'Danh sách ID cách nhau dấu phẩy (tối đa 31)')]);
        }

        if ($section === 'resources') {
            $dataset('Lịch sử dụng theo chuyến bay', $ops->table('resource_allocations')->whereIn('flight_id', array_keys($flights)), ['id' => 'ID', 'flight_id' => 'Chuyến', 'resource_id' => 'Hạng mục hạ tầng', 'starts_at' => 'Bắt đầu', 'ends_at' => 'Kết thúc', 'reason' => 'Lý do']);
            $resourceQuery = $ops->table('airport_resources')->where('active', true);
            if (! $isAdmin) {
                $resourceQuery->where('airport_code', $u->airport_code);
            }
            $form('allocate', 'Xếp lịch sử dụng', [$f('flight_id', 'Chuyến', 'select', $flights), $f('resource_id', 'Hạng mục hạ tầng', 'select', $resourceQuery->pluck('code', 'id')->all()), $f('starts_at', 'Bắt đầu (giờ Việt Nam)', 'datetime-local'), $f('ends_at', 'Kết thúc (giờ Việt Nam)', 'datetime-local'), $f('reason', 'Lý do')]);
            $form('unallocate', 'Gỡ lịch sử dụng', [$f('allocation_id', 'Lịch sử dụng cần gỡ', 'number'), $f('reason', 'Lý do')]);
        }

        if ($section === 'disruptions') {
            $dataset('Phương án xử lý gián đoạn', $ops->table('disruptions')->whereIn('flight_id', array_keys($flights)), ['id' => 'ID', 'flight_id' => 'Chuyến', 'type' => 'Loại', 'reason' => 'Nguyên nhân', 'delay_minutes' => 'Phút trễ', 'status' => 'Phê duyệt', 'created_by' => 'Người lập', 'approved_by' => 'Người duyệt']);
            if ($u->role === 'operations' || $isAdmin) {
                $form('disruption', 'Lập phương án', [$f('flight_id', 'Chuyến', 'select', $flights), $f('type', 'Loại', 'select', ['DELAY' => 'Trễ chuyến', 'CANCEL' => 'Hủy chuyến']), $f('delay_minutes', 'Số phút trễ (hủy nhập 0)', 'number'), $f('reason', 'Lý do')]);
            }
            if ($u->role === 'ops_manager' || $isAdmin) {
                $form('approve_disruption', 'Phê duyệt — đánh giá tác động trước khi xác nhận', [$f('disruption_id', 'ID phương án', 'number')]);
            }
        }
        if ($section === 'recovery') {
            $q = $ops->table('flight_changes')->whereIn('flight_id', array_keys($flights));
            $dataset('Phương án đổi nguồn lực', $q, ['id' => 'Mã phương án', 'flight_id' => 'Chuyến', 'kind' => 'Loại', 'status' => 'Tiến độ', 'reason' => 'Lý do', 'failure_reason' => 'Kết quả kiểm tra', 'created_by' => 'Người lập', 'approved_by' => 'Người duyệt']);
            if ($u->role === 'operations' || $isAdmin) {
                $form('resource_propose', 'Đề nghị đổi tàu bay', [$f('kind', '', 'hidden', ['value' => 'AIRCRAFT']), $f('flight_id', 'Chuyến', 'select', $flights), $f('target_aircraft_id', 'Tàu mới', 'select', $ops->table('aircraft')->where('status', 'AVAILABLE')->get()->mapWithKeys(fn ($a) => [$a->id => $a->registration.' · '.$a->type.' · '.$a->capacity.' ghế'])->all()), $f('reason', 'Lý do đổi tàu')]);
            }
            if ($u->role === 'crew_scheduler' || $isAdmin) {
                $dataset('Nhân sự và năng định', $ops->table('crew_members'), ['id' => 'ID nhân sự', 'name' => 'Họ tên', 'position' => 'Vị trí', 'qualification' => 'Năng định', 'license_expires' => 'Hạn giấy phép', 'available' => 'Khả dụng']);
                $form('resource_propose', 'Đề nghị thay tổ bay', [$f('kind', '', 'hidden', ['value' => 'CREW']), $f('flight_id', 'Chuyến', 'select', $flights), $f('crew_ids', 'Toàn bộ ID tổ bay mới, cách dấu phẩy'), $f('reason', 'Lý do thay thế')]);
            }
            if ($u->role === 'ops_manager' || $isAdmin) {
                $form('resource_decide', 'Phê duyệt đổi nguồn lực', [$f('change_id', 'Phương án', 'select', $ops->table('flight_changes')->whereIn('flight_id', array_keys($flights))->where('status', 'PENDING')->get()->mapWithKeys(fn ($p) => [$p->id => $p->kind.' · chuyến '.$p->flight_id.' · '.$p->reason])->all()), $f('approve', 'Quyết định', 'select', [1 => 'Chấp thuận', 0 => 'Từ chối'])]);
            }
        }

        if ($section === 'notifications') {
            $dataset('Hộp thư cá nhân', DB::table('notifications')->where('user_id', $u->id), ['id' => 'ID', 'title' => 'Tiêu đề', 'body' => 'Nội dung', 'created_at' => 'Gửi UTC+7', 'read_at' => 'Đã đọc UTC+7']);
            $form('read_notice', 'Đánh dấu đã đọc', [$f('notification_id', 'ID thông báo')]);
        }
        if ($section === 'audit') {
            foreach (['ops' => 'Khai thác', 'mysql' => 'Khách hàng', 'finance' => 'Tài chính'] as $node => $label) {
                $dataset($label, DB::connection($node)->table('audit_logs'), ['id' => 'ID', 'actor_id' => 'Người thực hiện', 'action' => 'Hành động', 'object_id' => 'Đối tượng', 'before_state' => 'Trước', 'after_state' => 'Sau', 'correlation_id' => 'Tương quan', 'created_at' => 'Thời gian UTC+7']);
            }
        }
        if ($section === 'integrations') {
            foreach (['ops' => 'Khai thác → Đặt chỗ', 'mysql' => 'Đặt chỗ → Tài chính', 'finance' => 'Tài chính → Đặt chỗ'] as $node => $label) {
                $dataset($label, DB::connection($node)->table('outbox'), ['id' => 'Event ID', 'type' => 'Loại', 'attempts' => 'Số lần thử', 'available_at' => 'Thử lại UTC+7', 'delivered_at' => 'Đã giao UTC+7']);
            }
        }
        if ($section === 'catalog') {
            $entities = $section === 'catalog' ? ['airports' => 'Sân bay', 'routes' => 'Đường bay', 'airport_resources' => 'Tài nguyên'] : ($section === 'fleet' ? ['aircraft' => 'Tàu bay'] : ['crew_members' => 'Tổ bay']);
            if (isset($flow['entity'])) {
                $entities = array_intersect_key($entities, [$flow['entity'] => true]);
            }
            $dataset('Phiên bản dữ liệu / chờ duyệt', $ops->table('master_changes')->whereIn('entity', array_keys($entities)), ['id' => 'ID', 'entity' => 'Danh mục', 'record_id' => 'Bản ghi', 'status' => 'Trạng thái', 'reason' => 'Lý do', 'after_data' => 'Thay đổi', 'created_by' => 'Người lập', 'approved_by' => 'Người duyệt']);
            foreach ($entities as $entity => $label) {
                $fields = [$f('entity', 'Danh mục', 'hidden', ['value' => $entity]), $f('record_id', 'ID / mã cần sửa')];
                $fields = array_merge($fields, match ($entity) {
                    'airports' => [$f('changes[name]', 'Tên sân bay'), $f('changes[city]', 'Thành phố'), $f('changes[timezone]', 'Múi giờ IANA')],
                    'routes' => [$f('changes[active]', 'Khả dụng', 'select', [1 => 'Hoạt động', 0 => 'Ngừng sử dụng']), $f('changes[duration_minutes]', 'Thời gian chuẩn (phút)', 'number'), $f('changes[distance_km]', 'Khoảng cách km', 'number')],
                    'airport_resources' => [$f('changes[active]', 'Khả dụng', 'select', [1 => 'Hoạt động', 0 => 'Ngừng sử dụng'])],
                    'aircraft' => [$f('changes[type]', 'Loại tàu'), $f('changes[capacity]', 'Số ghế', 'number')],
                    'crew_members' => [$f('changes[qualification]', 'Năng định'), $f('changes[license_expires]', 'Hết hạn giấy phép', 'date'), $f('changes[available]', 'Khả dụng', 'select', [1 => 'Sẵn sàng', 0 => 'Nghỉ/không khả dụng'])],
                });
                $fields[] = $f('reason', 'Lý do sửa đổi');
                $form('master_propose', 'Đề nghị sửa '.$label, $fields);
            }
            $form('master_approve', 'Duyệt phiên bản dữ liệu — độc lập', [$f('change_id', 'ID đề nghị', 'number')]);
        }
        if ($r->expectsJson()) {
            return response()->json(['section' => $section, 'title' => $flow['title'] ?? $meta[0], 'tables' => $tables, 'kpis' => $kpis]);
        }
        if ($section === 'notifications') {
            return view('notifications', [
                'notices' => $tables[0]['rows'],
                'unreadCount' => DB::table('notifications')->where('user_id', $u->id)->whereNull('read_at')->count(),
            ]);
        }
        $forms = app(WorkspaceForms::class)->prepare($forms, $u, array_keys($entities ?? []));
        $presentation = new WorkspacePresentation($tables);
        if (! in_array($section, ['audit', 'integrations'])) {
            foreach ($tables as &$table) {
                $table['columns'] = array_diff_key($table['columns'], array_flip(['id', 'version', 'source_version', 'correlation_id']));
            }
            unset($table);
        }

        $transfers = $payouts = collect();

        return view('workspace', ['section' => $section, 'title' => $flow['title'] ?? $meta[0], 'flow' => $flow, 'tables' => $tables, 'forms' => $forms, 'kpis' => $kpis, 'sections' => self::sections(), 'presentation' => $presentation, 'transfers' => $transfers, 'payouts' => $payouts]);
    }

    public function dispatch(Request $r, string $action)
    {
        $u = $r->user();
        $d = $r->all();
        if (! $r->expectsJson()) {
            foreach (['crew_ids', 'flight_ids'] as $key) {
                if (isset($d[$key]) && is_array($d[$key])) {
                    Validator::make([$key => $d[$key]], [$key => 'required|array|min:1|max:31', $key.'.*' => 'required|integer|min:1|distinct'])->validate();
                    $d[$key] = implode(',', $d[$key]);
                }
            }
        }
        $arrayActions = ['master_propose', 'airport_update', 'season_rule', 'airport_area', 'aircraft_model', 'fare_rule_version', 'baggage_allowance', 'ancillary', 'notification_template', 'customer_interaction', 'system_config', 'dataset', 'dataset_clean', 'model_run', 'model_train', 'model_evaluate', 'recommendation', 'fee_catalog', 'integration_connection'];
        $arrayKeys = ['changes', 'weekdays', 'cabin_layout', 'rules', 'metadata', 'operating_hours', 'conditions', 'schema_definition', 'records', 'metrics', 'explanation', 'configuration'];
        foreach ($d as $key => $value) {
            abort_if(! is_scalar($value) && $value !== null && ! (in_array($action, $arrayActions, true) && in_array($key, $arrayKeys, true)), 422, 'D? li?u kh?ng h?p l?.');
        }
        if ($action === 'master_propose' && isset($d['changes_json'])) {
            try {
                $d['changes'] = json_decode($d['changes_json'], true, 32, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                abort(422, 'JSON thay đổi không hợp lệ.');
            }
        }
        if ($action === 'dataset_clean' && isset($d['records']) && is_string($d['records'])) {
            try {
                $d['records'] = json_decode($d['records'], true, 32, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                abort(422, 'JSON bản ghi dữ liệu không hợp lệ.');
            }
        }
        foreach (['departure', 'arrival', 'due_at', 'starts_at', 'ends_at', 'event_time'] as $key) {
            if (! $r->expectsJson() && ! empty($d[$key]) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $d[$key])) {
                $d[$key] = Carbon::parse($d[$key], 'Asia/Ho_Chi_Minh')->utc()->toIso8601String();
            }
        }
        $result = match ($action) {
            'master_propose' => app(MasterDataService::class)->propose($u, $d),
            'master_approve' => app(MasterDataService::class)->approve($u, (int) ($d['change_id'] ?? 0)),
            'resource_propose' => app(FlightChangeService::class)->propose($u, $d),
            'resource_decide' => app(FlightChangeService::class)->decide($u, $d),
            'read_notice' => $this->readNotice($u, $d),
            'read_all_notices' => $this->readAllNotices($u),
            'airport_update', 'airport_area', 'season_rule', 'actual_time' => app(UseCaseService::class)->execute($u, $action, $d),
            default => app(OperationsService::class)->execute($u, $action, $d),
        };
        if (in_array($action, ['publish', 'publish_batch', 'flight_state', 'allocate', 'approve_disruption', 'refund_request', 'refund_decide', 'exchange_decide', 'resource_decide'])) {
            app(OutboxRelay::class)->drain();
        }

        return $r->expectsJson() ? response()->json($result) : back()->with('message', 'Đã ghi nhận thao tác.')->with('result', $action === 'forecast' ? $result : null);
    }

    private function readNotice(User $u, array $d): object
    {
        Validator::make($d, ['notification_id' => 'required|uuid'])->validate();
        abort_unless(DB::table('notifications')->where('id', $d['notification_id'])->where('user_id', $u->id)->exists(), 404);
        DB::table('notifications')->where('id', $d['notification_id'])->update(['read_at' => now()]);

        return (object) ['status' => 'READ'];
    }

    private function readAllNotices(User $u): object
    {
        $count = DB::table('notifications')
            ->where('user_id', $u->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return (object) ['status' => 'READ', 'count' => $count];
    }

    public function importMaster(Request $r)
    {
        Access::role($r->user(), 'data_admin');
        $r->validate(['file' => 'required|file|mimes:csv,txt|max:1024']);
        $count = app(MasterFileService::class)->importAirports($r->user(), $r->file('file')->getRealPath());

        return $r->expectsJson() ? response()->json(['staged' => $count]) : back()->with('message', 'Đã nhập '.$count.' sân bay vào hàng chờ duyệt. Dữ liệu chưa có hiệu lực.');
    }

    public function exportMaster(Request $r, string $entity)
    {
        return app(MasterFileService::class)->export($r->user(), $entity);
    }

    public function conversation(Request $r, int $id)
    {
        Access::role($r->user(), 'passenger', 'customer_service');

        return view('scope-empty', ['title' => 'Yêu cầu hỗ trợ']);
    }

    public function recovery(Request $r, string $id)
    {
        $change = app(FlightChangeService::class)->detail($r->user(), $id);

        return $r->expectsJson() ? response()->json($change) : view('flight-change', compact('change'));
    }

    public function trace(Request $r, int $id)
    {
        $bag = app(BaggageService::class)->trace($r->user(), $id);

        return $r->expectsJson() ? response()->json($bag) : view('baggage-trace', compact('bag'));
    }

    public function export(Request $r, int $id)
    {
        Access::role($r->user(), 'finance');

        return view('scope-empty', ['title' => 'Đối soát doanh thu']);
    }
}
