<details id="flight-crew-panel" class="card module-disclosure module-tone-purple">
    <summary><span><i class="bi bi-people module-icon" aria-hidden="true"></i>Phi hành đoàn của chuyến bay</span></summary>
    <div class="card-body">
        <ul class="module-feature-list">
            <li>Danh sách phi hành đoàn</li>
            <li>Phân công cơ trưởng</li>
            <li>Phân công cơ phó</li>
            <li>Phân công tiếp viên</li>
            <li>Thay đổi phi hành đoàn</li>
        </ul>
        <a class="btn btn-outline-primary" href="{{ $assignmentFlight ? route('admin.crew.plan', $assignmentFlight->id) : route('admin.crew.plans') }}">Phân công phi hành đoàn</a>
    </div>
</details>
