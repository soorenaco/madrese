<?php

namespace App\Modules\Class\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SchoolClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $existing = $this->route('class');
        $schoolId = $this->input('school_id', $existing?->school_id);

        return [
            'school_id' => ['required', 'uuid', 'exists:schools,id'],
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('school_classes', 'name')
                    ->where(fn ($q) => $q->where('school_id', $schoolId)
                        ->where('academic_year', $this->input('academic_year')))
                    ->ignore($existing?->id),
            ],
            'grade'           => ['nullable', 'string', 'max:40'],
            'academic_year'   => ['nullable', 'string', 'max:20'],
            'teacher_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'status'          => ['required', Rule::in(['active', 'archived'])],
        ];
    }

    public function messages(): array
    {
        return [
            'school_id.required' => 'انتخاب مدرسه الزامی است.',
            'name.required'      => 'نام کلاس الزامی است.',
            'name.unique'        => 'این نام کلاس در همین مدرسه و سال تحصیلی تکراری است.',
        ];
    }
}