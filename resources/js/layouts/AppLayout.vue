
<script setup lang="ts">
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import TripOutcomeMonitor from '@/components/trips/TripOutcomeMonitor.vue';
import type { BreadcrumbItem } from '@/types';

defineProps<{
    breadcrumbs?: BreadcrumbItem[];
}>();
</script>

<template>
    <!-- Retain existing Tourist mobile header, tabs and page layout. -->
    <AppSidebarLayout :breadcrumbs="breadcrumbs">
        <TripOutcomeMonitor />
        <slot />
    </AppSidebarLayout>
</template>
