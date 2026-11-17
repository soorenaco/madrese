<?php

namespace App\Modules\Auth\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
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
                // فرمت‌های قابل قبول: 09123456789 | +989123456789 | 9123456789
                'regex:/^(\+98|0)?9\d{9}$/',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'mobile.required' => 'شماره موبایل الزامی است.',
            'mobile.regex'    => 'فرمت شماره موبایل معتبر نیست.',
        ];
    }

    /**
     * آماده‌سازی داده قبل از اعتبارسنجی
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('mobile')) {
            $this->merge([
                'mobile' => preg_replace('/\s+/', '', $this->mobile),
            ]);
        }
    }
}