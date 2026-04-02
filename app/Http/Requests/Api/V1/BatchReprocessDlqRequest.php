<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class BatchReprocessDlqRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => ['nullable', 'array'],
            'ids.*' => ['uuid'],
            'event_type' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (empty($this->input('ids')) && empty($this->input('event_type'))) {
                $validator->errors()->add('filter', 'ids 또는 event_type 중 하나는 필수입니다.');
            }
        });
    }
}
