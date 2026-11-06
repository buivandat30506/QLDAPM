<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Core\MasterDataQuality;
use App\Services\Core\MasterDataService;
use App\Services\Core\MasterDataTransfer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class MasterDataController extends Controller
{
    public function __construct(private MasterDataService $masters, private MasterDataTransfer $transfer) {}

    public function index(Request $request)
    {
        Gate::authorize('master.read');
        $records = $this->masters->query($request->query())->paginate((int) $request->input('per_page', 25))->withQueryString();
        $batches = DB::table('master_import_batches')->orderByDesc('created_at')->paginate(10, ['*'], 'batch_page')->withQueryString();

        return view('foundation.master-index', compact('records', 'batches'));
    }

    public function show(int $id)
    {
        Gate::authorize('master.read');
        $record = DB::table('master_data_records')->find($id);
        abort_unless($record, 404);
        $payload = json_decode($record->payload, true);
        $qualityErrors = app(MasterDataQuality::class)->errors(['category' => $record->category, 'code' => $record->code, 'payload' => $payload], $id);
        $versions = DB::table('entity_versions')->where('entity_type', 'master_data_records')->where('entity_id', (string) $id)->orderByDesc('version')->get();
        $history = DB::table('audit_logs')->where('entity_type', 'master_data_records')->where('entity_id', (string) $id)->orderByDesc('id')->paginate(20);

        return view('foundation.master-show', compact('record', 'payload', 'qualityErrors', 'versions', 'history'));
    }

    public function save(Request $request, ?int $id = null)
    {
        Gate::authorize('master.write');
        $input = $request->validate(['category' => 'required|string', 'code' => 'required|string', 'payload_json' => 'required|json', 'expected_revision' => 'nullable|integer|min:0']);
        $input['payload'] = json_decode($input['payload_json'], true);
        $record = $this->masters->save($input, $id);

        return redirect()->route('admin.master.show', $record->id)->with('success', 'Đã lưu bản nháp. Kiểm tra chất lượng trước khi gửi duyệt.');
    }

    public function action(Request $request, int $id, string $action)
    {
        Gate::authorize(in_array($action, ['approve', 'publish', 'reject']) ? 'master.approve' : 'master.write');
        $this->masters->transition($id, $action, $request->all());

        return redirect()->route('admin.master.show', $id)->with('success', 'Đã cập nhật trạng thái và lưu lịch sử.');
    }

    public function preview(Request $request)
    {
        Gate::authorize('master.write');
        $request->validate(['file' => 'required|file|max:1024']);
        $batch = $this->transfer->preview($request->file('file'));

        return redirect()->route('admin.master.batch', $batch->id)->with('success', 'Đã kiểm tra tệp. Chưa nhập vào danh mục.');
    }

    public function batch(Request $request, string $batch)
    {
        $batch = $this->transfer->batch($batch);
        $rows = DB::table('master_import_rows')->where('batch_id', $batch->id)->orderBy('line')->paginate(25);

        return view('foundation.import-show', compact('batch', 'rows'));
    }

    public function commit(string $batch)
    {
        $this->transfer->commit($batch);

        return redirect()->route('admin.master.batch', $batch)->with('success', 'Đã nhập toàn bộ lô vào bản nháp. Từng bản ghi cần duyệt/công bố.');
    }

    public function export(Request $request)
    {
        return $this->transfer->export($request->query());
    }

    public function template()
    {
        Gate::authorize('master.read');

        return response()->streamDownload(function () {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['category', 'code', 'payload'], ',', '"', '');
            fputcsv($stream, ['SERVICE', 'MEAL_SAMPLE', '{"name":"Suất ăn mẫu","timezone":"Asia/Ho_Chi_Minh"}'], ',', '"', '');
            fclose($stream);
        }, 'master-import-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
