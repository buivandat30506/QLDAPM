<?php

namespace App\Services\Administration;

use App\Services\Core\AuditService;
use App\Shared\Security\SensitiveData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class AuditQuery
{
    public function query(array $filters)
    {
        Gate::authorize('audit.read');
        Validator::make($filters, ['entity_type' => 'nullable|string|max:80', 'entity_id' => 'nullable|string|max:80', 'action' => 'nullable|string|max:80', 'actor_id' => 'nullable|uuid', 'correlation_id' => 'nullable|uuid', 'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from'])->validate();
        $query = DB::table('audit_logs')->orderByDesc('id');
        foreach (['entity_type', 'entity_id', 'action', 'actor_id', 'correlation_id'] as $field) {
            if (! empty($filters[$field])) { $query->where($field, $filters[$field]); }
        }
        if (! empty($filters['from'])) { $query->where('created_at', '>=', $filters['from'].' 00:00:00'); }
        if (! empty($filters['to'])) { $query->where('created_at', '<', \Carbon\Carbon::parse($filters['to'], 'UTC')->addDay()); }

        return $query;
    }

    public function page(array $filters)
    {
        return $this->query($filters)->paginate(25)->through(function ($row) {
            foreach (['before_data', 'after_data'] as $field) {
                $row->{$field} = SensitiveData::redact(json_decode($row->{$field} ?? 'null', true));
            }

            return $row;
        });
    }

    public function export(array $filters): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        Gate::authorize('audit.export');
        $query = $this->query($filters);
        AuditService::record('audit_logs', 'export', 'AUDIT_EXPORTED', null, ['filters' => $filters]);

        return response()->streamDownload(function () use ($query) {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['id', 'actor_id', 'action', 'entity_type', 'entity_id', 'created_at_utc', 'outcome', 'correlation_id']);
            foreach ($query->cursor() as $row) {
                fputcsv($stream, array_map(fn ($value) => preg_match('/^[\s]*[=+\-@]/u', (string) $value) ? "'".$value : $value,
                    [$row->id, $row->actor_id, $row->action, $row->entity_type, $row->entity_id, $row->created_at, $row->outcome, $row->correlation_id]));
            }
            fclose($stream);
        }, 'audit.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
