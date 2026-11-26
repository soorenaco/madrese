<?php

namespace App\Modules\Class\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Modules\School\Models\School;
use App\Modules\Auth\Models\User;
use App\Modules\Attendance\Models\Attendance;

class SchoolClass extends Model
{
    use HasUuids;

    protected $table = 'school_classes';

    protected $fillable = [
        'school_id',
        'name',
        'grade',
        'academic_year',
        'teacher_user_id',
        'status',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_user_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(ClassEnrollment::class, 'school_class_id');
    }

    public function scheduleSlots(): HasMany
    {
        return $this->hasMany(ClassScheduleSlot::class, 'school_class_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'school_class_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
