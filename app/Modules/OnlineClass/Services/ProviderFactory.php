<?php

namespace App\Modules\OnlineClass\Services;

use App\Modules\OnlineClass\Models\OnlineClass;
use App\Modules\OnlineClass\Models\SystemSetting;
use App\Modules\OnlineClass\Services\Providers\BbbProvider;
use App\Modules\OnlineClass\Services\Providers\MeetingProvider;
use App\Modules\OnlineClass\Services\Providers\SkyroomProvider;

/**
 * انتخاب ارائه‌دهندهٔ جلسه بر اساس فیلد provider کلاس آنلاین
 * و بررسی پیکربندی‌بودن آن توسط ادمین.
 */
class ProviderFactory
{
    /**
     * بازگرداندن ارائه‌دهندهٔ مناسب برای یک کلاس آنلاین.
     */
    public function for(OnlineClass $online): MeetingProvider
    {
        return match ($online->provider) {
            'bbb'      => app(BbbProvider::class),
            'skyroom'  => app(SkyroomProvider::class),
            default    => throw new \RuntimeException("ارائه‌دهندهٔ ناشناخته: {$online->provider}"),
        };
    }

    /**
     * آیا ارائه‌دهندهٔ داده‌شده توسط ادمین پیکربندی شده است؟
     */
    public function isConfigured(string $provider): bool
    {
        return match ($provider) {
            'bbb'     => $this->bbbConfigured(),
            'skyroom' => $this->skyroomConfigured(),
            default   => false,
        };
    }

    protected function bbbConfigured(): bool
    {
        $cfg = SystemSetting::get('bbb', []) ?? [];

        $baseUrl = (string) ($cfg['base_url'] ?? env('BBB_BASE_URL', ''));
        $secret  = (string) ($cfg['secret'] ?? env('BBB_SECRET', ''));

        return $baseUrl !== '' && $secret !== '';
    }

    protected function skyroomConfigured(): bool
    {
        $cfg = SystemSetting::get('skyroom', []) ?? [];

        $apiKey = (string) ($cfg['api_key'] ?? env('SKYROOM_API_KEY', ''));

        return $apiKey !== '';
    }
}
