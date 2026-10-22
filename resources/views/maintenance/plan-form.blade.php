@php($p = $plan ?? null)
<div class="row g-3">
@if(!$p)
<div class="col-md-6"><label for="aircraft_tail" class="form-label">Máy bay / số liệu sử dụng</label><select id="aircraft_tail" name="aircraft_tail" class="form-select" required>
@foreach($aircrafts as $a)<option value="{{ $a->tail_number }}">{{ $a->tail_number }} — {{ $a->total_flight_hours }} giờ / {{ $a->total_flight_cycles }} chu kỳ — {{ $a->base_airport_code ?: 'chưa xác định cơ sở' }}</option>@endforeach</select></div>
@endif
<div class="col-md-6"><label for="plan_code" class="form-label">Mã kế hoạch</label><input id="plan_code" name="plan_code" class="form-control" value="{{ old('plan_code', $p?->plan_code) }}" pattern="[A-Za-z0-9_-]{1,50}" required maxlength="50"></div>
<div class="col-md-6"><label for="task" class="form-label">Hạng mục bảo dưỡng</label><input id="task" name="task" class="form-control" value="{{ old('task', $p?->task) }}" maxlength="200" required></div>
<div class="col-md-6"><label for="trigger_type" class="form-label">Cơ sở xác định hạn</label><select id="trigger_type" name="trigger_type" class="form-select">@foreach(['DATE'=>'Ngày giờ','HOURS'=>'Giờ bay tích lũy','CYCLES'=>'Chu kỳ tích lũy','CONDITION'=>'Điều kiện kỹ thuật'] as $key=>$text)<option value="{{ $key }}" @selected(old('trigger_type', $p?->trigger_type ?? 'DATE') === $key)>{{ $text }}</option>@endforeach</select></div>
@foreach(['starts_at_utc'=>'Bắt đầu (UTC)','ends_at_utc'=>'Kết thúc dự kiến (UTC)','due_at_utc'=>'Hạn hoàn tất (UTC)'] as $field=>$label)
<div class="col-md-4"><label for="{{ $field }}" class="form-label">{{ $label }}</label><input id="{{ $field }}" type="datetime-local" name="{{ $field }}" class="form-control" value="{{ old($field, $p ? \Carbon\Carbon::parse($p->$field, 'UTC')->format('Y-m-d\TH:i') : '') }}" required></div>
@endforeach
<div class="col-md-4"><label for="due_hours" class="form-label">Hạn tổng giờ bay (nếu chọn giờ)</label><input id="due_hours" name="due_hours" type="number" min="0" step="0.01" class="form-control" value="{{ old('due_hours', $p?->due_hours) }}"></div>
<div class="col-md-4"><label for="due_cycles" class="form-label">Hạn tổng chu kỳ (nếu chọn chu kỳ)</label><input id="due_cycles" name="due_cycles" type="number" min="0" class="form-control" value="{{ old('due_cycles', $p?->due_cycles) }}"></div>
<div class="col-md-4"><label for="condition" class="form-label">Điều kiện kỹ thuật</label><input id="condition" name="condition" class="form-control" value="{{ old('condition', $p?->condition) }}"></div>
<div class="col-md-6"><input type="hidden" name="condition_active" value="0"><label class="form-check-label"><input class="form-check-input" name="condition_active" type="checkbox" value="1" @checked(old('condition_active', $p?->condition_active))> Điều kiện đang kích hoạt (chặn khai thác)</label></div>
<div class="col-md-6"><input type="hidden" name="resets_usage_basis" value="0"><label class="form-check-label"><input class="form-check-input" name="resets_usage_basis" type="checkbox" value="1" @checked(old('resets_usage_basis', $p?->resets_usage_basis))> Đây là kiểm tra tổng giờ/chu kỳ, được thiết lập lại mốc khi giải phóng</label></div>
<div class="col-12"><label for="task_lines" class="form-label">Các bước bắt buộc (mỗi dòng một bước)</label><textarea id="task_lines" name="task_lines" rows="3" class="form-control" required>{{ old('task_lines', $p ? implode("\n", json_decode($p->tasks, true)) : '') }}</textarea></div>
<div class="col-12"><label for="plan_reason" class="form-label">Lý do / căn cứ kỹ thuật</label><textarea id="plan_reason" name="reason" class="form-control" required>{{ old('reason') }}</textarea></div>
</div>
@if($p)<input type="hidden" name="expected_revision" value="{{ $p->revision }}">@endif
<button class="btn btn-primary mt-3">Lưu kế hoạch</button>
