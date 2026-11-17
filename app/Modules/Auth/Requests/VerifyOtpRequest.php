<?php

namespace App\Modules\Auth\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mobile' => [
                'required',
                'string',
                'max:20',
                'regex:/^(\+98|0)?9\d{9}$/',
            ],
            'code' => [
                'required',
                'string',
                'digits_between:4,6',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'mobile.required' => 'شماره موبایل الزامی است.',
            'mobile.regex'    => 'فرمت شماره موبایل معتبر نیست.',
            'code.required'   => 'کد تأیید الزامی است.',
            'code.digits_between' => 'کد تأیید باید بین ۴ تا ۶ رقم باشد.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('mobile')) {
            $this->merge([
                'mobile' => preg_replace('/\s+/', '', $this->mobile),
            ]);
        }
    }
}