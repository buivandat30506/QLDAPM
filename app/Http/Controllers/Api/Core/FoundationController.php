<?php

namespace App\Http\Controllers\Api\Core;

use App\Http\Controllers\Api\BaseApiController;
use App\Services\Core\MasterDataQuality;
use App\Services\Core\MasterDataService;
use App\Services\Core\MasterDataTransfer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FoundationController extends BaseApiController
{
    public function show(int $id)
    {
        $record = DB::table('master_data_records')->find($id);
        abort_unless($record, 404);

        return $this->successResponse(['record' => $record, 'quality_errors' => app(MasterDataQuality::class)->errors([
            'category' => $record->category, 'code' => $record->code, 'payload' => json_decode($record->payload, true)], $id)]);
    }

    public function published(int $id)
    {
        return $this->successResponse(app(MasterDataService::class)->published($id));
    }

    public function preview(Request $r)
    {
        $r->validate(['file' => 'required|file|max:1024']);

        return $this->successResponse(app(MasterDataTransfer::class)->preview($r->file('file')), 'Đã kiểm tra, chưa nhập danh mục.', 201);
    }

    public function batch(string $batch)
    {
        $record = app(MasterDataTransfer::class)->batch($batch);

        return $this->successResponse(['batch' => $record, 'rows' => DB::table('master_import_rows')->where('batch_id', $batch)->orderBy('line')->paginate(25)]);
    }

    public function commit(string $batch)
    {
        return $this->successResponse(app(MasterDataTransfer::class)->commit($batch));
    }

    public function export(Request $r)
    {
        return app(MasterDataTransfer::class)->export($r->query());
    }
}
