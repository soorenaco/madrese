<?php

namespace App\Modules\Attendance\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\Class\Models\SchoolClass;
use App\Modules\Auth\Models\User;

class Attendance extends Model
{
    use HasUuids;

    protected $table = 'attendances';

    protected $fillable = [
        'school_class_id',
        'user_id',
        'session_date',
        'slot',
        'status',
        'recorded_by',
        'note',
    ];

    protected $casts = [
        'session_date' => 'date',
        'slot'         => 'integer',
    ];

    // ─── Relations ───

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    // ─── Helpers ───

    public function isPresent(): bool
    {
        return $this->status === 'present';
    }

    public function isAbsent(): bool
    {
        return $this->status === 'absent';
    }

    /**
     * برچسب فارسی وضعیت.
     */
    public static function statusLabel(string $status): string
    {
        return [
            'present' => 'حاضر',
            'absent'  => 'غایب',
            'late'    => 'تأخیر',
            'excused' => 'موجه',
        ][$status] ?? $status;
    }

    /**
     * نقشه‌ی رنگ برای UI (به‌صورت رشته‌ی variant برای StatusBadge).
     */
    public static function statusVariant(string $status): string
    {
        return [
            'present' => 'success',
            'absent'  => 'danger',
            'late'    => 'warning',
            'excused' => 'info',
        ][$status] ?? 'neutral';
    }

    /**
     * لیست وضعیت‌های مجاز برای فرم.
     */
    public static function statuses(): array
    {
        return ['present', 'absent', 'late', 'excused'];
    }
}
