<?php

namespace App\Modules\Class\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassEnrollmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'school_class_id' => $this->school_class_id,
            'user_id'         => $this->user_id,
            'status'          => $this->status,
            'enrolled_at'     => $this->enrolled_at?->toIso8601String(),
            'ended_at'        => $this->ended_at?->toIso8601String(),
            'user'            => $this->whenLoaded('user', fn () => [
                'id'           => $this->user->id,
                'display_name' => $this->user->display_name,
                'mobile'       => $this->user->mobile_e164,
                'status'       => $this->user->status,
            ]),
        ];
    }
}