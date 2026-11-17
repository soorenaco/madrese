<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';

const page = usePage();

// وضعیت مرحله‌ها محلی است؛ flash سرور فقط یک بار بعد از ارسال OTP خوانده می‌شود.
const step = ref('mobile'); // mobile | otp
const normalizedMobile = ref('');
const devOtp = ref(null);
const cooldown = ref(0);
const throttleMessage = ref('');

const mobileForm = useForm({ mobile: '' });
const codeForm = useForm({ mobile: '', code: '' });

let timer = null;
function startCooldown(seconds = 60) {
    clearInterval(timer);
    cooldown.value = seconds;
    timer = setInterval(() => {
        cooldown.value -= 1;
        if (cooldown.value <= 0) clearInterval(timer);
    }, 1000);
}

function requestOtp(mobile = mobileForm.mobile) {
    throttleMessage.value = '';
    mobileForm.mobile = mobile;
    mobileForm.post('/login', {
        preserveScroll: true,
        onSuccess: () => {
            const flash = page.props.flash || {};
            if (flash.otp_step === 'otp') {
                step.value = 'otp';
                normalizedMobile.value = flash.otp_mobile;
                devOtp.value = flash.otp_dev ?? null;
                codeForm.reset('code');
                startCooldown(60);
            }
        },
    });
}

function verifyOtp() {
    throttleMessage.value = '';
    codeForm.mobile = normalizedMobile.value;
    // preserveState: در صورت خطا، همین مرحله (otp) باقی بماند
    codeForm.post('/login/verify', { preserveState: true });
}

function backToMobile() {
    step.value = 'mobile';
    codeForm.reset();
    codeForm.clearErrors();
    devOtp.value = null;
}

// پاسخ 429 (JSON) از limiter غیر Inertia است؛ به‌جای modal خطا، پیام فارسی نشان می‌دهیم.
let removeInvalid = null;
onMounted(() => {
    removeInvalid = router.on('invalid', (event) => {
        if (event.detail.response.status === 429) {
            event.preventDefault();
            throttleMessage.value =
                event.detail.response.data?.message ?? 'تعداد درخواست‌ها بیش از حد مجاز است. لطفاً یک دقیقه صبر کنید.';
        }
    });
});
onUnmounted(() => {
    clearInterval(timer);
    if (removeInvalid) removeInvalid();
});
</script>

<template>
    <div class="min-h-screen flex flex-col items-center justify-center px-4 py-10 bg-gradient-to-br from-violet-50 via-white to-amber-50">
        <div class="w-full max-w-md">
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-violet-700 text-white text-2xl font-black shadow-lg shadow-violet-200 mb-4">م</div>
                <h1 class="text-2xl font-extrabold text-violet-950">مدرصه</h1>
                <p class="text-sm text-slate-500 mt-1">ورود به سامانه هوشمند آموزشی</p>
            </div>

            <div class="bg-white/90 rounded-3xl shadow-xl shadow-violet-100 border border-violet-100 p-6 sm:p-8">
                <p v-if="throttleMessage" class="mb-4 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm px-3 py-2">
                    {{ throttleMessage }}
                </p>

                <!-- مرحله: موبایل -->
                <form v-if="step === 'mobile'" class="space-y-5" @submit.prevent="requestOtp()">
                    <h2 class="text-lg font-bold text-slate-900">ورود با شماره موبایل</h2>
                    <p class="text-sm text-slate-500">کد تأیید به شماره شما ارسال می‌شود.</p>
                    <div>
                        <label for="mobile" class="block text-sm font-medium text-slate-700 mb-1.5">شماره موبایل</label>
                        <input id="mobile" v-model="mobileForm.mobile" type="tel" inputmode="numeric" dir="ltr" autofocus
                               placeholder="09123456789"
                               class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-base focus:border-violet-500 focus:ring-2 focus:ring-violet-200 outline-none transition">
                        <p v-if="mobileForm.errors.mobile" class="mt-1.5 text-sm text-red-600">{{ mobileForm.errors.mobile }}</p>
                    </div>
                    <button type="submit" :disabled="mobileForm.processing"
                            class="w-full rounded-xl bg-violet-700 hover:bg-violet-800 disabled:opacity-60 text-white font-bold py-3.5 transition">
                        {{ mobileForm.processing ? 'در حال ارسال...' : 'دریافت کد تأیید' }}
                    </button>
                </form>

                <!-- مرحله: OTP -->
                <form v-else class="space-y-5" @submit.prevent="verifyOtp()">
                    <h2 class="text-lg font-bold text-slate-900">کد تأیید را وارد کنید</h2>
                    <p class="text-sm text-slate-500">
                        کد ۵ رقمی به
                        <span class="font-mono font-semibold text-violet-800" dir="ltr">{{ normalizedMobile }}</span>
                        ارسال شد.
                    </p>
                    <p v-if="devOtp" class="rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-sm px-3 py-2">
                        محیط توسعه — کد: <strong class="font-mono text-base">{{ devOtp }}</strong>
                    </p>
                    <div>
                        <label for="code" class="block text-sm font-medium text-slate-700 mb-1.5">کد تأیید</label>
                        <input id="code" v-model="codeForm.code" type="text" inputmode="numeric" maxlength="5" dir="ltr" autofocus
                               placeholder="-----"
                               class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-center text-2xl tracking-[0.4em] font-mono focus:border-violet-500 focus:ring-2 focus:ring-violet-200 outline-none transition">
                        <p v-if="codeForm.errors.code" class="mt-1.5 text-sm text-red-600">{{ codeForm.errors.code }}</p>
                        <p v-else-if="codeForm.errors.mobile" class="mt-1.5 text-sm text-red-600">{{ codeForm.errors.mobile }}</p>
                    </div>
                    <button type="submit" :disabled="codeForm.processing"
                            class="w-full rounded-xl bg-violet-700 hover:bg-violet-800 disabled:opacity-60 text-white font-bold py-3.5 transition">
                        {{ codeForm.processing ? 'در حال بررسی...' : 'ورود' }}
                    </button>
                    <div class="flex items-center justify-between text-sm">
                        <button type="button" class="text-slate-500 hover:text-violet-700" @click="backToMobile">تغییر شماره</button>
                        <button type="button" :disabled="cooldown > 0 || mobileForm.processing"
                                class="text-violet-700 font-medium disabled:text-slate-400 disabled:cursor-not-allowed"
                                @click="requestOtp(normalizedMobile)">
                            {{ cooldown > 0 ? `ارسال مجدد (${cooldown}ث)` : 'ارسال مجدد کد' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
