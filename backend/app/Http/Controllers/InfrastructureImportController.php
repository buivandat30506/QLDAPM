<?php

namespace App\Http\Controllers;

use App\Modules\Operations\InfrastructureImport;
use App\Support\AirportWorkspace;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InfrastructureImportController extends Controller
{
    public function form(Request $request)
    {
        $request->validate(['airport' => 'required|regex:/^[A-Z]{3}$/']);
        $airport = AirportWorkspace::airport($request->user(), $request->query('airport'));

        return view('m01.infrastructure-import', compact('airport'));
    }

    public function template(Request $request)
    {
        AirportWorkspace::airports($request->user());

        return response()->streamDownload(function () {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");
            fputcsv($file, InfrastructureImport::COLUMNS, ',', '"', '');
            fclose($file);
        }, 'infrastructure-import.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function preview(Request $request, InfrastructureImport $import)
    {
        $data = $request->validate(['airport_code' => 'required|regex:/^[A-Z]{3}$/', 'source_reference' => 'required|string|max:500', 'file' => 'required|file|max:1024'], ['file.file'=>'Vui lòng chọn tệp CSV.', 'file.max'=>'Tệp CSV không được vượt 1 MB.'], ['file'=>'tệp CSV', 'source_reference'=>'tài liệu hoặc người cung cấp', 'airport_code'=>'sân bay']);
        $airport = AirportWorkspace::airport($request->user(), $data['airport_code']);
        $rows = $import->preview($request->user(), $airport->code, $request->file('file')->getRealPath(), $data['source_reference']);
        $token = Str::random(48);
        $request->session()->put('infrastructure_import', ['token' => $token, 'actor' => $request->user()->id, 'airport' => $airport->code, 'rows' => $rows, 'source' => $data['source_reference'], 'expires' => now()->addMinutes(30)->timestamp]);

        return view('m01.infrastructure-import', compact('airport', 'rows', 'token') + ['source' => $data['source_reference']]);
    }

    public function store(Request $request, InfrastructureImport $import)
    {
        $request->validate(['token' => 'required|string']);
        $batch = $request->session()->get('infrastructure_import');
        abort_unless($batch && $batch['actor'] === $request->user()->id && $batch['expires'] >= now()->timestamp && hash_equals($batch['token'], $request->input('token')), 409, 'Bản xem trước đã hết hạn hoặc đã được nhập.');
        AirportWorkspace::airport($request->user(), $batch['airport']);
        $ids = $import->commit($request->user(), $batch['airport'], $batch['rows'], $batch['source']);
        $request->session()->forget('infrastructure_import');

        return redirect('/workspace/catalog?flow=infrastructure&airport='.$batch['airport'])->with('message', 'Đã nhập '.count($ids).' hạng mục hạ tầng.');
    }
}
