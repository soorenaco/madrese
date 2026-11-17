# ورود وب (Inertia + Vue) — ماژول Auth

**نسخه:** 1.0  
**تفاوت با API:** ورود وب با **session guard (`web`)** انجام می‌شود و هیچ توکن Sanctum ساخته نمی‌شود. توکن فقط برای API/موبایل است (`docs/AUTH_API_DOCUMENTATION.md`).

## فهرست مسیرها

| Method | مسیر | Middleware | Inertia component | توضیح |
|--------|------|------------|-------------------|-------|
| GET | `/login` | `guest` | `Auth/Login` | صفحه‌ی ورود |
| POST | `/login` | `guest`، `throttle:auth-login-ip`، `throttle:auth-login` | – (redirect) | ارسال OTP |
| POST | `/login/verify` | `guest`، `throttle:auth-verify` | – (redirect) | تأیید OTP و ورود |
| GET | `/dashboard` | `auth` | `Dashboard` | داشبورد اولیه |
| POST | `/logout` | `auth` | – (redirect) | خروج |

کاربر واردشده که `/login` را باز کند به `/dashboard` و مهمانِ `/dashboard` به `/login` هدایت می‌شود (`redirectUsersTo` / `redirectGuestsTo` در `bootstrap/app.php`).

## ۱. `POST /login` — ارسال OTP

| پارامتر | نوع | الزامی | مثال | توضیح |
|---------|-----|--------|------|-------|
| `mobile` | string | بله | `09123456789` | همان قوانین API: `09xxxxxxxxx` / `+989xxxxxxxxx` / `9xxxxxxxxx` |

**موفق:** redirect به `/login` با flash در session:
- `otp_step = "otp"`
- `otp_mobile` = شماره نرمال‌شده (`+989...`)
- `otp_dev` = کد OTP (فقط در `APP_ENV=local`)

این flash با `HandleInertiaRequests` به شکل `props.flash` به صفحه می‌رسد.

**خطا:** خطای اعتبارسنجی روی `mobile` (فرمت نامعتبر، حساب مسدود، cooldown ۶۰ ثانیه‌ای). **`429`** (JSON) از limiter؛ فرانت آن را با رویداد `invalid` Inertia می‌گیرد و پیام فارسی نشان می‌دهد.

## ۲. `POST /login/verify` — تأیید OTP

| پارامتر | نوع | الزامی | توضیح |
|---------|-----|--------|-------|
| `mobile` | string | بله | شماره‌ی نرمال‌شده از مرحله‌ی قبل |
| `code` | string | بله | ۴ تا ۶ رقم (OTP فعلی ۵ رقمی) |

**موفق:** `Auth::guard('web')->login($user)`، تعویض session id، redirect به `/dashboard`. کاربر `invited` به `active` تبدیل و `last_login_at` به‌روز می‌شود.  
**خطا:** خطای `code` (کد اشتباه/منقضی، حداکثر ۵ تلاش) یا `429`.

## ۳. `GET /dashboard`

props: `user.id`، `user.mobile`، `user.display_name`، `user.status`.

## ۴. `POST /logout`
`logout` guard وب، `invalidate` session و ساخت CSRF جدید، redirect به `/login`.

## نکات پیاده‌سازی
- `AuthService::verifyCode()` فقط OTP را تأیید و کاربر را برمی‌گرداند (وب)؛ `verifyOtp()` همان را صدا می‌زند و توکن Sanctum می‌سازد (API).
- مرحله‌ی OTP در `Login.vue` به‌صورت state محلی نگه داشته می‌شود و `verify` با `preserveState: true` ارسال می‌شود تا با خطای کد، به مرحله‌ی موبایل برنگردد.
- limiterها همان limiterهای API هستند (کلیدشان IP و شماره).

## تست
`tests/Feature/WebAuthTest.php` (۱۱ تست: هر route و مسیرهای خطا).

## وضعیت
- [x] `php artisan test --filter=WebAuthTest`: `11 passed (47 assertions)` (۱۴۰۵/۰۸/۲۱).
- [x] کل پروژه: `33 passed (169 assertions)`؛ `npm run build` موفق (۵۸۰ module).
- [ ] بررسی دستی در مرورگر انجام شود.
