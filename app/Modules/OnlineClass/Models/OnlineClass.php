<?php

namespace App\Modules\OnlineClass\Models;

use App\Modules\Auth\Models\User;
use App\Modules\Class\Models\SchoolClass;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnlineClass extends Model
{
    use HasUuids;

    protected $table = 'online_classes';

    protected $fillable = [
        'school_class_id',
        'provider',
        'title',
        'provider_room_id',
        'status',
        'record',
        'recording_url',
        'started_at',
        'ended_at',
        'created_by',
    ];

    protected $casts = [
        'record'      => 'boolean',
        'started_at'  => 'datetime',
        'ended_at'    => 'datetime',
    ];

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isLive(): bool
    {
        return $this->status === 'live';
    }
}
