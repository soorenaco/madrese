<?php

namespace Tests\Fakes;

use App\Modules\Auth\Models\User;
use App\Modules\OnlineClass\Models\OnlineClass;
use App\Modules\OnlineClass\Services\Providers\MeetingProvider;

/**
 * ارائه‌دهندهٔ جعلی برای تست‌ها: بدون فراخوانی شبکه.
 * فراخوانی‌ها را در یک آرایهٔ استاتیک ثبت می‌کند تا در assertions بررسی شوند.
 */
class FakeMeetingProvider implements MeetingProvider
{
    /** @var list<array<mixed>> */
    public static array $calls = [];

    public static ?string $lastJoinUrl = null;

    public static function reset(): void
    {
        self::$calls = [];
        self::$lastJoinUrl = null;
    }

    public function createRoom(OnlineClass $online): string
    {
        $id = $online->provider_room_id ?: 'fake-room-' . $online->id;

        self::$calls[] = ['createRoom', $online->id, $online->provider];

        return $id;
    }

    public function getJoinUrl(OnlineClass $online, User $user, bool $moderator): string
    {
        $url = 'https://fake.example/join'
            . '?room=' . $online->provider_room_id
            . '&user=' . $user->id
            . '&role=' . ($moderator ? 'moderator' : 'viewer');

        self::$calls[] = ['getJoinUrl', $online->id, $user->id, $moderator];
        self::$lastJoinUrl = $url;

        return $url;
    }

    public function endRoom(OnlineClass $online): void
    {
        self::$calls[] = ['endRoom', $online->id];
    }

    public function isRunning(OnlineClass $online): bool
    {
        return true;
    }
}
