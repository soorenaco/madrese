<?php

namespace App\Modules\OnlineClass\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\OnlineClass\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * تنظیمات سراسری سامانه (پنل ادمین).
 * در حال حاضر: پیکربندی ارائه‌دهنده‌های کلاس آنلاین (BBB + Skyroom).
 */
class SystemSettingsController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Settings/Index', [
            'providers'       => $this->providerConfigForView(),
            'defaultProvider' => SystemSetting::get('online_class.default_provider', null),
        ]);
    }

    public function updateProviders(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'bbb.base_url'      => ['nullable', 'string', 'max:255'],
            'bbb.secret'        => ['nullable', 'string', 'max:255'],
            'bbb.checksum_algo' => ['nullable', 'in:sha1,sha256,sha384,sha512'],
            'skyroom.base_url'  => ['nullable', 'string', 'max:255'],
            'skyroom.api_key'   => ['nullable', 'string', 'max:100'],
            'default_provider'  => ['nullable', 'in:bbb,skyroom'],
        ]);

        $this->persistProvider('bbb', $data['bbb'] ?? [], ['base_url', 'secret', 'checksum_algo']);
        $this->persistProvider('skyroom', $data['skyroom'] ?? [], ['base_url', 'api_key']);

        if (array_key_exists('default_provider', $data)) {
            SystemSetting::set('online_class.default_provider', $data['default_provider']);
        }

        return back()->with('success', 'تنظیمات ارائه‌دهنده‌ها ذخیره شد.');
    }

    /**
     * ذخیرهٔ پیکربندی یک ارائه‌دهنده. secret/api_key اگر خالی ارسال شود،
     * مقدار قبلی حفظ می‌شود (تا ادمین مجبور به وارد کردن دوباره نباشد).
     */
    protected function persistProvider(string $key, array $submitted, array $fields): void
    {
        $current = SystemSetting::get($key, []) ?? [];

        foreach ($fields as $field) {
            $value = $submitted[$field] ?? null;

            $isSecret = $field === 'secret' || $field === 'api_key';

            if ($isSecret && ($value === null || $value === '')) {
                continue;
            }

            $current[$field] = $value;
        }

        SystemSetting::set($key, $current);
    }

    /** مقدارهای غیرمحرم + وضعیت «تنظیم‌شده» بودن secretها (برای نمایش در فرم). */
    protected function providerConfigForView(): array
    {
        $bbb = SystemSetting::get('bbb', []) ?? [];
        $sky = SystemSetting::get('skyroom', []) ?? [];

        return [
            'bbb' => [
                'base_url'      => $bbb['base_url'] ?? '',
                'checksum_algo' => $bbb['checksum_algo'] ?? 'sha1',
                'secret_set'    => ! empty($bbb['secret']),
            ],
            'skyroom' => [
                'base_url'    => $sky['base_url'] ?? 'https://www.skyroom.online',
                'api_key_set' => ! empty($sky['api_key']),
            ],
        ];
    }
}
