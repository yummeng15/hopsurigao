<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Menu } from '@lucide/vue';

import AppLogo from '@/components/AppLogo.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import NotificationBell from '@/components/notifications/NotificationBell.vue';
import UserMenuContent from '@/components/UserMenuContent.vue';

import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    NavigationMenu,
    NavigationMenuItem,
    NavigationMenuList,
    navigationMenuTriggerStyle,
} from '@/components/ui/navigation-menu';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';

import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { getInitials } from '@/composables/useInitials';
import { touristNavItems } from '@/navigation';
import type { BreadcrumbItem, NavItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

const props = withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage();
const auth = computed(() => page.props.auth);
const { isCurrentUrl, whenCurrentUrl } = useCurrentUrl();

const getBadgeCount = (item: NavItem): number => {
    if (!item.badgeKey) return 0;
    const counts = page.props.badgeCounts as Record<string, number> | undefined;
    return counts?.[item.badgeKey] ?? 0;
};

const activeItemStyles =
    'bg-[#00FFFF] font-semibold text-cyan-950 hover:bg-[#00FFFF] hover:text-cyan-950';
</script>

<template>
    <header class="sticky top-0 z-40 w-full border-b border-border/60 bg-background/80 backdrop-blur-md transition-all">
        <div class="mx-auto flex h-16 w-full max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
            <!-- Left Side: Mobile Menu & Logo -->
            <div class="flex items-center gap-3 lg:gap-8">
                <!-- Mobile Menu Sheet -->
                <Sheet>
                    <SheetTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="h-9 w-9 rounded-xl border border-border/50 bg-background/50 backdrop-blur lg:hidden"
                        >
                            <Menu class="size-4.5 text-foreground" />
                            <span class="sr-only">Open navigation menu</span>
                        </Button>
                    </SheetTrigger>

                    <SheetContent side="left" class="w-[290px] p-0 rounded-r-3xl border-r bg-card/95 backdrop-blur-md">
                        <SheetHeader class="border-b border-border/60 px-6 py-4.5">
                            <SheetTitle>
                                <Link href="/user/dashboard" class="flex items-center gap-x-2.5">
                                    <AppLogo />
                                </Link>
                            </SheetTitle>
                        </SheetHeader>

                        <nav class="flex flex-col gap-1.5 p-4">
                            <Link
                                v-for="(item, index) in touristNavItems"
                                :key="index"
                                :href="item.href"
                                :class="[
                                    'flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-medium transition-all duration-200',
                                    isCurrentUrl(item.href)
                                        ? 'bg-[#00FFFF] font-semibold text-cyan-950'
                                        : 'text-muted-foreground hover:bg-muted/60 hover:text-foreground',
                                ]"
                            >
                                <component
                                    v-if="item.icon"
                                    :is="item.icon"
                                    class="size-4 shrink-0"
                                    :class="isCurrentUrl(item.href) ? 'text-cyan-950' : 'text-muted-foreground'"
                                />
                                <span>{{ item.title }}</span>

                                <span
                                    v-if="getBadgeCount(item) > 0"
                                    class="ml-auto flex h-5 min-w-5 items-center justify-center rounded-full bg-amber-500/15 border border-amber-500/30 px-1.5 text-xs font-bold text-amber-700 dark:text-amber-300"
                                >
                                    {{ getBadgeCount(item) }}
                                </span>
                            </Link>
                        </nav>
                    </SheetContent>
                </Sheet>

                <!-- Brand Logo -->
                <Link
                    href="/user/dashboard"
                    class="flex items-center gap-x-2.5 rounded-xl transition hover:opacity-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
                >
                    <AppLogo />
                </Link>

                <!-- Desktop Navigation Menu -->
                <div class="hidden h-full lg:flex">
                    <NavigationMenu class="flex h-full items-center">
                        <NavigationMenuList class="flex items-center space-x-1.5">
                            <NavigationMenuItem
                                v-for="(item, index) in touristNavItems"
                                :key="index"
                                class="relative flex items-center"
                            >
                                <Link
                                    :href="item.href"
                                    :class="[
                                        navigationMenuTriggerStyle(),
                                        whenCurrentUrl(
                                            item.href,
                                            activeItemStyles,
                                        ),
                                        'h-9 cursor-pointer rounded-xl px-3.5 text-xs font-medium transition-all duration-200 hover:bg-muted/60',
                                    ]"
                                >
                                    <component
                                        v-if="item.icon"
                                        :is="item.icon"
                                        class="mr-2 size-3.5 shrink-0"
                                        :class="isCurrentUrl(item.href) ? 'text-cyan-950' : 'text-muted-foreground'"
                                    />
                                    <span>{{ item.title }}</span>

                                    <span
                                        v-if="getBadgeCount(item) > 0"
                                        class="ml-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-amber-500/20 border border-amber-500/40 px-1 text-[10px] font-bold text-amber-700 dark:text-amber-300"
                                    >
                                        {{ getBadgeCount(item) }}
                                    </span>
                                </Link>

                                <!-- Active Bottom Indicator Glow -->
                                <span
                                    v-if="isCurrentUrl(item.href)"
                                    class="absolute -bottom-3.5 left-1/2 h-1 w-6 -translate-x-1/2 rounded-full bg-[#00FFFF]"
                                />
                            </NavigationMenuItem>
                        </NavigationMenuList>
                    </NavigationMenu>
                </div>
            </div>

            <!-- Right Side Actions: Appearance, Notifications & User Profile -->
            <div class="flex items-center gap-2.5">
                <AppearanceTabs variant="segmented" />

                <NotificationBell />

                <div class="h-4 w-px bg-border/60" />

                <!-- User Profile Menu -->
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="relative size-9 rounded-full border border-border/50 bg-background/50 p-0.5 shadow-xs transition hover:ring-2 hover:ring-primary/20 focus-visible:ring-2 focus-visible:ring-primary/40"
                        >
                            <Avatar class="size-full overflow-hidden rounded-full">
                                <AvatarImage
                                    v-if="auth.user.avatar"
                                    :src="auth.user.avatar"
                                    :alt="auth.user.name"
                                    class="object-cover"
                                />
                                <AvatarFallback
                                    class="bg-[#00FFFF]/20 text-xs font-semibold text-cyan-800 dark:text-cyan-200"
                                >
                                    {{ getInitials(auth.user?.name) }}
                                </AvatarFallback>
                            </Avatar>
                        </Button>
                    </DropdownMenuTrigger>

                    <DropdownMenuContent
                        align="end"
                        class="w-60 rounded-2xl border bg-card/95 p-1.5 backdrop-blur-md shadow-lg"
                    >
                        <UserMenuContent :user="auth.user" />
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>

        <!-- Breadcrumbs Toolbar Sub-header -->
        <div
            v-if="props.breadcrumbs.length > 1"
            class="border-t border-border/40 bg-muted/20 backdrop-blur-xs"
        >
            <div class="mx-auto flex h-10 w-full max-w-7xl items-center px-4 text-xs text-muted-foreground sm:px-6 lg:px-8">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </div>
        </div>
    </header>
</template>