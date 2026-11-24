<?php

namespace App\Modules\OnlineClass\Services\Providers;

use App\Modules\Auth\Models\User;
use App\Modules\OnlineClass\Models\OnlineClass;
use App\Modules\OnlineClass\Models\SystemSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * کلاینت Skyroom Web Service.
 *
 * endpoint: POST {base_url}/skyroom/api/{api_key}
 * بدنه: {"action": "...", "params": {...}}
 * پاسخ همیشه HTTP 200؛ موفقیت از فیلد ok خوانده می‌شود.
 *
 * کدهای دسترسی (access): 1 = کاربر عادی، 2 = ارایه‌کننده، 3 = اپراتور/مدیر.
 */
class SkyroomProvider implements MeetingProvider
{
    /** @return array{base_url:string, api_key:string} */
    protected function config(): array
    {
        $stored = SystemSetting::get('skyroom', []) ?? [];

        return [
            'base_url' => rtrim((string) ($stored['base_url'] ?? env('SKYROOM_BASE_URL', 'https://www.skyroom.online')), '/'),
            'api_key'  => (string) ($stored['api_key'] ?? env('SKYROOM_API_KEY', '')),
        ];
    }

    /**
     * @throws RuntimeException
     */
    protected function call(string $action, array $params): mixed
    {
        $cfg = $this->config();

        if ($cfg['api_key'] === '') {
            throw new RuntimeException('Skyroom توسط ادمین پیکربندی نشده است.');
        }

        $url = $cfg['base_url'] . '/skyroom/api/' . $cfg['api_key'];

        $response = Http::timeout(20)
            ->asJson()
            ->post($url, ['action' => $action, 'params' => $params]);

        $payload = $response->json();

        if (! is_array($payload) || ! ($payload['ok'] ?? false)) {
            $msg = $payload['error_message'] ?? ($response->body() ?: 'نامشخص');
            throw new RuntimeException("فراخوانی Skyroom ({$action}) ناموفق بود: {$msg}");
        }

        return $payload['result'] ?? null;
    }

    public function createRoom(OnlineClass $online): string
    {
        // اتاق‌های Skyroom پایدارند؛ اگر قبلاً ساخته‌ایم، همان را بازمی‌گردانیم.
        if ($online->provider_room_id) {
            try {
                $this->call('getRoom', ['room_id' => (int) $online->provider_room_id]);

                return $online->provider_room_id;
            } catch (RuntimeException) {
                // اتاق دیگر وجود ندارد؛ دوباره می‌سازیم.
            }
        }

        $name = 'class-' . Str::slug(Str::substr((string) $online->school_class_id, 0, 8));

        $roomId = $this->call('createRoom', [
            'name'           => $name,
            'title'          => $online->title ?: ($online->schoolClass->name ?? 'کلاس آنلاین'),
            'op_login_first' => true,
            'max_users'      => 50,
            'guest_login'    => false,
        ]);

        return (string) $roomId;
    }

    public function getJoinUrl(OnlineClass $online, User $user, bool $moderator): string
    {
        $url = $this->call('createLoginUrl', [
            'room_id'   => (int) $online->provider_room_id,
            'user_id'   => $user->id,
            'nickname'  => $user->display_name ?: $user->mobile_e164,
            'access'    => $moderator ? 3 : 1,
            'concurrent'=> 1,
            'ttl'       => 3600,
            'language'  => 'fa',
        ]);

        return (string) $url;
    }

    public function endRoom(OnlineClass $online): void
    {
        // اتاق‌های Skyroom پایدارند و API عمومی «end» مستقلی ندارند؛
        // پایان جلسه از طریق خروج اپراتور و به‌روزرسانی وضعیت در DB ثبت می‌شود.
    }

    public function isRunning(OnlineClass $online): bool
    {
        return (bool) $online->provider_room_id;
    }
}
