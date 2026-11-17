<?php

namespace App\Modules\Class\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassScheduleSlotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'school_class_id' => $this->school_class_id,
            'day_of_week'     => (int) $this->day_of_week,
            'start_time'      => substr((string) $this->start_time, 0, 5),
            'end_time'        => substr((string) $this->end_time, 0, 5),
            'room'            => $this->room,
        ];
    }
}