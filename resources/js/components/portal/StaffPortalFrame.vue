
<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { ChevronRight, LogOut, Menu, X } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';

import AppearanceTabs from '@/components/AppearanceTabs.vue';
import AppLogo from '@/components/AppLogo.vue';
import NotificationBell from '@/components/notifications/NotificationBell.vue';
import SeaWaves from '@/components/SeaWaves.vue';
import { Toaster } from '@/components/ui/sonner';
import type { NavItem } from '@/types';

const props = defineProps<{
    title: string;
    subtitle: string;
    homeHref: string;
    items: NavItem[];
    // Keep this prop for compatibility with both portal layouts.
    variant: 'superadmin' | 'operator';
}>();

const page = usePage();

const mobileMenuOpen = ref(false);
const loggingOut = ref(false);
const currentHash = ref('');
const openGroups = ref<Record<string, boolean>>({});

const currentPath = computed(
    () => page.url.split('?')[0]?.split('#')[0] ?? page.url
);

const accountName = computed(
    () => page.props.auth?.user?.name ?? 'Staff account'
);

function pathOnly(href: string): string {
    return href.split('#')[0] ?? href;
}

function hashOnly(href: string): string {
    const parts = href.split('#');

    return parts.length > 1 ? `#${parts[1]}` : '';
}

function allNavPaths(): string[] {
    const paths: string[] = [];

    for (const item of props.items) {
        paths.push(pathOnly(String(item.href)));

        for (const subItem of item.items ?? []) {
            paths.push(pathOnly(subItem.href));
        }
    }

    return paths;
}

function isActive(href: string): boolean {
    const path = pathOnly(href);
    const hash = hashOnly(href);

    if (href === props.homeHref) {
        return currentPath.value === href;
    }

    /*
     * Prefer the longest matching nav link.
     * Example: /operator/bookings/calendar must highlight
     * Booking Calendar only, not Bookings.
     */
    const hasMoreSpecificMatch = allNavPaths().some((other) => {
        if (other === path || !other.startsWith(`${path}/`)) {
            return false;
        }

        return (
            currentPath.value === other
            || currentPath.value.startsWith(`${other}/`)
        );
    });

    if (hasMoreSpecificMatch) {
        return false;
    }

    const pathMatches =
        currentPath.value === path
        || currentPath.value.startsWith(`${path}/`);

    if (!pathMatches) {
        return false;
    }

    if (hash !== '') {
        return currentHash.value === hash;
    }

    return true;
}

function isGroupSectionActive(item: NavItem): boolean {
    const parentPath = pathOnly(String(item.href));

    return (
        currentPath.value === parentPath
        || currentPath.value.startsWith(`${parentPath}/`)
        || (item.items?.some((sub) => isActive(sub.href)) ?? false)
    );
}

function isGroupOpen(item: NavItem): boolean {
    const key = String(item.href);

    if (openGroups.value[key] !== undefined) {
        return openGroups.value[key];
    }

    return isGroupSectionActive(item);
}

function groupButtonClass(item: NavItem): string {
    /*
     * Solid cyan = exact current page only.
     * Soft cyan = this section is current (a child page).
     * Muted = accordion open, but user is still on another page.
     */
    if (isGroupSectionActive(item)) {
        return 'bg-[#00FFFF]/70 font-semibold text-cyan-950 shadow-sm';
    }

    if (isGroupOpen(item)) {
        return 'bg-white/60 font-medium text-cyan-900 dark:bg-white/10 dark:text-cyan-100';
    }

    return 'text-slate-600 hover:bg-white/80 hover:text-cyan-900 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white';
}

function toggleGroup(item: NavItem): void {
    const key = String(item.href);
    openGroups.value[key] = !isGroupOpen(item);
}

/**
 * Group parents only expand/collapse.
 * Section pages open from the child links (Boats, Weather, …).
 */
function visitGroup(item: NavItem, _closeMobile = false): void {
    toggleGroup(item);
}

function syncHash(): void {
    currentHash.value = window.location.hash;
}

onMounted(() => {
    syncHash();
    window.addEventListener('hashchange', syncHash);
});

onUnmounted(() => {
    window.removeEventListener('hashchange', syncHash);
});

function logout(): void {
    if (loggingOut.value) return;

    mobileMenuOpen.value = false;

    router.post('/logout', {}, {
        onStart: () => {
            loggingOut.value = true;
        },
        onFinish: () => {
            loggingOut.value = false;
        },
    });
}
</script>

