<fieldset class="mt-4" data-flight-crew-fields @disabled(! $creationCrewAllowed)>
    <legend class="h6 module-heading"><i class="bi bi-people" aria-hidden="true"></i>Phân công phi hành đoàn</legend>
    <p class="form-text">Nhân sự được kiểm tra năng định, lịch trực và thời gian nghỉ khi khởi tạo. Phân công vẫn cần duyệt và xác nhận.</p>
    @if($creationCrew->isEmpty())
        <p class="text-muted">Chưa có nhân sự khả dụng trong phạm vi phân công.</p>
    @else
        <div class="row g-3">
            @foreach([
                'CAPTAIN' => ['Cơ trưởng', ['CAPTAIN']],
                'FIRST_OFFICER' => ['Cơ phó', ['CAPTAIN', 'FIRST_OFFICER']],
                'PURSER' => ['Tiếp viên trưởng', ['PURSER']],
                'FLIGHT_ATTENDANT' => ['Tiếp viên', ['PURSER', 'FLIGHT_ATTENDANT']],
            ] as $position => [$label, $ranks])
                <div class="col-md-6">
                    @if($position === 'FLIGHT_ATTENDANT')
                        <span id="create-crew-attendants-label" class="form-label d-block">{{ $label }}</span>
                        <div id="create_crew_flight_attendant" class="flight-crew-options border rounded p-2 d-flex flex-column gap-2 {{ $errors->has('crew.'.$position.'.*') ? 'border-danger' : '' }}" role="group" aria-labelledby="create-crew-attendants-label">
                            @forelse($creationCrew->whereIn('rank', $ranks) as $member)
                                <div class="form-check mb-0">
                                    <input id="create-crew-attendant-{{ $member->id }}" type="checkbox" name="crew[{{ $position }}][]" value="{{ $member->id }}" class="form-check-input" @checked(in_array($member->id, (array) old('crew.'.$position, []), true))>
                                    <label for="create-crew-attendant-{{ $member->id }}" class="form-check-label text-break">{{ $member->crew_code }} · {{ $member->full_name }} · {{ $member->base_airport_code }}</label>
                                </div>
                            @empty
                                <span class="text-muted">Chưa có tiếp viên khả dụng trong phạm vi phân công.</span>
                            @endforelse
                        </div>
                    @else
                    <label for="create_crew_{{ strtolower($position) }}" class="form-label">{{ $label }}</label>
                    <select id="create_crew_{{ strtolower($position) }}" name="crew[{{ $position }}][]" class="form-select {{ $errors->has('crew.'.$position.'.*') ? 'is-invalid' : '' }}">
                        <option value="">Chưa phân công</option>
                        @foreach($creationCrew->whereIn('rank', $ranks) as $member)
                            <option value="{{ $member->id }}" @selected(in_array($member->id, (array) old('crew.'.$position, []), true))>{{ $member->crew_code }} · {{ $member->full_name }} · {{ $member->base_airport_code }}</option>
                        @endforeach
                    </select>
                    @endif
                    @foreach($errors->get('crew.'.$position.'.*') as $messages)
                        @foreach($messages as $message)<div class="invalid-feedback d-block">{{ $message }}</div>@endforeach
                    @endforeach
                </div>
            @endforeach
        </div>
    @endif
</fieldset>
<p class="form-text" data-flight-crew-unavailable role="status" @if($creationCrewAllowed) hidden @endif>Bạn không có quyền phân công phi hành đoàn cho lịch đang chọn.</p>
