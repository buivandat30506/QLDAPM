@php($fieldLabels = ['open' => 'Mở cửa', 'close' => 'Đóng cửa', 'from' => 'Từ', 'to' => 'Đến', 'daily' => 'Hằng ngày', 'note' => 'Ghi chú', 'notes' => 'Ghi chú'])
<dl class="airport-info airport-info--single">
    @foreach($values as $key => $value)
        <div><dt>{{ is_int($key) ? 'Mục '.($key + 1) : ($fieldLabels[$key] ?? \Illuminate\Support\Str::headline($key)) }}</dt><dd>
            @if(is_array($value))
                @include('m01.value-list', ['values' => $value])
            @elseif(is_bool($value))
                {{ $value ? 'Có' : 'Không' }}
            @elseif(in_array($key,['type_code','type'],true) && is_string($value))
                {{ \App\Support\InfrastructureCatalog::label($value) }} ({{ $value }})
            @else
                {{ $value !== null && $value !== '' ? $value : 'Chưa khai báo' }}
            @endif
        </dd></div>
    @endforeach
</dl>
