<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import MobileBottomNav from '@/components/MobileBottomNav.vue';
import SeaBackground from '@/components/SeaBackground.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="relative overflow-x-hidden bg-transparent!">
            <SeaBackground />
            <div
                class="relative z-10 flex min-h-full flex-1 flex-col pb-[calc(5.5rem+env(safe-area-inset-bottom))] md:pb-0 [&>.bg-background]:bg-transparent"
            >
                <AppSidebarHeader :breadcrumbs="breadcrumbs" />
                <slot />
            </div>
        </AppContent>
        <MobileBottomNav />
        <Toaster position="top-center" richColors closeButton :toastOptions="{ style: { borderRadius: '12px' } }" />
    </AppShell>
</template>
