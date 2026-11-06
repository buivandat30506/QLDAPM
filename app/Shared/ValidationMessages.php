<?php

namespace App\Shared;

class ValidationMessages
{
    public const VI = [
        'required' => 'Vui lòng nhập :attribute.', 'required_with' => 'Vui lòng nhập :attribute khi có :values.',
        'unique' => ':attribute đã tồn tại.', 'exists' => ':attribute tham chiếu dữ liệu không tồn tại.',
        'regex' => ':attribute không đúng định dạng.', 'not_regex' => ':attribute có ký tự không hợp lệ.',
        'timezone' => ':attribute phải là múi giờ IANA hợp lệ.', 'in' => ':attribute không thuộc danh sách được phép.',
        'array' => ':attribute phải có đúng cấu trúc và thuộc tính cho phép.', 'string' => ':attribute phải là chuỗi ký tự.',
        'integer' => ':attribute phải là số nguyên.', 'numeric' => ':attribute phải là số.', 'boolean' => ':attribute phải là giá trị đúng/sai.',
        'between' => ':attribute phải nằm trong khoảng :min đến :max.', 'max' => ':attribute vượt giới hạn :max.',
        'min' => ':attribute phải có tối thiểu :min.', 'json' => ':attribute phải là JSON hợp lệ.', 'file' => ':attribute phải là tệp tải lên.',
    ];
}
