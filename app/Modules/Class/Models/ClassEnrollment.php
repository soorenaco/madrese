<?php

namespace App\Modules\Class\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\Auth\Models\User;

class ClassEnrollment extends Model
{
    use HasUuids;

    protected $fillable = [
        'school_class_id',
        'user_id',
        'status',
        'enrolled_at',
        'ended_at',
    ];

    protected $casts = [
        'enrolled_at' => 'datetime',
        'ended_at'    => 'datetime',
    ];

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->ended_at === null;
    }
}