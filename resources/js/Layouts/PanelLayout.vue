<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useMenu } from '../composables/useMenu';
import Icon from '../Components/Icon.vue';
import FlashMessages from '../Components/Ui/FlashMessages.vue';
import ToastContainer from '../Components/Ui/ToastContainer.vue';

const page = usePage();
const { items, isActive } = useMenu();

const auth = computed(() => page.props.auth);
const user = computed(() => auth.value?.user ?? null);
const roles = computed(() => auth.value?.roles ?? []);
const unreadCount = computed(() => auth.value?.unread_notifications_count ?? 0);

const sidebarOpen = ref(false);
const navigating = ref(false);

function logout() {
    router.post('/logout');
}

function closeSidebar() {
    sidebarOpen.value = false;
}

// نوار پیشرفت Inertia
let unbindStart, unbindFinish;
onMounted(() => {
    unbindStart = router.on('start', () => (navigating.value = true));
    unbindFinish = router.on('finish', () => (navigating.value = false));
    // بستن سایدبار با Esc روی موبایل
    document.addEventListener('keydown', onEsc);
});
onUnmounted(() => {
    document.removeEventListener('keydown', onEsc);
});
function onEsc(e) {
    if (e.key === 'Escape') sidebarOpen.value = false;
}

// نقشه‌ی آیکون منو به آیکون‌ست
const iconMap = {
    home: 'home',
    class: 'class',
    school: 'building',
    users: 'users',
    settings: 'settings',
    video: 'video',
    'check-circle': 'check-circle',
    calendar: 'calendar',
    bell: 'bell',
};

const greeting = computed(() => {
    const h = new Date().getHours();
    if (h < 12) return 'صبح بخیر';
    if (h < 18) return 'ظهر بخیر';
    return 'عصر بخیر';
});
</script>

