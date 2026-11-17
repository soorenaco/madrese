<?php

namespace App\Modules\School\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SchoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return self::rulesFor($this->route('school')?->id);
    }

    public function messages(): array
    {
        return self::messagesList();
    }

    public static function rulesFor(?string $ignoreId = null): array
    {
        return [
            'name'        => ['required', 'string', 'max:200'],
            'code'        => ['nullable', 'string', 'max:40', Rule::unique('schools', 'code')->ignore($ignoreId)],
            'city'        => ['nullable', 'string', 'max:100'],
            'address'     => ['nullable', 'string', 'max:500'],
            'status'      => ['required', Rule::in(['active', 'suspended'])],
            'phone'       => ['nullable', 'string', 'max:20'],
            'email'       => ['nullable', 'email', 'max:120'],
            'website'     => ['nullable', 'url', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'logo'        => ['nullable', 'image', 'max:2048'],

            'manager_mobile'       => [$ignoreId ? 'nullable' : 'required', 'string', 'max:20'],
            'manager_display_name' => ['nullable', 'string', 'max:160'],

        ];
    }

    public static function messagesList(): array
    {
        return [
            'name.required' => 'نام مدرسه الزامی است.',
            'logo.image'    => 'لوگو باید تصویر باشد.',
            'logo.max'      => 'حجم لوگو نباید بیشتر از ۲ مگابایت باشد.',
        ];
    }
}