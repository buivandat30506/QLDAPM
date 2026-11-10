<dl class="airport-info">
@foreach(\App\Support\InfrastructureCatalog::ATTRIBUTE_OPTIONS as $field=>$definition)
    @if(in_array($field, \App\Support\InfrastructureCatalog::PROFILE_FIELDS[$displayType] ?? [], true))
    <div><dt>{{ $definition['label'] }}</dt><dd>{{ $definition['options'][$profile[$field] ?? ''] ?? 'Chưa khai báo' }}</dd></div>
    @endif
@endforeach
@if($displayType === 'TERMINAL')
    @foreach(['serves_departures'=>'Phục vụ khởi hành','serves_arrivals'=>'Phục vụ chuyến đến'] as $field=>$label)<div><dt>{{ $label }}</dt><dd>{{ isset($profile[$field]) ? ($profile[$field] ? 'Có' : 'Không') : 'Chưa khai báo' }}</dd></div>@endforeach
@endif
</dl>
