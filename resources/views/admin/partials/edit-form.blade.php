<details class="d-inline-block text-start">
    <summary class="btn btn-sm btn-outline-primary">Sửa</summary>
    <form action="{{ $action }}" method="POST" class="border rounded p-3 mt-2 bg-light" style="min-width:260px">
        @csrf
        @method('PUT')
        @foreach($fields as $field => $settings)
            <label class="form-label d-block mt-2" for="edit-{{ $record->getKey() }}-{{ $field }}">{{ $settings['label'] }}</label>
            @if(isset($settings['options']))
                <select id="edit-{{ $record->getKey() }}-{{ $field }}" name="{{ $field }}{{ !empty($settings['multiple']) ? '[]' : '' }}" class="form-select form-select-sm" @if(!empty($settings['multiple'])) multiple @endif required>
                    @foreach($settings['options'] as $value => $label)
                        <option value="{{ $value }}" @selected(!empty($settings['multiple']) ? in_array($value, $record->{$field} ?? []) : (string) $record->{$field} === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
            @else
                <input id="edit-{{ $record->getKey() }}-{{ $field }}" name="{{ $field }}" type="{{ $settings['type'] ?? 'text' }}"
                    value="{{ ($settings['format'] ?? '') === 'csv' ? implode(',', $record->{$field} ?? []) : ((($settings['type'] ?? '') === 'datetime-local' && $record->{$field}) ? \Carbon\Carbon::parse($record->{$field})->format('Y-m-d\TH:i') : $record->{$field}) }}" class="form-control form-control-sm" @readonly($settings['readonly'] ?? false)
                    @if(isset($settings['min'])) min="{{ $settings['min'] }}" @endif
                    @if(isset($settings['max'])) max="{{ $settings['max'] }}" @endif
                    @if(isset($settings['step'])) step="{{ $settings['step'] }}" @endif @if($settings['required'] ?? true) required @endif>
            @endif
        @endforeach
        <button class="btn btn-sm btn-primary mt-3" type="submit">Lưu thay đổi</button>
    </form>
</details>
