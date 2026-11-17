<script setup>
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useMenu } from '../composables/useMenu';

const page = usePage();
const { items, isActive } = useMenu();

const auth = computed(() => page.props.auth);
const user = computed(() => auth.value?.user ?? null);
const roles = computed(() => auth.value?.roles ?? []);

const sidebarOpen = ref(false);

function logout() {
    router.post('/logout');
}

function closeSidebar() {
    sidebarOpen.value = false;
}
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-800" dir="rtl">
        <!-- هدر موبایل -->
        <header class="bg-white border-b border-slate-200 sticky top-0 z-30 lg:hidden">
            <div class="px-4 h-14 flex items-center justify-between gap-3">
                <button
                    type="button"
                    class="w-9 h-9 rounded-lg border border-slate-200 flex items-center justify-center text-slate-600"
                    @click="sidebarOpen = !sidebarOpen"
                    aria-label="منو"
                >
                    <span class="text-lg leading-none">☰</span>
                </button>
                <div class="font-semibold text-sm truncate">
                    {{ user?.display_name || 'پنل' }}
                </div>
                <button
                    type="button"
                    class="text-sm text-slate-500 hover:text-violet-700"
                    @click="logout"
                >
                    خروج
                </button>
            </div>
        </header>

        <div class="lg:flex lg:min-h-screen">
            <!-- overlay موبایل -->
            <div
                v-if="sidebarOpen"
                class="fixed inset-0 bg-black/30 z-40 lg:hidden"
                @click="closeSidebar"
            />

            <!-- سایدبار -->
            <aside
                class="fixed lg:sticky top-0 right-0 z-50 lg:z-20 h-full lg:h-screen w-64 bg-white border-l border-slate-200 flex flex-col transition-transform duration-200 lg:translate-x-0"
                :class="sidebarOpen ? 'translate-x-0' : 'translate-x-full lg:translate-x-0'"
            >
                <!-- برند -->
                <div class="h-14 px-4 border-b border-slate-100 flex items-center gap-3 shrink-0">
                    <div class="w-9 h-9 rounded-xl bg-violet-700 text-white flex items-center justify-center text-sm font-black">
                        م
                    </div>
                    <div class="min-w-0">
                        <div class="font-bold text-sm truncate">مدرسه</div>
                        <div class="text-[11px] text-slate-400 truncate">پنل کاربری</div>
                    </div>
                </div>

                <!-- منو -->
                <nav class="flex-1 overflow-y-auto p-3 space-y-1">
                    <Link
                        v-for="item in items"
                        :key="item.href"
                        :href="item.href"
                        class="flex items-center gap-2 px-3 py-2.5 rounded-xl text-sm transition-colors"
                        :class="isActive(item.href)
                            ? 'bg-violet-50 text-violet-800 font-semibold'
                            : 'text-slate-600 hover:bg-slate-50'"
                        @click="closeSidebar"
                    >
                        <span
                            class="w-1.5 h-1.5 rounded-full shrink-0"
                            :class="isActive(item.href) ? 'bg-violet-600' : 'bg-slate-300'"
                        />
                        {{ item.label }}
                    </Link>
                </nav>

                <!-- کاربر + خروج -->
                <div class="border-t border-slate-100 p-3 shrink-0">
                    <div class="px-2 mb-2">
                        <div class="text-sm font-medium truncate">
                            {{ user?.display_name || 'کاربر' }}
                        </div>
                        <div v-if="roles.length" class="flex flex-wrap gap-1 mt-1">
                            <span
                                v-for="role in roles"
                                :key="role.code"
                                class="inline-block text-[10px] px-1.5 py-0.5 rounded-md bg-violet-50 text-violet-700 border border-violet-100"
                            >
                                {{ role.title }}
                            </span>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="w-full text-right px-3 py-2 rounded-xl text-sm text-slate-500 hover:bg-slate-50 hover:text-violet-700 transition-colors"
                        @click="logout"
                    >
                        خروج
                    </button>
                </div>
            </aside>

            <!-- محتوا -->
            <main class="flex-1 min-w-0">
                <div class="max-w-5xl mx-auto px-4 py-6 lg:py-8">
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>