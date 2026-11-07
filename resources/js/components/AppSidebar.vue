<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { LogOut } from '@lucide/vue';
import { computed, onMounted, onUnmounted } from 'vue';

import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import SeaWaves from '@/components/SeaWaves.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useRole } from '@/composables/useRole';
import { adminNavItems, operatorNavItems, touristNavItems } from '@/navigation';
import { logout } from '@/routes';

const { isSuperadmin, isAdmin, isUser } = useRole();
const { isMobile, setOpenMobile } = useSidebar();

const portalLabel = computed(() => {
    if (isSuperadmin.value) return 'Super Admin Portal';
    if (isAdmin.value) return 'Tour Operator Portal';
    if (isUser.value) return 'Tourist Portal';
    return 'HopSurigao';
});

const navigationItems = computed(() => {
    if (isSuperadmin.value) return adminNavItems;
    if (isAdmin.value) return operatorNavItems;
    if (isUser.value) return touristNavItems;
    return [];
});

const homeHref = computed(() => {
    if (isSuperadmin.value) return '/superadmin/dashboard';
    if (isAdmin.value) return '/operator/dashboard';
    if (isUser.value) return '/user/dashboard';
    return '/';
});

const handleLogout = () => {
    setOpenMobile(false);
    router.flushAll();
};

let removeNavigateListener: (() => void) | undefined;

onMounted(() => {
    removeNavigateListener = router.on('navigate', () => setOpenMobile(false));
});

onUnmounted(() => {
    removeNavigateListener?.();
});
</script>

<template>
    <!-- One sidebar for each role; never mix another role's navigation. -->
    <Sidebar v-if="!isMobile" collapsible="offcanvas" variant="sidebar">
        <div
            aria-hidden="true"
            class="pointer-events-none absolute inset-0 overflow-hidden bg-card"
        >
            <SeaWaves class="absolute inset-x-0 bottom-0 h-40 w-full opacity-40" />
        </div>

        <SidebarHeader class="relative z-10 border-b border-border">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child class="h-auto! py-2!">
                        <Link :href="homeHref"><AppLogo compact /></Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <p class="px-3 pb-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                {{ portalLabel }}
            </p>
        </SidebarHeader>

        <SidebarContent class="relative z-10 pt-2">
            <NavMain :label="portalLabel" :items="navigationItems" />
        </SidebarContent>

        <SidebarFooter class="relative z-10">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton as-child>
                        <Link
                            :href="logout()"
                            as="button"
                            class="block w-full cursor-pointer"
                            data-test="logout-button"
                            @click="handleLogout"
                        >
                            <LogOut class="mr-2 h-4 w-4" />
                            <span>Log out</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>