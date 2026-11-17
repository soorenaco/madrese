<?php

namespace App\Modules\Class\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SchoolClassResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'school_id'       => $this->school_id,
            'name'            => $this->name,
            'grade'           => $this->grade,
            'academic_year'   => $this->academic_year,
            'teacher_user_id' => $this->teacher_user_id,
            'status'          => $this->status,
            'school'          => $this->whenLoaded('school', fn () => [
                'id'   => $this->school->id,
                'name' => $this->school->name,
            ]),
            'teacher'         => $this->whenLoaded('teacher', fn () => [
                'id'           => $this->teacher->id,
                'display_name' => $this->teacher->display_name,
                'mobile'       => $this->teacher->mobile_e164,
            ]),
            'students_count'  => $this->when(isset($this->enrollments_count), fn () => (int) $this->enrollments_count),
            'created_at'      => $this->created_at?->toIso8601String(),
            'updated_at'      => $this->updated_at?->toIso8601String(),
        ];
    }
}