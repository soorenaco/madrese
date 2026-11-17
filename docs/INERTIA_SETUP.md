# راه‌اندازی Inertia + Vue (مرحله ۱)

## هدف
زیرساخت پنل وب (محیط مدیریت و کاربری). در این مرحله فقط رندر یک صفحه‌ی آزمایشی تأیید می‌شود. لاگین Livewire فعلی دست‌نخورده است.

## پکیج‌ها
- PHP: `inertiajs/inertia-laravel`
- JS: `vue`، `@inertiajs/vue3`، `@vitejs/plugin-vue`

## فایل‌ها
| فایل | نقش |
|------|-----|
| `resources/views/app.blade.php` | قالب ریشه (`lang="fa" dir="rtl"`، `@inertia`) |
| `resources/js/inertia.js` | ورودی Vite برای Inertia (جدا از `app.js` تا با Livewire تداخل نکند) |
| `resources/js/Pages/*.vue` | صفحه‌ها؛ نام فایل = نام کامپوننت در `Inertia::render('Name')` |
| `app/Http/Middleware/HandleInertiaRequests.php` | props مشترک و نسخه‌ی asset؛ در گروه `web` ثبت شده |
| `vite.config.js` | پلاگین Vue و ورودی `resources/js/inertia.js` |

## ساخت صفحه‌ی جدید
1. فایل `resources/js/Pages/<Name>.vue` را بسازید (`defineProps` برای props).
2. در route: `return Inertia::render('<Name>', ['key' => 'value']);`
3. تست: `->assertInertia(fn ($page) => $page->component('<Name>')->where('key', 'value'))`، با `$this->withoutVite()` در ابتدای تست.

## Route آزمایشی
| Method | مسیر | خروجی |
|--------|------|-------|
| GET | `/inertia-ping` | کامپوننت `Ping` با prop برابر `status: "ok"`. با هدر `X-Inertia: true` پاسخ JSON می‌دهد. |

این route موقتی است و بعد از آماده شدن صفحه‌ی لاگین Inertia (مرحله ۲) حذف می‌شود.

## تست
`tests/Feature/InertiaSetupTest.php` (۲ تست: رندر HTML و پاسخ JSON درخواست Inertia).

## وضعیت
- [ ] نتیجه‌ی اجرای تست‌ها و `npm run build` ثبت شود.
