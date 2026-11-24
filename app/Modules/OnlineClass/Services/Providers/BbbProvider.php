<?php

namespace App\Modules\OnlineClass\Services\Providers;

use App\Modules\Auth\Models\User;
use App\Modules\OnlineClass\Models\OnlineClass;
use App\Modules\OnlineClass\Models\SystemSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * کلاینت BigBlueButton API.
 *
 * امضای هر فراخوانی: checksum = hash(algo, callName + queryString + sharedSecret)
 * سرور و secret از SystemSetting «bbb» (یا env BBB_*) خوانده می‌شود.
 * همهٔ فراخوانی‌ها باید سمت سرور (Laravel) انجام شوند؛ secret هرگز به مرورگر نمی‌رود.
 */
class BbbProvider implements MeetingProvider
{
    /** @return array{base_url:string, secret:string, algo:string} */
    protected function config(): array
    {
        $stored = SystemSetting::get('bbb', []) ?? [];

        return [
            'base_url' => (string) ($stored['base_url'] ?? env('BBB_BASE_URL', '')),
            'secret'   => (string) ($stored['secret'] ?? env('BBB_SECRET', '')),
            'algo'     => (string) ($stored['checksum_algo'] ?? env('BBB_CHECKSUM_ALGO', 'sha1')),
        ];
    }

    /**
     * اجرای یک فراخوانی BBB و بازگرداندن پاسخ به‌صورت آرایه.
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    protected function call(string $action, array $params): array
    {
        $cfg = $this->config();

        if ($cfg['base_url'] === '' || $cfg['secret'] === '') {
            throw new RuntimeException('BigBlueButton توسط ادمین پیکربندی نشده است.');
        }

        $query = http_build_query($params);
        $checksum = hash($cfg['algo'], $action . $query . $cfg['secret']);
        $url = rtrim($cfg['base_url'], '/') . '/api/' . $action . '?' . $query . '&checksum=' . $checksum;

        $body = Http::timeout(20)->get($url)->body();

        $xml = @simplexml_load_string($body);
        if ($xml === false) {
            throw new RuntimeException('پاسخ BigBlueButton نامعتبر است.');
        }

        $decoded = json_decode(json_encode($xml), true);

        return is_array($decoded) ? $decoded : [];
    }

    public function createRoom(OnlineClass $online): string
    {
        // create در BBB idempotent است؛ اگر meetingID داشته‌ایم از همان استفاده می‌کنیم.
        $meetingId = $online->provider_room_id ?: (string) Str::uuid();

        $result = $this->call('create', [
            'name'       => $online->title ?: ($online->schoolClass->name ?? 'کلاس آنلاین'),
            'meetingID'  => $meetingId,
            'record'     => $online->record ? 'true' : 'false',
            'logoutURL'  => rtrim((string) env('APP_URL', url('/')), '/') . '/classes/' . $online->school_class_id . '/online',
        ]);

        if (($result['returncode'] ?? '') !== 'SUCCESS') {
            throw new RuntimeException('ساخت جلسهٔ BBB ناموفق بود: ' . ($result['message'] ?? 'نامشخص'));
        }

        return $meetingId;
    }

    public function getJoinUrl(OnlineClass $online, User $user, bool $moderator): string
    {
        $cfg = $this->config();

        $params = [
            'meetingID' => $online->provider_room_id,
            'fullName'  => mb_substr((string) ($user->display_name ?: $user->mobile_e164), 0, 255),
            'role'      => $moderator ? 'MODERATOR' : 'VIEWER',
            'userID'    => $user->id,
            'redirect'  => 'true',
        ];

        $query = http_build_query($params);
        $checksum = hash($cfg['algo'], 'join' . $query . $cfg['secret']);

        return rtrim($cfg['base_url'], '/') . '/api/join?' . $query . '&checksum=' . $checksum;
    }

    public function endRoom(OnlineClass $online): void
    {
        if (! $online->provider_room_id) {
            return;
        }

        $this->call('end', ['meetingID' => $online->provider_room_id]);
    }

    public function isRunning(OnlineClass $online): bool
    {
        if (! $online->provider_room_id) {
            return false;
        }

        $result = $this->call('isMeetingRunning', ['meetingID' => $online->provider_room_id]);

        return ($result['running'] ?? 'false') === 'true';
    }
}
