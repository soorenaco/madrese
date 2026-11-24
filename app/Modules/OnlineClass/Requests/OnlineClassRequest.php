<?php

namespace App\Modules\OnlineClass\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OnlineClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // در ثبت، ارائه‌دهنده الزامی است؛ در ویرایش، فقط اگر ارسال شد اعتبارسنجی می‌شود.
        $providerRule = $this->isMethod('POST')
            ? ['required', 'in:bbb,skyroom']
            : ['sometimes', 'in:bbb,skyroom'];

        return [
            'provider' => $providerRule,
            'title'    => ['nullable', 'string', 'max:160'],
            'record'   => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'provider.required' => 'انتخاب ارائه‌دهنده الزامی است.',
            'provider.in'       => 'ارائه‌دهنده باید BigBlueButton یا Skyroom باشد.',
            'title.max'         => 'عنوان نباید بیشتر از ۱۶۰ کاراکتر باشد.',
        ];
    }
}