<template>
    <!--
        SHARED STAFF LAYOUT

        Used by:
        - Super Admin
        - Tour Operator / Admin

        Both roles use the exact same visual styling.
        Tourist mobile layout is separate and unchanged.
    -->
    <div
        class="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100"
    >
        <!-- FIXED LEFT SIDEBAR -->
        <aside
            class="fixed inset-y-0 left-0 z-40 hidden w-[260px] flex-col overflow-hidden border-r border-cyan-100 bg-linear-to-b from-white via-cyan-50 to-cyan-100/80 text-slate-800 lg:flex dark:border-cyan-900/50 dark:from-slate-950 dark:via-cyan-950/30 dark:to-cyan-950/60 dark:text-slate-100"
        >
            <div
                aria-hidden="true"
                class="pointer-events-none absolute inset-x-0 bottom-0 h-36 overflow-hidden opacity-40"
            >
                <SeaWaves class="h-full w-full" />
            </div>

            <!-- SIDEBAR BRANDING -->
            <Link
                :href="homeHref"
                class="relative z-10 block shrink-0 border-b border-cyan-100/80 px-5 py-4 dark:border-cyan-900/40"
            >
                <AppLogo compact />

                <h1 class="mt-2 text-base font-bold text-slate-900 dark:text-slate-100">
                    {{ title }}
                </h1>

                <p class="mt-1 text-xs text-cyan-800/70 dark:text-cyan-200/70">
                    {{ subtitle }}
                </p>
            </Link>

            <!--
                INDEPENDENT SIDEBAR SCROLL

                - Sidebar remains fixed.
                - Only the navigation scrolls.
                - Same menu design for both staff roles.
                - No logout button in sidebar.
            -->
            <nav
                class="staff-nav relative z-10 min-h-0 flex-1 space-y-0.5 overflow-y-auto overscroll-contain px-3 py-3"
                aria-label="Staff portal navigation"
            >
                <template
                    v-for="item in items"
                    :key="String(item.href)"
                >
                    <div
                        v-if="item.items?.length"
                        class="space-y-1"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-left text-sm transition-colors"
                            :class="groupButtonClass(item)"
                            :aria-expanded="isGroupOpen(item)"
                            @click="visitGroup(item)"
                        >
                            <component
                                :is="item.icon"
                                v-if="item.icon"
                                class="size-4 shrink-0"
                            />

                            <span class="min-w-0 flex-1 break-words">
                                {{ item.title }}
                            </span>

                            <ChevronRight
                                class="size-4 shrink-0 transition-transform"
                                :class="isGroupOpen(item) ? 'rotate-90' : ''"
                            />
                        </button>

                        <div
                            v-if="isGroupOpen(item)"
                            class="ml-3 space-y-1 border-l border-cyan-200 pl-2 dark:border-cyan-800"
                        >
                            <Link
                                v-for="subItem in item.items"
                                :key="subItem.href"
                                :href="subItem.href"
                                class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm transition-colors"
                                :class="
                                    isActive(subItem.href)
                                        ? 'bg-[#00FFFF] font-semibold text-cyan-950 shadow-sm'
                                        : 'text-slate-600 hover:bg-white/80 hover:text-cyan-900 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white'
                                "
                                @click="syncHash"
                            >
                                <component
                                    :is="subItem.icon"
                                    v-if="subItem.icon"
                                    class="size-4 shrink-0"
                                />

                                <span class="min-w-0 break-words">
                                    {{ subItem.title }}
                                </span>
                            </Link>
                        </div>
                    </div>

                    <Link
                        v-else
                        :href="item.href"
                        class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm transition-colors"
                        :class="
                            isActive(String(item.href))
                                ? 'bg-[#00FFFF] font-semibold text-cyan-950 shadow-sm'
                                : 'text-slate-600 hover:bg-white/80 hover:text-cyan-900 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white'
                        "
                    >
                        <component
                            :is="item.icon"
                            v-if="item.icon"
                            class="size-4 shrink-0"
                        />

                        <span class="min-w-0 break-words">
                            {{ item.title }}
                        </span>
                    </Link>
                </template>
            </nav>
        </aside>

        <!-- MAIN PAGE AREA -->
        <div class="min-h-screen min-w-0 lg:pl-[260px]">

            <!-- SAME TOP HEADER FOR BOTH PORTALS -->
            <header
                class="sticky top-0 z-30 flex min-h-16 items-center gap-3 border-b border-cyan-100 bg-white/90 px-4 text-slate-900 backdrop-blur-md sm:px-6 dark:border-cyan-900/50 dark:bg-slate-950/90 dark:text-slate-100"
            >
                <!-- STAFF MOBILE MENU -->
                <button
                    type="button"
                    class="rounded-xl border border-cyan-100 p-2 text-cyan-900 hover:bg-cyan-50 lg:hidden dark:border-cyan-900 dark:text-cyan-100 dark:hover:bg-white/10"
                    :aria-label="
                        mobileMenuOpen
                            ? 'Close menu'
                            : 'Open menu'
                    "
                    :aria-expanded="mobileMenuOpen"
                    @click="mobileMenuOpen = !mobileMenuOpen"
                >
                    <X
                        v-if="mobileMenuOpen"
                        class="size-5"
                    />

                    <Menu
                        v-else
                        class="size-5"
                    />
                </button>

                <!-- PORTAL INFORMATION -->
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-slate-900 dark:text-slate-100">
                        {{ title }}
                    </p>

                    <p class="truncate text-xs text-cyan-800/70 dark:text-cyan-200/70">
                        {{ accountName }}
                    </p>
                </div>

                <AppearanceTabs variant="segmented" />

                <!-- NOTIFICATIONS -->
                <NotificationBell />

                <!-- LOGOUT: TOP-RIGHT HEADER ONLY -->
                <button
                    type="button"
                    data-test="staff-header-logout"
                    :disabled="loggingOut"
                    aria-label="Log out"
                    class="inline-flex shrink-0 items-center gap-2 rounded-xl border border-cyan-200 bg-white px-3 py-2 text-sm font-semibold text-cyan-900 transition-colors hover:bg-cyan-50 disabled:cursor-wait disabled:opacity-60 dark:border-cyan-800 dark:bg-slate-950 dark:text-cyan-100 dark:hover:bg-cyan-950"
                    @click="logout"
                >
                    <LogOut class="size-4" />

                    <span class="hidden sm:inline">
                        {{
                            loggingOut
                                ? 'Logging out...'
                                : 'Log out'
                        }}
                    </span>
                </button>
            </header>

            <!-- STAFF MOBILE NAVIGATION -->
            <nav
                v-if="mobileMenuOpen"
                class="space-y-1 border-b border-cyan-100 bg-white px-4 py-3 text-slate-800 lg:hidden dark:border-cyan-900 dark:bg-slate-950 dark:text-slate-100"
                aria-label="Mobile staff navigation"
            >
                <template
                    v-for="item in items"
                    :key="`mobile-${String(item.href)}`"
                >
                    <div
                        v-if="item.items?.length"
                        class="space-y-1"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center justify-between rounded-xl px-3 py-2.5 text-left text-sm transition-colors hover:bg-cyan-50 dark:hover:bg-white/10"
                            :class="groupButtonClass(item)"
                            :aria-expanded="isGroupOpen(item)"
                            @click="visitGroup(item, true)"
                        >
                            <span>{{ item.title }}</span>
                            <ChevronRight
                                class="size-4 transition-transform"
                                :class="isGroupOpen(item) ? 'rotate-90' : ''"
                            />
                        </button>

                        <div
                            v-if="isGroupOpen(item)"
                            class="ml-3 space-y-1 border-l border-cyan-200 pl-2 dark:border-cyan-800"
                        >
                            <Link
                                v-for="subItem in item.items"
                                :key="`mobile-${subItem.href}`"
                                :href="subItem.href"
                                class="block rounded-xl px-3 py-2 text-sm transition-colors hover:bg-cyan-50 dark:hover:bg-white/10"
                                :class="
                                    isActive(subItem.href)
                                        ? 'bg-[#00FFFF] font-semibold text-cyan-950'
                                        : ''
                                "
                                @click="mobileMenuOpen = false; syncHash()"
                            >
                                {{ subItem.title }}
                            </Link>
                        </div>
                    </div>

                    <Link
                        v-else
                        :href="item.href"
                        class="block rounded-xl px-3 py-2.5 text-sm transition-colors hover:bg-cyan-50 dark:hover:bg-white/10"
                        :class="
                            isActive(String(item.href))
                                ? 'bg-[#00FFFF] font-semibold text-cyan-950'
                                : ''
                        "
                        @click="mobileMenuOpen = false"
                    >
                        {{ item.title }}
                    </Link>
                </template>
            </nav>

            <!--
                SCROLLABLE DASHBOARD CONTENT

                The dashboard scrolls normally.
                The sidebar remains fixed.
            -->
            <main
                class="min-h-[calc(100vh-4rem)] min-w-0 pb-10"
            >
                <slot />
            </main>
        </div>

        <!-- TOAST NOTIFICATIONS -->
        <Toaster
            position="top-right"
            richColors
            closeButton
        />
    </div>
</template>

<style scoped>
/*
 * Thin sidebar scrollbar.
 * Both Super Admin and Admin use the same styling.
 */
.staff-nav {
    scrollbar-width: thin;
    scrollbar-color: #67e8f9 transparent;
}

.staff-nav::-webkit-scrollbar {
    width: 6px;
}

.staff-nav::-webkit-scrollbar-thumb {
    background-color: #67e8f9;
    border-radius: 999px;
}

.staff-nav::-webkit-scrollbar-track {
    background: transparent;
}
</style>
