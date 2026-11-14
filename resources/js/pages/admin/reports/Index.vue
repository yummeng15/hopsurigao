<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ReportsAnalyticsWorkspace from '@/components/reports/ReportsAnalyticsWorkspace.vue';

type ReportMeta = {
    id: string;
    title: string;
    description: string;
    icon: string;
    supported_filters: string[];
};

type FilterOptions = {
    packages: Array<{
        id: number;
        package_name: string;
        destination: string;
        price: number | string;
    }>;
    pickupLocations: Array<{ id: number; name: string }>;
    nationalities: string[];
    bookingStatuses: string[];
    paymentStatuses: string[];
    paymentMethods: string[];
    advisoryLevels: string[];
    packageStatuses: string[];
};

defineProps<{
    reports: ReportMeta[];
    filterOptions: FilterOptions;
    initialFilters: Record<string, any>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                href: '/admin/reports',
                title: 'Reports & Analytics',
            },
        ],
    },
});
</script>

<template>
    <Head title="Reports & Analytics" />

    <ReportsAnalyticsWorkspace
        :reports="reports"
        :filter-options="filterOptions"
        :initial-filters="initialFilters"
        :endpoints="{
            preview: '/admin/reports/preview',
            download: '/admin/reports/download',
            export: '/admin/reports/export',
        }"
        eyebrow="Operations & Financial Intelligence"
        title="Reports & Manifests"
        description="Generate and download professional, print-ready PDF reports and manifests for tourism dispatch, operations, and auditing across all registered tour operators."
        scope-note="Super Administrator scope: consolidated data across the entire HopSurigao platform."
    />
</template>
