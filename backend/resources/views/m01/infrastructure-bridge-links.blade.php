<fieldset class="infra-fieldset"><legend>Liên kết đã xác nhận</legend><div class="airport-form-grid">
@foreach(['gate_ids'=>['Cổng liên kết',$gateChoices,$linkedGates], 'stand_ids'=>['Vị trí đỗ liên kết',$standChoices,$linkedStands]] as $field=>[$label,$options,$selected])
    <label>{{ $label }}<select class="form-select" name="{{ $field }}[]" multiple size="4">
        @foreach($options as $option)<option value="{{ $option->id }}" @selected(in_array($option->id,old($field,$selected)))>{{ \App\Support\InfrastructureDisplay::name($option) }}</option>@endforeach
    </select>@include('m01.infra-error',['field'=>$field])</label>
@endforeach
</div><small class="subtle">Chỉ chọn liên kết đã được xác nhận. Giữ Ctrl hoặc Command để chọn nhiều.</small></fieldset>
