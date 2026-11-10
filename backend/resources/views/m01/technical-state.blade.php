@php($conditionTone = ['NORMAL' => 'normal', 'MAINTENANCE' => 'maintenance', 'RESTRICTED' => 'high', 'OUT_OF_SERVICE' => 'unavailable'][$condition ?? ''] ?? 'unknown')
<span class="airport-status airport-status--{{ $conditionTone }}"><span aria-hidden="true"></span>{{ \App\Support\InfrastructureCatalog::CONDITIONS[$condition ?? ''] ?? 'Chưa xác định' }}</span>
