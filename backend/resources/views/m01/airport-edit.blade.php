<details class="airport-card airport-disclosure" id="airport-edit" @if($errors->hasAny(['name','city','timezone','reason','changes']) || !empty($draft)) open @endif>
    <summary>Đề nghị sửa hồ sơ {{ $airport->code }}</summary>
    <p class="subtle">Đối chiếu thông tin hiện tại, nhập nội dung cần sửa rồi xem lại trước khi gửi.</p>
    <form method="post" action="/workspace/catalog/airports/{{ $airport->code }}/changes/preview" class="airport-form">
        @csrf @include('m01.infra-error',['field'=>'changes'])
        <fieldset class="infra-fieldset"><legend>Thông tin sân bay</legend>
            <dl class="airport-info"><div><dt>Mã IATA</dt><dd>{{ $airport->code }}</dd></div><div><dt>Mã ICAO</dt><dd>{{ $airport->icao ?: 'Chưa ghi nhận' }}</dd></div></dl>
            <label class="mt-3">Tên sân bay<input class="form-control" name="name" required maxlength="120" value="{{ old('name',$draft['name'] ?? $airport->name) }}">@include('m01.infra-error',['field'=>'name'])</label>
        </fieldset>
        <fieldset class="infra-fieldset"><legend>Vị trí và múi giờ</legend>
            <div class="airport-form-grid">
                <label>Thành phố<input class="form-control" name="city" required maxlength="100" value="{{ old('city',$draft['city'] ?? $airport->city) }}">@include('m01.infra-error',['field'=>'city'])</label>
                <label>Múi giờ (IANA)<input class="form-control" name="timezone" required maxlength="100" value="{{ old('timezone',$draft['timezone'] ?? $airport->timezone) }}"><small class="subtle">Ví dụ: Asia/Ho_Chi_Minh.</small>@include('m01.infra-error',['field'=>'timezone'])</label>
            </div>
            <dl class="airport-info"><div><dt>Mã quốc gia</dt><dd>{{ $airport->country_code ?: 'Chưa ghi nhận' }}</dd></div><div><dt>Tọa độ đã ghi nhận</dt><dd>{{ isset($airport->latitude,$airport->longitude) ? $airport->latitude.', '.$airport->longitude : 'Chưa ghi nhận' }}</dd></div></dl>
        </fieldset>
        <label>Lý do sửa<textarea class="form-control" name="reason" required minlength="5" maxlength="255" rows="3" placeholder="Nêu thông tin cần điều chỉnh và căn cứ đối chiếu">{{ old('reason',$draft['reason'] ?? '') }}</textarea>@include('m01.infra-error',['field'=>'reason'])</label>
        <div><button class="btn btn-primary" type="submit">Xem trước thay đổi</button></div>
        <p class="subtle">Mã định danh và thông tin vị trí đang hiển thị để đối chiếu. Đề nghị chỉ cập nhật tên, thành phố và múi giờ sau khi được người khác duyệt.</p>
    </form>
</details>
