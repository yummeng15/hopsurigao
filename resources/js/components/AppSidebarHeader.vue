<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import NotificationBell from '@/components/notifications/NotificationBell.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { getInitials } from '@/composables/useInitials';
import type { BreadcrumbItem } from '@/types';

const props = withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const page = usePage();
const auth = computed(() => page.props.auth);

const pageTitle = computed(() => {
    if (props.breadcrumbs.length > 0) {
        return props.breadcrumbs[props.breadcrumbs.length - 1]?.title ?? 'HopSurigao';
    }

    return 'HopSurigao';
});
</script>

<template>
    <!-- Desktop header -->
    <header
        class="hidden h-(--header-height) shrink-0 items-center gap-2 border-b transition-[width,height] ease-linear md:flex group-has-data-[collapsible=icon]/sidebar-wrapper:h-(--header-height)"
    >
        <div class="flex w-full items-center gap-1 px-4 lg:gap-2 lg:px-6">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>

            <div class="ml-auto flex items-center gap-2">
                <AppearanceTabs variant="segmented" />
                <NotificationBell />
                <DropdownMenu>
                    <DropdownMenuTrigger :as-child="true">
                        <Button
                            variant="ghost"
                            size="icon"
                            class="relative size-10 w-auto rounded-full p-1 focus-within:ring-2 focus-within:ring-primary"
                        >
                            <Avatar class="size-8 overflow-hidden rounded-full">
                                <AvatarImage
                                    v-if="auth.user.avatar"
                                    :src="auth.user.avatar"
                                    :alt="auth.user.name"
                                />
                                <AvatarFallback
                                    class="rounded-lg bg-neutral-200 font-semibold text-black dark:bg-neutral-700 dark:text-white"
                                >
                                    {{ getInitials(auth.user?.name) }}
                                </AvatarFallback>
                            </Avatar>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-56">
                        <UserMenuContent :user="auth.user" />
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>
    </header>

    <!-- Mobile app bar -->
    <header
        class="sticky top-0 z-30 flex h-14 shrink-0 items-center gap-3 border-b border-border bg-background/90 px-4 backdrop-blur-md md:hidden"
        style="padding-top: env(safe-area-inset-top)"
    >
        <img
            src="/images/hop-surigao-mark.png"
            alt="HopSurigao"
            class="size-9 shrink-0 rounded-full object-contain"
        />
        <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-semibold tracking-tight text-foreground">
                {{ pageTitle }}
            </p>
            <p class="truncate text-[11px] text-muted-foreground">
                {{ auth.user?.name }}
            </p>
        </div>
        <AppearanceTabs variant="segmented" />
        <NotificationBell />
        <DropdownMenu>
            <DropdownMenuTrigger :as-child="true">
                <Button
                    variant="ghost"
                    size="icon"
                    class="relative size-9 rounded-full p-0 focus-within:ring-2 focus-within:ring-primary"
                >
                    <Avatar class="size-8 overflow-hidden rounded-full">
                        <AvatarImage
                            v-if="auth.user.avatar"
                            :src="auth.user.avatar"
                            :alt="auth.user.name"
                        />
                        <AvatarFallback
                            class="rounded-full bg-[#00FFFF]/20 text-xs font-semibold text-cyan-800 dark:text-cyan-200"
                        >
                            {{ getInitials(auth.user?.name) }}
                        </AvatarFallback>
                    </Avatar>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-56">
                <UserMenuContent :user="auth.user" />
            </DropdownMenuContent>
        </DropdownMenu>
    </header>
</template>
