<?php

namespace App\Http\Requests\Core;

class CreateFlightWithCrewRequest extends CreateOperationalFlightRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'crew' => ['sometimes', 'array:CAPTAIN,FIRST_OFFICER,PURSER,FLIGHT_ATTENDANT'],
            'crew.CAPTAIN' => ['sometimes', 'array', 'max:1'],
            'crew.FIRST_OFFICER' => ['sometimes', 'array', 'max:1'],
            'crew.PURSER' => ['sometimes', 'array', 'max:1'],
            'crew.FLIGHT_ATTENDANT' => ['sometimes', 'array'],
            'crew.*.*' => ['nullable', 'uuid', 'distinct', 'exists:crew_members,id'],
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'crew.array' => 'Danh sách vị trí phi hành đoàn không hợp lệ.',
            'crew.*.array' => 'Danh sách nhân sự được chọn không hợp lệ.',
            'crew.*.max' => 'Số nhân sự được chọn vượt giới hạn của vị trí.',
            'crew.*.*.uuid' => 'Mã nhân sự không hợp lệ. Vui lòng chọn lại.',
            'crew.*.*.distinct' => 'Không chọn cùng một nhân sự cho nhiều vị trí.',
            'crew.*.*.exists' => 'Nhân sự được chọn không còn tồn tại. Vui lòng chọn lại.',
        ];
    }
}
