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

const props = defineProps<{
    reports: ReportMeta[];
    filterOptions: FilterOptions;
    initialFilters: Record<string, any>;
    endpoints: {
        preview: string;
        download: string;
        export: string;
    };
    operatorName: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                href: '/operator/operations/reports',
                title: 'Reports',
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
        :endpoints="endpoints"
        eyebrow="Operations & Financial Intelligence"
        title="Reports & Manifests"
        description="Generate and download professional, print-ready PDF reports and manifests for your authorized tour operator business."
        :scope-note="`Admin Operator scope: ${props.operatorName} only. Other operators' records are never included.`"
    />
</template>
