<?php

namespace App\Modules\OnlineClass\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OnlineClassResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'school_class_id' => $this->school_class_id,
            'provider'        => $this->provider,
            'title'           => $this->title,
            'status'          => $this->status,
            'record'          => $this->record,
            'recording_url'   => $this->recording_url,
            'started_at'      => $this->started_at?->toIso8601String(),
            'ended_at'        => $this->ended_at?->toIso8601String(),
            'is_live'         => $this->isLive(),
            'school_class'    => $this->whenLoaded('schoolClass', function () {
                $data = [
                    'id'             => $this->schoolClass->id,
                    'name'           => $this->schoolClass->name,
                    'grade'          => $this->schoolClass->grade,
                    'academic_year'  => $this->schoolClass->academic_year,
                ];

                if ($this->schoolClass->relationLoaded('school') && $this->schoolClass->school) {
                    $data['school'] = [
                        'id'   => $this->schoolClass->school->id,
                        'name' => $this->schoolClass->school->name,
                    ];
                }

                if ($this->schoolClass->relationLoaded('teacher') && $this->schoolClass->teacher) {
                    $data['teacher'] = [
                        'id'           => $this->schoolClass->teacher->id,
                        'display_name' => $this->schoolClass->teacher->display_name,
                    ];
                }

                return $data;
            }),
            'created_at'      => $this->created_at?->toIso8601String(),
            'updated_at'      => $this->updated_at?->toIso8601String(),
        ];
    }
}
