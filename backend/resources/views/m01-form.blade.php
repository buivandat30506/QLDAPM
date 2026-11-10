@php($optional = static fn (array $field): bool => str_ends_with($field['type'], '-optional'))
<form method="post" action="/workspace/actions/{{ $form['action'] }}" class="m01-form">
    @csrf
    @foreach($form['fields'] as $field)
        @php($isOptional = $optional($field))
        @if($field['type'] === 'hidden')
            <input type="hidden" name="{{ $field['name'] }}" value="{{ $field['options']['value'] }}">
        @elseif($field['type'] === 'uuid')
            <input type="hidden" name="{{ $field['name'] }}" value="{{ \Illuminate\Support\Str::uuid() }}">
        @else
            <div class="m01-field">
                @if($field['type'] === 'multi-select')<span>{{ $field['label'] }}@if(!$isOptional)<b aria-hidden="true"> *</b>@endif</span>@else<label for="{{ $formKey.'-'.str_replace(['[', ']'], '-', $field['name']) }}">{{ $field['label'] }}@if(!$isOptional)<b aria-hidden="true"> *</b>@endif</label>@endif
                @if($field['type'] === 'multi-select')
                    <span class="m01-checklist">@forelse($field['options'] as $val => $label)<label><input type="checkbox" name="{{ $field['name'] }}[]" value="{{ $val }}"> {{ $label }}</label>@empty<span class="subtle">Chưa có lựa chọn.</span>@endforelse</span>
                @elseif(str_starts_with($field['type'], 'select'))
                    <select class="form-select" id="{{ $formKey.'-'.str_replace(['[', ']'], '-', $field['name']) }}" name="{{ $field['name'] }}" @required(!$isOptional)><option value="">Chọn…</option>@foreach($field['options'] as $val => $label)<option value="{{ $val }}" @selected((string)old($field['name']) === (string)$val)>{{ $label }}</option>@endforeach</select>
                @elseif(str_starts_with($field['type'], 'textarea'))
                    <textarea class="form-control" id="{{ $formKey.'-'.str_replace(['[', ']'], '-', $field['name']) }}" name="{{ $field['name'] }}" rows="3" maxlength="4000" @required(!$isOptional)>{{ old($field['name']) }}</textarea>
                @else
                    <input class="form-control" id="{{ $formKey.'-'.str_replace(['[', ']'], '-', $field['name']) }}" type="{{ $isOptional ? 'text' : $field['type'] }}" name="{{ $field['name'] }}" value="{{ old($field['name']) }}" @if($field['type'] === 'number') step="any" @endif @required(!$isOptional)>
                @endif
            </div>
            @if($errors->has($field['name']))<span class="m01-field-error">{{ $errors->first($field['name']) }}</span>@endif
        @endif
    @endforeach
    <button class="btn btn-primary" type="submit">{{ $form['title'] }}</button>
</form>
