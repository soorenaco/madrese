/**
 * تبدیل تاریخ میلادی به شمسی (Jalali) بدون وابستگی خارجی.
 * الگوریتم استاندارد تبدیل میلادی → شمسی (هجری شمسی).
 *
 * توجه: این فقط برای نمایش است. در DB تاریخ‌ها میلادی (Y-m-d) ذخیره می‌شوند.
 */

const J_DAYS_IN_MONTH = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];

/**
 * تبدیل تاریخ میلادی (Date یا 'Y-M-D') به [jy, jm, jd].
 * پیاده‌سازی الگوریتم PHP jdf / Birashk-compatible برای بازه‌ی ۱۹۰۰–۲۱۰۰.
 */
function gregorianToJalali(gy, gm, gd) {
    const g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    let jy = (gy <= 1600) ? 0 : 979;
    gy -= (gy <= 1600) ? 621 : 1600;
    const gy2 = (gm > 2) ? (gy + 1) : gy;
    let days = (365 * gy)
        + (Math.floor((gy2 + 3) / 4))
        - (Math.floor((gy2 + 99) / 100))
        + (Math.floor((gy2 + 399) / 400))
        - 80
        + gd
        + g_d_m[gm - 1];
    jy += 33 * Math.floor(days / 12053);
    days %= 12053;
    jy += 4 * Math.floor(days / 1461);
    days %= 1461;
    if (days > 365) {
        jy += Math.floor((days - 1) / 365);
        days = (days - 1) % 365;
    }
    const jm = (days < 186) ? 1 + Math.floor(days / 31) : 7 + Math.floor((days - 186) / 30);
    const jd = 1 + ((days < 186) ? (days % 31) : ((days - 186) % 30));
    return [jy, jm, jd];
}

/**
 * تبدیل تاریخ شمسی به میلادی. [jy, jm, jd] -> [gy, gm, gd]
 */
function jalaliToGregorian(jy, jm, jd) {
    let gy = (jy <= 979) ? 621 : 1600;
    jy -= (jy <= 979) ? 0 : 979;
    let days = (365 * jy)
        + (Math.floor(jy / 33) * 8)
        + (Math.floor((jy % 33 + 3) / 4))
        + 78
        + jd
        + ((jm < 7) ? (jm - 1) * 31 : ((jm - 7) * 30) + 186);
    gy += 400 * Math.floor(days / 146097);
    days %= 146097;
    if (days > 36524) {
        gy += 100 * Math.floor(--days / 36524);
        days %= 36524;
        if (days >= 365) days++;
    }
    gy += 4 * Math.floor(days / 1461);
    days %= 1461;
    if (days > 365) {
        gy += Math.floor((days - 1) / 365);
        days = (days - 1) % 365;
    }
    let gd = days + 1;
    const sal_a = [0, 31, ((gy % 4 === 0 && gy % 100 !== 0) || (gy % 400 === 0)) ? 29 : 28,
        31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    let gm = 0;
    for (gm = 0; gm < 13 && gd > sal_a[gm]; gm++) gd -= sal_a[gm];
    return [gy, gm, gd + 1];
}

const PERSIAN_MONTHS = [
    'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
    'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند',
];
const PERSIAN_WEEKDAYS = [
    'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه',
];

function toPersianDigits(input) {
    const fa = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    return String(input).replace(/\d/g, (d) => fa[+d]);
}

function pad2(n) {
    return String(n).padStart(2, '0');
}

/**
 * پارس 'Y-M-D' (میلادی) -> Date.
 */
function parseIso(dateStr) {
    if (dateStr instanceof Date) return dateStr;
    const [y, m, d] = String(dateStr).split('-').map(Number);
    return new Date(y, m - 1, d);
}

export function useJalali() {
    /**
     * 'Y-M-D' میلادی -> 'Y-M-D' شمسی (با ارقام لاتین، برای ذخیره/کلید).
     */
    function toJalaliIso(dateStr) {
        const dt = parseIso(dateStr);
        const [jy, jm, jd] = gregorianToJalali(dt.getFullYear(), dt.getMonth() + 1, dt.getDate());
        return `${jy}-${pad2(jm)}-${pad2(jd)}`;
    }

    /**
     * 'Y-M-D' شمسی -> 'Y-M-D' میلادی (برای ارسال به سرور).
     */
    function fromJalaliIso(jalaliStr) {
        const [jy, jm, jd] = String(jalaliStr).split('-').map(Number);
        const [gy, gm, gd] = jalaliToGregorian(jy, jm, jd);
        return `${gy}-${pad2(gm)}-${pad2(gd)}`;
    }

    /**
     * نمایش کامل فارسی: «یکشنبه ۵ آذر ۱۴۰۵»
     */
    function formatJalali(dateStr, opts = {}) {
        if (!dateStr) return '';
        const dt = parseIso(dateStr);
        const [jy, jm, jd] = gregorianToJalali(dt.getFullYear(), dt.getMonth() + 1, dt.getDate());
        const weekday = PERSIAN_WEEKDAYS[dt.getDay()];
        const day = opts.digits === false ? jd : toPersianDigits(jd);
        const month = PERSIAN_MONTHS[jm - 1];
        const year = opts.digits === false ? jy : toPersianDigits(jy);
        return `${weekday} ${day} ${month} ${year}`;
    }

    /**
     * نمایش کوتاه: «۱۴۰۵/۰۹/۰۵»
     */
    function formatJalaliShort(dateStr) {
        if (!dateStr) return '';
        const dt = parseIso(dateStr);
        const [jy, jm, jd] = gregorianToJalali(dt.getFullYear(), dt.getMonth() + 1, dt.getDate());
        return toPersianDigits(`${jy}/${pad2(jm)}/${pad2(jd)}`);
    }

    /**
     * نام روز هفته به فارسی.
     */
    function weekdayName(dateStr) {
        const dt = parseIso(dateStr);
        return PERSIAN_WEEKDAYS[dt.getDay()];
    }

    /**
     * امروز به‌صورت 'Y-M-D' میلادی.
     */
    function todayIso() {
        const dt = new Date();
        return `${dt.getFullYear()}-${pad2(dt.getMonth() + 1)}-${pad2(dt.getDate())}`;
    }

    /**
     * تولید N روز اخیر به‌صورت 'Y-M-D' میلادی (برای انتخاب سریع).
     */
    function recentDays(n = 14) {
        const out = [];
        const dt = new Date();
        for (let i = 0; i < n; i++) {
            out.push(`${dt.getFullYear()}-${pad2(dt.getMonth() + 1)}-${pad2(dt.getDate())}`);
            dt.setDate(dt.getDate() - 1);
        }
        return out;
    }

    return {
        toJalaliIso,
        fromJalaliIso,
        formatJalali,
        formatJalaliShort,
        weekdayName,
        todayIso,
        recentDays,
        toPersianDigits,
    };
}
