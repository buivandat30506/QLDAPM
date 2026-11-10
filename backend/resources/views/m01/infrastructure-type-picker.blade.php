<div class="infra-type-picker">
    <label>{{ $pickerLabel }}<select class="form-select" name="{{ $pickerName }}" @required($pickerName === 'type')>
        <option value="">{{ $pickerName === 'type' ? 'Chọn loại…' : 'Tất cả loại trong phạm vi' }}</option>
        @foreach($types->groupBy('group_code') as $groupKey => $groupTypes)
        <optgroup label="{{ \App\Support\InfrastructureCatalog::GROUPS[$groupKey] }}">
            @foreach($groupTypes as $type)<option value="{{ $type->code }}" @selected(($pickerName === 'kind' && request('kind') === $type->code) || ($pickerName === 'type' && request('kind') === $type->code))>{{ $type->label }}</option>@endforeach
        </optgroup>
        @endforeach
    </select></label>
</div>
