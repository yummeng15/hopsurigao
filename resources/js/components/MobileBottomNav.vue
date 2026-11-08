<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { LogOut } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useRole } from '@/composables/useRole';
import {
    isMobileTabActive,
    mobileMoreItemsForRole,
    mobileTabsForRole,
} from '@/navigation/mobile';
import { logout } from '@/routes';
import type { NavItem } from '@/types';

const page = usePage();
const { roleName } = useRole();
const moreOpen = ref(false);

const currentUrl = computed(() => page.url.split('?')[0] ?? page.url);
const tabs = computed(() => mobileTabsForRole(roleName.value ?? undefined, currentUrl.value));
const moreItems = computed(() => mobileMoreItemsForRole(roleName.value ?? undefined, currentUrl.value));

const getBadgeCount = (item: NavItem): number => {
    if (!item.badgeKey) {
        return 0;
    }

    const counts = page.props.badgeCounts as Record<string, number> | undefined;

    return counts?.[item.badgeKey] ?? 0;
};

const isActive = (tab: (typeof tabs.value)[number]) => isMobileTabActive(tab, currentUrl.value);

const moreIsHighlighted = computed(() => {
    if (moreItems.value.length === 0) {
        return false;
    }

    return moreItems.value.some((item) => {
        const href = String(item.href);

        return currentUrl.value === href || currentUrl.value.startsWith(`${href}/`);
    });
});

watch(
    () => page.url,
    () => {
        moreOpen.value = false;
    },
);

const openMore = () => {
    moreOpen.value = true;
};

const handleLogout = () => {
    moreOpen.value = false;
    router.flushAll();
};
</script>

<template>
    <nav
        v-if="tabs.length > 0"
        class="fixed inset-x-0 bottom-0 z-40 border-t border-border bg-background/95 backdrop-blur-md md:hidden"
        style="padding-bottom: env(safe-area-inset-bottom, 0px)"
        aria-label="Mobile primary navigation"
        data-mobile-bottom-nav
    >
        <ul class="mx-auto grid max-w-lg grid-flow-col auto-cols-fr px-1 pt-1.5 pb-1">
            <li v-for="tab in tabs" :key="tab.tabTitle" class="min-w-0">
                <button
                    v-if="tab.href === '#more'"
                    type="button"
                    class="relative flex w-full flex-col items-center gap-0.5 rounded-xl px-1 py-1.5 text-[10px] font-medium transition-colors"
                    :class="
                        moreOpen || moreIsHighlighted
                            ? 'text-cyan-700 dark:text-cyan-300'
                            : 'text-muted-foreground'
                    "
                    @click="openMore"
                >
                    <span
                        class="flex size-9 items-center justify-center rounded-2xl transition-colors"
                        :class="
                            moreOpen || moreIsHighlighted
                                ? 'bg-[#00FFFF] text-cyan-950'
                                : 'bg-transparent'
                        "
                    >
                        <component :is="tab.icon" class="size-5" />
                    </span>
                    <span class="truncate">{{ tab.tabTitle }}</span>
                </button>

                <Link
                    v-else
                    :href="tab.href"
                    class="relative flex w-full flex-col items-center gap-0.5 rounded-xl px-1 py-1.5 text-[10px] font-medium transition-colors"
                    :class="isActive(tab) ? 'text-cyan-700 dark:text-cyan-300' : 'text-muted-foreground'"
                >
                    <span
                        class="relative flex size-9 items-center justify-center rounded-2xl transition-colors"
                        :class="
                            isActive(tab)
                                ? 'bg-[#00FFFF] text-cyan-950'
                                : String(tab.href).includes('qrscanner')
                                  ? 'bg-[#00FFFF]/20 text-cyan-700 dark:text-cyan-300'
                                  : 'bg-transparent'
                        "
                    >
                        <component :is="tab.icon" class="size-5" />
                        <span
                            v-if="getBadgeCount(tab) > 0"
                            class="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-amber-500 px-1 text-[9px] font-bold text-white"
                        >
                            {{ getBadgeCount(tab) > 9 ? '9+' : getBadgeCount(tab) }}
                        </span>
                    </span>
                    <span class="truncate">{{ tab.tabTitle }}</span>
                </Link>
            </li>
        </ul>
    </nav>

    <Sheet v-model:open="moreOpen">
        <SheetContent
            side="bottom"
            class="max-h-[min(80vh,100dvh)] rounded-t-3xl border-border bg-background px-0 pb-[calc(2rem+env(safe-area-inset-bottom,0px))]"
        >
            <SheetHeader class="border-b border-border px-5 pb-3 text-left">
                <SheetTitle>More</SheetTitle>
                <SheetDescription>Other tools and pages for your role.</SheetDescription>
            </SheetHeader>

            <div class="overflow-y-auto px-3 pt-2">
                <Link
                    v-for="item in moreItems"
                    :key="String(item.href)"
                    :href="item.href"
                    class="flex items-center gap-3 rounded-2xl px-3 py-3 text-sm font-medium text-foreground transition-colors hover:bg-muted"
                    @click="moreOpen = false"
                >
                    <span class="flex size-10 items-center justify-center rounded-2xl bg-[#00FFFF]/20 text-cyan-700 dark:text-cyan-300">
                        <component v-if="item.icon" :is="item.icon" class="size-5" />
                    </span>
                    <span class="flex-1">{{ item.title }}</span>
                    <span
                        v-if="getBadgeCount(item) > 0"
                        class="rounded-full bg-amber-500/15 px-2 py-0.5 text-[10px] font-bold text-amber-700"
                    >
                        {{ getBadgeCount(item) }}
                    </span>
                </Link>

                <Link
                    :href="logout()"
                    as="button"
                    class="mt-2 flex w-full items-center gap-3 rounded-2xl px-3 py-3 text-sm font-medium text-destructive transition-colors hover:bg-destructive/5"
                    @click="handleLogout"
                >
                    <span class="flex size-10 items-center justify-center rounded-2xl bg-destructive/10 text-destructive">
                        <LogOut class="size-5" />
                    </span>
                    <span>Log out</span>
                </Link>
            </div>
        </SheetContent>
    </Sheet>
</template>
