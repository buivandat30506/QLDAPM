@php($attributeFields = \App\Support\InfrastructureCatalog::PROFILE_FIELDS[$type['code']] ?? [])
@foreach(\App\Support\InfrastructureCatalog::ATTRIBUTE_OPTIONS as $field => $definition)
    @if(in_array($field, $attributeFields, true))
    <label>{{ $definition['label'] }}<select class="form-select" name="{{ $field }}"><option value="">Chưa khai báo</option>@foreach($definition['options'] as $value=>$label)<option value="{{ $value }}" @selected(old($field,$profile[$field] ?? '') === $value)>{{ $label }}</option>@endforeach</select>@include('m01.infra-error',['field'=>$field])</label>
    @endif
@endforeach
@if($type['code'] === 'TERMINAL')
<fieldset class="infra-fieldset"><legend>Chức năng phục vụ nhà ga</legend><div class="airport-form-grid">@foreach(['serves_departures'=>'Phục vụ khởi hành','serves_arrivals'=>'Phục vụ chuyến đến'] as $field=>$label)<label>{{ $label }}<select class="form-select" name="{{ $field }}"><option value="">Chưa khai báo</option><option value="1" @selected((string)old($field,$profile[$field] ?? '') === '1')>Có</option><option value="0" @selected((string)old($field,$profile[$field] ?? '') === '0')>Không</option></select>@include('m01.infra-error',['field'=>$field])</label>@endforeach</div></fieldset>
@endif