<template>
    <div class="min-h-screen text-slate-800" dir="rtl">
        <!-- نوار پیشرفت بالای صفحه -->
        <div
            class="fixed inset-x-0 top-0 z-[80] h-1 origin-right bg-gradient-to-l from-brand-500 to-brand-300 transition-transform duration-300"
            :class="navigating ? 'scale-x-100' : 'scale-x-0'"
            aria-hidden="true"
        />

        <!-- بنر فلش + toast سراسری -->
        <FlashMessages />
        <ToastContainer />

        <!-- هدر موبایل -->
        <header class="clay-card-flat sticky top-0 z-30 mx-3 mt-3 rounded-clay-sm px-4 py-2.5 lg:hidden">
            <div class="flex items-center justify-between gap-3">
                <button
                    type="button"
                    class="clay-btn-secondary flex h-9 w-9 items-center justify-center !p-0"
                    @click="sidebarOpen = !sidebarOpen"
                    aria-label="باز کردن منو"
                >
                    <Icon :name="sidebarOpen ? 'close' : 'menu'" :size="20" />
                </button>
                <div class="font-semibold text-sm truncate">
                    {{ user?.display_name || 'پنل' }}
                </div>
                <div class="flex items-center gap-1">
                    <Link href="/notifications" class="relative flex h-9 w-9 items-center justify-center" aria-label="اعلان‌ها">
                        <Icon name="bell" :size="20" />
                        <span
                            v-if="unreadCount > 0"
                            class="absolute -top-0.5 -left-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-danger-500 px-1 text-[9px] font-bold text-white"
                        >{{ unreadCount > 9 ? '۹+' : unreadCount }}</span>
                    </Link>
                    <button
                        type="button"
                        class="clay-btn-ghost flex items-center gap-1.5 px-2 py-1.5 text-sm"
                        @click="logout"
                    >
                        <Icon name="logout" :size="18" />
                    </button>
                </div>
            </div>
        </header>

        <!-- هدر دسکتاپ -->
        <header class="hidden lg:flex sticky top-0 z-30 items-center justify-between gap-4 border-b border-clay-200/70 bg-clay-50/80 px-8 py-3 backdrop-blur">
            <div class="text-sm font-semibold text-slate-600">{{ greeting }}، {{ user?.display_name || 'کاربر' }} 👋</div>
            <div class="flex items-center gap-2">
                <Link
                    href="/notifications"
                    class="clay-card-flat relative flex h-10 w-10 items-center justify-center text-slate-600 transition hover:text-brand-600"
                    aria-label="اعلان‌ها"
                >
                    <Icon name="bell" :size="20" />
                    <span
                        v-if="unreadCount > 0"
                        class="absolute -top-1 -left-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-danger-500 px-1.5 text-[10px] font-bold text-white"
                    >{{ unreadCount > 9 ? '۹+' : unreadCount }}</span>
                </Link>
                <button
                    type="button"
                    class="clay-btn-ghost flex items-center gap-1.5 px-3 py-2 text-sm"
                    @click="logout"
                >
                    <Icon name="logout" :size="16" />
                    خروج
                </button>
            </div>
        </header>

        <div class="lg:flex lg:min-h-screen">
            <!-- overlay موبایل -->
            <Transition
                enter-active-class="transition duration-200"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition duration-150"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="sidebarOpen"
                    class="fixed inset-0 z-40 bg-brand-950/40 backdrop-blur-sm lg:hidden"
                    @click="closeSidebar"
                />
            </Transition>

            <!-- سایدبار -->
            <aside
                class="fixed lg:sticky top-0 right-0 z-50 lg:z-20 h-full lg:h-screen w-64 flex flex-col transition-transform duration-300 lg:translate-x-0"
                :class="sidebarOpen ? 'translate-x-0' : 'translate-x-full lg:translate-x-0'"
            >
                <div class="clay-card m-3 flex h-[calc(100%-1.5rem)] flex-col overflow-hidden p-3">
                    <!-- برند -->
                    <div class="flex items-center gap-3 px-2 py-2">
                        <div class="clay-btn-primary flex h-11 w-11 items-center justify-center text-lg font-black !p-0">
                            م
                        </div>
                        <div class="min-w-0">
                            <div class="font-bold text-sm truncate">مدرسه</div>
                            <div class="text-[11px] text-slate-400 truncate">سامانه مدیریت</div>
                        </div>
                    </div>

                    <!-- منو -->
                    <nav class="no-scrollbar mt-2 flex-1 space-y-1 overflow-y-auto px-1 py-2">
                        <Link
                            v-for="item in items"
                            :key="item.href"
                            :href="item.href"
                            class="clay-nav-item flex items-center gap-3 px-3 py-2.5 text-sm"
                            :class="isActive(item.href)
                                ? 'clay-nav-item-active'
                                : 'text-slate-600'"
                            @click="closeSidebar"
                        >
                            <Icon
                                :name="iconMap[item.icon] || 'home'"
                                :size="18"
                                :stroke-width="isActive(item.href) ? 2.25 : 1.75"
                            />
                            <span class="truncate">{{ item.label }}</span>
                        </Link>
                    </nav>

                    <!-- کاربر + خروج -->
                    <div class="clay-card-inset mt-2 p-3">
                        <div class="flex items-center gap-3">
                            <div class="clay-card-flat flex h-10 w-10 shrink-0 items-center justify-center text-sm font-bold text-brand-700">
                                {{ (user?.display_name || '؟').charAt(0) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold truncate">{{ user?.display_name || 'کاربر' }}</div>
                                <div v-if="roles.length" class="flex flex-wrap gap-1 mt-0.5">
                                    <span
                                        v-for="role in roles.slice(0, 2)"
                                        :key="role.code"
                                        class="clay-chip text-[10px] px-1.5 py-0.5 text-brand-700"
                                    >
                                        {{ role.title }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="clay-btn-secondary mt-3 w-full !py-2 text-xs"
                            @click="logout"
                        >
                            <Icon name="logout" :size="16" />
                            خروج از حساب
                        </button>
                    </div>
                </div>
            </aside>

            <!-- محتوا -->
            <main class="flex-1 min-w-0">
                <div class="mx-auto max-w-6xl px-3 py-5 sm:px-5 lg:px-8 lg:py-8">
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>
