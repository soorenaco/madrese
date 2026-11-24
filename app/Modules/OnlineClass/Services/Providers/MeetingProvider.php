<?php

namespace App\Modules\OnlineClass\Services\Providers;

use App\Modules\Auth\Models\User;
use App\Modules\OnlineClass\Models\OnlineClass;

/**
 * رابط مشترک ارائه‌دهنده‌های جلسهٔ آنلاین (BigBlueButton / Skyroom).
 */
interface MeetingProvider
{
    /**
     * ساخت (یا تأیید) اتاق جلسه در سمت ارائه‌دهنده.
     *
     * @return string شناسهٔ اتاق — BBB: meetingID، Skyroom: room_id (رشته).
     *
     * @throws \RuntimeException اگر فراخوانی ناموفق باشد.
     */
    public function createRoom(OnlineClass $online): string;

    /**
     * ساخت لینک ورود برای کاربر. $moderator نقش را تعیین می‌کند.
     */
    public function getJoinUrl(OnlineClass $online, User $user, bool $moderator): string;

    /**
     * پایان جلسه در سمت ارائه‌دهنده (در صورت پشتیبانی).
     */
    public function endRoom(OnlineClass $online): void;

    /**
     * آیا جلسه در حال اجراست؟
     */
    public function isRunning(OnlineClass $online): bool;
}
