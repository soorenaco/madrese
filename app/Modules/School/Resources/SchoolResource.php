<?php

namespace App\Modules\School\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SchoolResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'code'              => $this->code,
            'city'              => $this->city,
            'address'           => $this->address,
            'status'            => $this->status,
            'phone'             => $this->phone,
            'email'             => $this->email,
            'website'           => $this->website,
            'description'       => $this->description,
            'logo'              => $this->logo,
            'classes_count'     => (int) ($this->classes_count ?? 0),
            'memberships_count' => (int) ($this->memberships_count ?? 0),
        ];
    }
}
