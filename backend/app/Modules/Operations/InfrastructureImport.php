<?php

namespace App\Modules\Operations;

use App\Models\User;
use App\Modules\Shared\Access;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class InfrastructureImport
{
    public const COLUMNS = ['code', 'type_code', 'name', 'parent_code', 'technical_state', 'description', 'terminal_kind', 'terminal_scope', 'serves_departures', 'serves_arrivals', 'zone_function', 'counter_service', 'baggage_flow', 'taxiway_function', 'asset_reference', 'length_m', 'width_m', 'limitations', 'directions', 'compatibility_notes', 'gate_codes', 'stand_codes'];

    public function preview(User $user, string $airport, string $path, string $source): array
    {
        Access::role($user, 'data_admin');
        Access::airport($user, $airport);
        $content = file_get_contents($path);
        if (! mb_check_encoding($content, 'UTF-8')) {
            throw ValidationException::withMessages(['file' => 'Tệp phải dùng mã hóa UTF-8.']);
        }
        $handle = fopen($path, 'r');
        try {
            $headers = fgetcsv($handle, 0, ',', '"', '');
            if ($headers) {
                $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
            }
            if (! $headers || count(array_unique($headers)) !== count($headers) || array_diff($headers, self::COLUMNS) || array_diff(['code', 'type_code'], $headers)) {
                throw ValidationException::withMessages(['file' => 'Tiêu đề CSV không hợp lệ. Hãy dùng tệp mẫu.']);
            }
            $rows = [];
            $seen = [];
            $assets = [];
            $errors = [];
            $line = 1;
            while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                $line++;
                if (count(array_filter($values, fn ($v) => trim($v ?? '') !== '')) === 0) {
                    continue;
                }
                if (count($rows) >= 200 || $line > 1000) {
                    throw ValidationException::withMessages(['file' => 'Mỗi tệp tối đa 200 hạng mục.']);
                }
                if (count($values) !== count($headers)) {
                    $errors[] = "Dòng $line: Số cột không khớp tiêu đề.";
                    continue;
                }
                $row = array_map(fn ($v) => trim($v ?? ''), array_combine($headers, $values));
                $key = mb_strtoupper($row['code']);
                if (isset($seen[$key])) {
                    $errors[] = "Dòng $line: Mã bị lặp trong tệp.";
                }
                $seen[$key] = true;
                if (! empty($row['asset_reference'])) {
                    $assetKey = mb_strtoupper($row['asset_reference']);
                    if (isset($assets[$assetKey])) {
                        $errors[] = "Dòng $line: Mã tài sản bị lặp trong tệp.";
                    }
                    $assets[$assetKey] = true;
                }
                try {
                    $input = $this->input($airport, $row, $source);
                    app(InfrastructureService::class)->validate($input);
                } catch (ValidationException $e) {
                    $errors[] = "Dòng $line: ".implode(' ', $e->validator->errors()->all());
                } catch (HttpExceptionInterface $e) {
                    $errors[] = "Dòng $line: ".$e->getMessage();
                }
                $rows[] = $row;
            }
            if ($errors || ! $rows) {
                throw ValidationException::withMessages(['file' => $errors ?: ['Tệp chưa có hạng mục.']]);
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    private function input(string $airport, array $row, string $source): array
    {
        $db = DB::connection('ops');
        $data = ['airport_code' => $airport, 'active' => true, 'technical_state' => null, 'source_reference' => $source];
        foreach ($row as $key => $value) {
            if ($value === '' || in_array($key, ['parent_code', 'gate_codes', 'stand_codes'], true)) {
                continue;
            }
            $data[$key] = $key === 'directions' ? preg_split('/[|\s]+/', $value, -1, PREG_SPLIT_NO_EMPTY) : $value;
        }
        foreach (['parent_code' => 'parent_id', 'gate_codes' => 'gate_ids', 'stand_codes' => 'stand_ids'] as $column => $field) {
            if (empty($row[$column])) {
                continue;
            }
            $codes = $column === 'parent_code' ? [$row[$column]] : explode('|', $row[$column]);
            $ids = [];
            foreach ($codes as $code) {
                $record = $db->table('airport_infrastructure')->where('airport_code', $airport)->where('code', trim($code))->first();
                if (! $record) {
                    throw ValidationException::withMessages([$column => 'Công trình hoặc đối tượng liên kết phải được khai báo trước tại sân bay đang chọn.']);
                }
                $ids[] = $record->id;
            }
            $data[$field] = $column === 'parent_code' ? $ids[0] : $ids;
        }

        return $data;
    }

    public function commit(User $user, string $airport, array $rows, string $source): array
    {
        Access::role($user, 'data_admin');
        Access::airport($user, $airport);

        return DB::connection('ops')->transaction(function () use ($user, $airport, $rows, $source) {
            DB::connection('ops')->table('airports')->where('code', $airport)->lockForUpdate()->first() ?? abort(404);
            $ids = [];
            foreach ($rows as $row) {
                $ids[] = app(InfrastructureService::class)->create($user, $this->input($airport, $row, $source), 'CSV_IMPORT')->id;
            }

            return $ids;
        }, 3);
    }
}
