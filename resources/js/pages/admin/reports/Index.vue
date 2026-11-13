<!-- resources/js/pages/admin/reports/Index.vue -->
<script setup lang="ts">
/*
|--------------------------------------------------------------------------
| Imports
|--------------------------------------------------------------------------
*/

import { Head } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import SimpleTable from '@/components/tables/DataTable.vue';

import {
    CalendarClock,
    ClipboardList,
    CreditCard,
    CloudSun,
    Download,
    Eye,
    FileSpreadsheet,
    FileText,
    Filter,
    Loader2,
    MapPin,
    RefreshCw,
    Sparkles,
    Ticket,
    TrendingUp,
    Users,
    CheckCircle2,
    AlertCircle,
} from '@lucide/vue';

import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/*
|--------------------------------------------------------------------------
| Types
|--------------------------------------------------------------------------
*/

type ReportMeta = {
    id: string;
    title: string;
    description: string;
    icon: string;
    supported_filters: string[];
};

type PackageOption = {
    id: number;
    package_name: string;
    destination: string;
    price: number | string;
};

type PickupLocationOption = {
    id: number;
    name: string;
};

type FilterOptions = {
    packages: PackageOption[];
    pickupLocations: PickupLocationOption[];
    nationalities: string[];
    bookingStatuses: string[];
    paymentStatuses: string[];
    paymentMethods: string[];
    advisoryLevels: string[];
    packageStatuses: string[];
};

/*
|--------------------------------------------------------------------------
| Props
|--------------------------------------------------------------------------
*/

const props = defineProps<{
    reports: ReportMeta[];
    filterOptions: FilterOptions;
    initialFilters: Record<string, any>;
}>();

/*
|--------------------------------------------------------------------------
| State
|--------------------------------------------------------------------------
*/

const selectedReportId = ref<string>(props.reports[0]?.id || 'booking');

const filters = ref({
    date_from: props.initialFilters.date_from || '',
    date_to: props.initialFilters.date_to || '',
    package_id: 'all',
    booking_status: 'all',
    payment_status: 'all',
    payment_method: 'all',
    pickup_location_id: 'all',
    nationality: 'all',
    advisory_level: 'all',
    status: 'all',
});

const previewLoading = ref(false);
const downloadLoading = ref(false);
const previewData = ref<any>(null);
const errorMessage = ref<string | null>(null);
const tableRef = ref();

/*
|--------------------------------------------------------------------------
| Dynamic Columns for SimpleTable
|--------------------------------------------------------------------------
*/

const reportColumns = computed(() => {
    switch (selectedReportId.value) {
        case 'booking':
            return [
                { key: 'id', label: 'ID', sortable: true, width: '70px' },
                { key: 'tourist_name', label: 'Tourist', sortable: true },
                { key: 'package_name', label: 'Package', sortable: true },
                { key: 'tour_date', label: 'Tour Date', sortable: true },
                { key: 'pickup_location', label: 'Pickup Location', sortable: true },
                { key: 'number_of_guests', label: 'Guests', sortable: true, align: 'center', width: '90px' },
                { key: 'booking_status', label: 'Status', sortable: true, align: 'center' },
                { key: 'payment_status', label: 'Payment', sortable: true, align: 'center' },
            ];
        case 'tour-schedule':
            return [
                { key: 'tour_date', label: 'Tour Date', sortable: true, width: '130px' },
                { key: 'package_name', label: 'Package & Destination', sortable: true },
                { key: 'capacity', label: 'Capacity', sortable: true, align: 'center', width: '90px' },
                { key: 'booked_guests', label: 'Booked', sortable: true, align: 'center', width: '90px' },
                { key: 'available_slots', label: 'Available', sortable: true, align: 'center', width: '90px' },
                { key: 'occupancy_percent', label: 'Occupancy', sortable: true, align: 'center', width: '100px' },
                { key: 'advisory_badge', label: 'Weather Advisory', sortable: true, align: 'center' },
            ];
        case 'payment':
            return [
                { key: 'id', label: 'ID', sortable: true, width: '70px' },
                { key: 'booking_id', label: 'Booking #', sortable: true, width: '110px' },
                { key: 'tourist_name', label: 'Payer / Tourist', sortable: true },
                { key: 'package_name', label: 'Package', sortable: true },
                { key: 'amount', label: 'Amount', sortable: true, align: 'right' },
                { key: 'payment_method', label: 'Channel', sortable: true },
                { key: 'payment_status', label: 'Status', sortable: true, align: 'center' },
            ];
        case 'tourist':
            return [
                { key: 'id', label: 'ID', sortable: true, width: '70px' },
                { key: 'tourist_name', label: 'Tourist Name', sortable: true },
                { key: 'phone_number', label: 'Contact', sortable: false },
                { key: 'nationality', label: 'Nationality', sortable: true },
                { key: 'package_name', label: 'Package', sortable: true },
                { key: 'tour_date', label: 'Tour Date', sortable: true },
                { key: 'number_of_guests', label: 'Guests', sortable: true, align: 'center', width: '90px' },
                { key: 'booking_status', label: 'Status', sortable: true, align: 'center' },
            ];
        case 'pickup-schedule':
            return [
                { key: 'location_name', label: 'Pickup Location', sortable: true },
                { key: 'package_name', label: 'Package', sortable: true },
                { key: 'tour_date', label: 'Tour Date', sortable: true },
                { key: 'pickup_time', label: 'Pickup Time', sortable: true },
                { key: 'guests_count', label: 'Guests', sortable: true, align: 'center', width: '90px' },
                { key: 'passenger_manifest', label: 'Passenger Manifest', sortable: false },
            ];
        case 'package-performance':
            return [
                { key: 'package_name', label: 'Package', sortable: true },
                { key: 'operator_name', label: 'Tour Operator', sortable: true },
                { key: 'destination', label: 'Destination', sortable: true },
                { key: 'total_bookings', label: 'Bookings', sortable: true, align: 'center', width: '90px' },
                { key: 'confirmed_bookings', label: 'Confirmed', sortable: true, align: 'center', width: '100px' },
                { key: 'cancelled_bookings', label: 'Cancelled', sortable: true, align: 'center', width: '100px' },
                { key: 'gcash_revenue', label: 'GCash Revenue', sortable: true, align: 'right' },
                { key: 'cash_revenue', label: 'Cash Revenue', sortable: true, align: 'right' },
                { key: 'revenue', label: 'Verified Revenue', sortable: true, align: 'right' },
            ];
        case 'operator-performance':
            return [
                { key: 'operator_name', label: 'Tour Operator', sortable: true },
                { key: 'approval_status', label: 'Status', sortable: true, align: 'center', width: '110px' },
                { key: 'total_bookings', label: 'Bookings', sortable: true, align: 'center', width: '90px' },
                { key: 'confirmed_bookings', label: 'Confirmed', sortable: true, align: 'center', width: '100px' },
                { key: 'cancelled_bookings', label: 'Cancelled', sortable: true, align: 'center', width: '100px' },
                { key: 'gcash_revenue', label: 'GCash Revenue', sortable: true, align: 'right' },
                { key: 'cash_revenue', label: 'Cash Revenue', sortable: true, align: 'right' },
                { key: 'revenue', label: 'Verified Revenue', sortable: true, align: 'right' },
            ];
        case 'weather-advisory':
            return [
                { key: 'forecast_date', label: 'Forecast Date', sortable: true, width: '130px' },
                { key: 'package_name', label: 'Package', sortable: true },
                { key: 'weather_condition', label: 'Condition', sortable: true },
                { key: 'temperature_range', label: 'Temp', sortable: false },
                { key: 'wind_rain', label: 'Wind / Rain', sortable: false },
                { key: 'advisory_badge', label: 'Advisory', sortable: true, align: 'center' },
                { key: 'booked_guests', label: 'Booked Guests', sortable: true, align: 'center', width: '110px' },
            ];
        default:
            return [];
    }
});

/*
|--------------------------------------------------------------------------
| Computed
|--------------------------------------------------------------------------
*/

const currentReport = computed(() => {
    return props.reports.find((r) => r.id === selectedReportId.value) || props.reports[0];
});

function getIconComponent(iconName: string) {
    switch (iconName) {
        case 'ClipboardList':
            return ClipboardList;
        case 'CalendarClock':
            return CalendarClock;
        case 'CreditCard':
            return CreditCard;
        case 'Users':
            return Users;
        case 'MapPin':
            return MapPin;
        case 'TrendingUp':
            return TrendingUp;
        case 'CloudSun':
            return CloudSun;
        default:
            return FileText;
    }
}

/*
|--------------------------------------------------------------------------
| Date Preset Helpers
|--------------------------------------------------------------------------
*/

function setDatePreset(preset: 'today' | 'this_week' | 'this_month' | 'next_30' | 'all') {
    const today = new Date();
    const formatDateStr = (d: Date) => d.toISOString().slice(0, 10);

    if (preset === 'today') {
        filters.value.date_from = formatDateStr(today);
        filters.value.date_to = formatDateStr(today);
    } else if (preset === 'this_week') {
        const day = today.getDay();
        const diff = today.getDate() - day + (day === 0 ? -6 : 1);
        const monday = new Date(today.setDate(diff));
        const sunday = new Date(monday);
        sunday.setDate(monday.getDate() + 6);
        filters.value.date_from = formatDateStr(monday);
        filters.value.date_to = formatDateStr(sunday);
    } else if (preset === 'this_month') {
        const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
        const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
        filters.value.date_from = formatDateStr(firstDay);
        filters.value.date_to = formatDateStr(lastDay);
    } else if (preset === 'next_30') {
        const future = new Date();
        future.setDate(today.getDate() + 30);
        filters.value.date_from = formatDateStr(today);
        filters.value.date_to = formatDateStr(future);
    } else if (preset === 'all') {
        filters.value.date_from = '';
        filters.value.date_to = '';
    }

    fetchPreview();
}

/*
|--------------------------------------------------------------------------
| Fetch Live Preview Data
|--------------------------------------------------------------------------
*/

async function fetchPreview() {
    previewLoading.value = true;
    errorMessage.value = null;

    try {
        const params = {
            type: selectedReportId.value,
            ...filters.value,
        };

        const query = new URLSearchParams(
            Object.entries(params).reduce<Record<string, string>>((carry, [key, value]) => {
                if (value !== null && value !== undefined && value !== '') {
                    carry[key] = String(value);
                }

                return carry;
            }, {}),
        );

        const response = await fetch(`/admin/reports/preview?${query.toString()}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        const payload = await response.json();

        if (! response.ok) {
            throw new Error(payload?.message || 'Failed to fetch report preview data.');
        }

        if (payload?.success) {
            previewData.value = payload.data;
        }
    } catch (err: unknown) {
        console.error('Failed to load report preview:', err);
        errorMessage.value = err instanceof Error
            ? err.message
            : 'Failed to fetch report preview data.';
    } finally {
        previewLoading.value = false;
    }
}

/*
|--------------------------------------------------------------------------
| Download PDF
|--------------------------------------------------------------------------
*/

function reportQuery(): string {
    return new URLSearchParams({
        type: selectedReportId.value,
        ...filters.value,
    }).toString();
}

function downloadPdf() {
    downloadLoading.value = true;

    const query = reportQuery();

    // Trigger direct download URL
    window.location.href = `/admin/reports/download?${query}`;

    setTimeout(() => {
        downloadLoading.value = false;
    }, 2500);
}

function exportCsv() {
    window.location.href = `/admin/reports/export?${reportQuery()}`;
}

function selectReport(id: string) {
    selectedReportId.value = id;
    fetchPreview();
}

function formatKpiValue(key: string, val: any): string {
    if (val === null || val === undefined) return '-';

    // If string value (e.g. highest_revenue_package, most_booked_package, top_nationality)
    if (typeof val === 'string') {
        if (!isNaN(Number(val)) && (key === 'overall_total_revenue' || key === 'total_revenue' || key === 'pending_revenue')) {
            return '₱' + Number(val).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        return val;
    }

    // If number value
    if (typeof val === 'number') {
        if (key.includes('revenue') || key.includes('amount') || key.includes('price')) {
            return '₱' + val.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        if (key.includes('rate') || key.includes('percent')) {
            return val + '%';
        }

        return val.toLocaleString('en-PH');
    }

    return String(val);
}

// Watch report change or filters
watch(selectedReportId, () => {
    fetchPreview();
});

onMounted(() => {
    fetchPreview();
});

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

    <div class="relative min-h-screen overflow-x-clip bg-background pb-20 text-foreground">
        <!-- Ambient Background Glows -->
        <div class="pointer-events-none absolute inset-0 -z-10 overflow-hidden">
            <div class="absolute left-1/2 top-[-10rem] h-[28rem] w-[42rem] -translate-x-1/2 rounded-full bg-primary/10 blur-3xl" />
            <div class="absolute right-[-10rem] top-64 h-72 w-72 rounded-full bg-primary/5 blur-3xl" />
        </div>

        <div class="mx-auto max-w-7xl px-5 py-8 lg:px-8">
            <!-- ==========================================================
                 HEADER
            =========================================================== -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <div class="inline-flex items-center gap-2 rounded-full border bg-background/80 px-3 py-1 text-xs font-medium backdrop-blur shadow-xs">
                        <Sparkles class="h-3.5 w-3.5 text-primary" />
                        <span>Operations & Financial Intelligence</span>
                    </div>

                    <h1 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                        Reports & Manifests
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Generate and download professional, print-ready PDF reports and manifests for tourism dispatch, operations, and auditing.
                    </p>
                </div>

                <div class="inline-flex items-center gap-2 rounded-2xl border bg-card/60 px-4 py-2 text-xs font-medium backdrop-blur">
                    <FileSpreadsheet class="h-4 w-4 text-primary" />
                    <span>{{ reports.length }} Standard Report Templates</span>
                </div>
            </div>

            <!-- ==========================================================
                 REPORT CATALOG CARDS
            =========================================================== -->
            <div class="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    v-for="report in reports"
                    :key="report.id"
                    class="group relative cursor-pointer rounded-2xl border p-5 backdrop-blur transition-all duration-200"
                    :class="
                        selectedReportId === report.id
                            ? 'border-primary bg-primary/10 ring-2 ring-primary/20 shadow-sm'
                            : 'border-border/80 bg-card/60 hover:border-primary/40 hover:bg-card/90'
                    "
                    @click="selectReport(report.id)"
                >
                    <div class="flex items-center justify-between">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-xl transition-colors"
                            :class="
                                selectedReportId === report.id
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-primary/10 text-primary group-hover:bg-primary/20'
                            "
                        >
                            <component :is="getIconComponent(report.icon)" class="h-5 w-5" />
                        </div>

                        <span
                            v-if="selectedReportId === report.id"
                            class="inline-flex items-center gap-1 rounded-full bg-primary/20 px-2.5 py-0.5 text-[10px] font-bold text-primary"
                        >
                            <CheckCircle2 class="h-3 w-3" />
                            Active
                        </span>
                    </div>

                    <h3 class="mt-4 text-sm font-bold text-foreground">
                        {{ report.title }}
                    </h3>
                    <p class="mt-1 line-clamp-2 text-xs text-muted-foreground">
                        {{ report.description }}
                    </p>
                </div>
            </div>

            <!-- ==========================================================
                 ACTIVE REPORT CONFIGURATION & PREVIEW WORKSPACE
            =========================================================== -->
            <div class="mt-8 space-y-6">
                <!-- Filter Controls Card -->
                <Card class="rounded-3xl border bg-card/60 backdrop-blur shadow-sm">
                    <CardHeader class="border-b bg-muted/20 p-6">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                    <component :is="getIconComponent(currentReport.icon)" class="h-5 w-5" />
                                </div>
                                <div>
                                    <CardTitle class="text-lg font-bold text-foreground">
                                        {{ currentReport.title }}
                                    </CardTitle>
                                    <CardDescription class="text-xs">
                                        {{ currentReport.description }}
                                    </CardDescription>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    :disabled="previewLoading"
                                    class="rounded-xl border-border/80 bg-background/80 backdrop-blur"
                                    @click="fetchPreview"
                                >
                                    <RefreshCw class="mr-1.5 h-3.5 w-3.5" :class="{ 'animate-spin': previewLoading }" />
                                    <span>Refresh Preview</span>
                                </Button>

                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="rounded-xl"
                                    @click="exportCsv"
                                >
                                    <Download class="mr-2 h-4 w-4" />
                                    <span>Export CSV</span>
                                </Button>

                                <Button
                                    size="sm"
                                    :disabled="downloadLoading"
                                    class="rounded-xl shadow-xs"
                                    @click="downloadPdf"
                                >
                                    <Loader2 v-if="downloadLoading" class="mr-2 h-4 w-4 animate-spin" />
                                    <Download v-else class="mr-2 h-4 w-4" />
                                    <span>{{ downloadLoading ? 'Generating PDF...' : 'Download PDF Report' }}</span>
                                </Button>
                            </div>
                        </div>
                    </CardHeader>

                    <CardContent class="p-6">
                        <!-- Date Quick Presets -->
                        <div class="mb-5 flex flex-wrap items-center gap-2">
                            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground mr-1">
                                Quick Period:
                            </span>
                            <button
                                type="button"
                                class="rounded-lg border border-border/80 bg-background/60 px-3 py-1 text-xs font-medium text-muted-foreground hover:bg-muted hover:text-foreground"
                                @click="setDatePreset('today')"
                            >
                                Today
                            </button>
                            <button
                                type="button"
                                class="rounded-lg border border-border/80 bg-background/60 px-3 py-1 text-xs font-medium text-muted-foreground hover:bg-muted hover:text-foreground"
                                @click="setDatePreset('this_week')"
                            >
                                This Week
                            </button>
                            <button
                                type="button"
                                class="rounded-lg border border-border/80 bg-background/60 px-3 py-1 text-xs font-medium text-muted-foreground hover:bg-muted hover:text-foreground"
                                @click="setDatePreset('this_month')"
                            >
                                This Month
                            </button>
                            <button
                                type="button"
                                class="rounded-lg border border-border/80 bg-background/60 px-3 py-1 text-xs font-medium text-muted-foreground hover:bg-muted hover:text-foreground"
                                @click="setDatePreset('next_30')"
                            >
                                Next 30 Days
                            </button>
                            <button
                                type="button"
                                class="rounded-lg border border-border/80 bg-background/60 px-3 py-1 text-xs font-medium text-muted-foreground hover:bg-muted hover:text-foreground"
                                @click="setDatePreset('all')"
                            >
                                All Time
                            </button>
                        </div>

                        <!-- Dynamic Filter Form Grid -->
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <!-- Date From -->
                            <div>
                                <Label class="mb-1.5 block text-xs font-semibold text-muted-foreground">Date From</Label>
                                <Input
                                    v-model="filters.date_from"
                                    type="date"
                                    class="h-10 rounded-xl border-border/80 bg-background/80"
                                    @change="fetchPreview"
                                />
                            </div>

                            <!-- Date To -->
                            <div>
                                <Label class="mb-1.5 block text-xs font-semibold text-muted-foreground">Date To</Label>
                                <Input
                                    v-model="filters.date_to"
                                    type="date"
                                    class="h-10 rounded-xl border-border/80 bg-background/80"
                                    @change="fetchPreview"
                                />
                            </div>

                            <!-- Package Filter -->
                            <div v-if="currentReport.supported_filters.includes('package_id')">
                                <Label class="mb-1.5 block text-xs font-semibold text-muted-foreground">Tour Package</Label>
                                <select
                                    v-model="filters.package_id"
                                    class="h-10 w-full rounded-xl border border-border/80 bg-background/80 px-3 text-xs text-foreground focus:border-primary focus:outline-none"
                                    @change="fetchPreview"
                                >
                                    <option value="all">All Packages</option>
                                    <option
                                        v-for="pkg in filterOptions.packages"
                                        :key="pkg.id"
                                        :value="pkg.id"
                                    >
                                        {{ pkg.package_name }} ({{ pkg.destination }})
                                    </option>
                                </select>
                            </div>

                            <!-- Booking Status Filter -->
                            <div v-if="currentReport.supported_filters.includes('booking_status')">
                                <Label class="mb-1.5 block text-xs font-semibold text-muted-foreground">Booking Status</Label>
                                <select
                                    v-model="filters.booking_status"
                                    class="h-10 w-full rounded-xl border border-border/80 bg-background/80 px-3 text-xs text-foreground focus:border-primary focus:outline-none"
                                    @change="fetchPreview"
                                >
                                    <option value="all">All Booking Statuses</option>
                                    <option
                                        v-for="status in filterOptions.bookingStatuses"
                                        :key="status"
                                        :value="status"
                                    >
                                        {{ status.charAt(0).toUpperCase() + status.slice(1) }}
                                    </option>
                                </select>
                            </div>

                            <!-- Payment Status Filter -->
                            <div v-if="currentReport.supported_filters.includes('payment_status')">
                                <Label class="mb-1.5 block text-xs font-semibold text-muted-foreground">Payment Status</Label>
                                <select
                                    v-model="filters.payment_status"
                                    class="h-10 w-full rounded-xl border border-border/80 bg-background/80 px-3 text-xs text-foreground focus:border-primary focus:outline-none"
                                    @change="fetchPreview"
                                >
                                    <option value="all">All Payment Statuses</option>
                                    <option
                                        v-for="status in filterOptions.paymentStatuses"
                                        :key="status"
                                        :value="status"
                                    >
                                        {{ status.charAt(0).toUpperCase() + status.slice(1) }}
                                    </option>
                                </select>
                            </div>

                            <!-- Payment Method Filter -->
                            <div v-if="currentReport.supported_filters.includes('payment_method')">
                                <Label class="mb-1.5 block text-xs font-semibold text-muted-foreground">Payment Channel</Label>
                                <select
                                    v-model="filters.payment_method"
                                    class="h-10 w-full rounded-xl border border-border/80 bg-background/80 px-3 text-xs text-foreground focus:border-primary focus:outline-none"
                                    @change="fetchPreview"
                                >
                                    <option value="all">All Payment Channels</option>
                                    <option
                                        v-for="method in filterOptions.paymentMethods"
                                        :key="method"
                                        :value="method"
                                    >
                                        {{ method.replace('_', ' ').toUpperCase() }}
                                    </option>
                                </select>
                            </div>

                            <!-- Pickup Location Filter -->
                            <div v-if="currentReport.supported_filters.includes('pickup_location_id')">
                                <Label class="mb-1.5 block text-xs font-semibold text-muted-foreground">Pickup Location</Label>
                                <select
                                    v-model="filters.pickup_location_id"
                                    class="h-10 w-full rounded-xl border border-border/80 bg-background/80 px-3 text-xs text-foreground focus:border-primary focus:outline-none"
                                    @change="fetchPreview"
                                >
                                    <option value="all">All Pickup Locations</option>
                                    <option
                                        v-for="loc in filterOptions.pickupLocations"
                                        :key="loc.id"
                                        :value="loc.id"
                                    >
                                        {{ loc.name }}
                                    </option>
                                </select>
                            </div>

                            <!-- Nationality Filter -->
                            <div v-if="currentReport.supported_filters.includes('nationality')">
                                <Label class="mb-1.5 block text-xs font-semibold text-muted-foreground">Nationality</Label>
                                <select
                                    v-model="filters.nationality"
                                    class="h-10 w-full rounded-xl border border-border/80 bg-background/80 px-3 text-xs text-foreground focus:border-primary focus:outline-none"
                                    @change="fetchPreview"
                                >
                                    <option value="all">All Nationalities</option>
                                    <option
                                        v-for="nat in filterOptions.nationalities"
                                        :key="nat"
                                        :value="nat"
                                    >
                                        {{ nat }}
                                    </option>
                                </select>
                            </div>

                            <!-- Advisory Level Filter -->
                            <div v-if="currentReport.supported_filters.includes('advisory_level')">
                                <Label class="mb-1.5 block text-xs font-semibold text-muted-foreground">Advisory Severity</Label>
                                <select
                                    v-model="filters.advisory_level"
                                    class="h-10 w-full rounded-xl border border-border/80 bg-background/80 px-3 text-xs text-foreground focus:border-primary focus:outline-none"
                                    @change="fetchPreview"
                                >
                                    <option value="all">All Advisory Levels</option>
                                    <option value="green">Normal / Safe (Green)</option>
                                    <option value="yellow">Caution / Monitor (Yellow)</option>
                                    <option value="orange">Warning / Gale (Orange)</option>
                                    <option value="red">Severe / Storm (Red)</option>
                                </select>
                            </div>

                            <!-- Package Status Filter -->
                            <div v-if="currentReport.supported_filters.includes('status')">
                                <Label class="mb-1.5 block text-xs font-semibold text-muted-foreground">Package Status</Label>
                                <select
                                    v-model="filters.status"
                                    class="h-10 w-full rounded-xl border border-border/80 bg-background/80 px-3 text-xs text-foreground focus:border-primary focus:outline-none"
                                    @change="fetchPreview"
                                >
                                    <option value="all">All Statuses</option>
                                    <option value="active">Active Packages</option>
                                    <option value="inactive">Inactive Packages</option>
                                </select>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Live Summary KPI Metrics Bar -->
                <div v-if="previewData?.summary" class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                    <div
                        v-for="(val, key) in previewData.summary"
                        :key="key"
                        v-show="typeof val === 'number' || typeof val === 'string'"
                        class="rounded-2xl border bg-card/60 p-4 backdrop-blur shadow-xs flex flex-col justify-between"
                    >
                        <span class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
                            {{ String(key).replace(/_/g, ' ') }}
                        </span>
                        <p
                            class="mt-1 font-extrabold text-foreground truncate"
                            :class="typeof val === 'string' && val.length > 12 ? 'text-xs font-bold' : 'text-xl'"
                            :title="String(val)"
                        >
                            {{ formatKpiValue(String(key), val) }}
                        </p>
                    </div>
                </div>

                <!-- Preview Table Card with SimpleTable -->
                <Card class="overflow-hidden rounded-3xl border bg-card/60 backdrop-blur shadow-sm">
                    <CardHeader class="flex flex-row items-center justify-between border-b bg-muted/20 p-5 sm:p-6">
                        <div>
                            <CardTitle class="text-base font-bold text-foreground">
                                Data Preview ({{ previewData?.records?.length || 0 }} total records)
                            </CardTitle>
                            <CardDescription class="text-xs">
                                Period Coverage: <strong>{{ previewData?.period_label || 'Current Period' }}</strong>
                            </CardDescription>
                        </div>

                        <div class="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                class="rounded-xl"
                                @click="exportCsv"
                            >
                                <Download class="mr-1.5 h-3.5 w-3.5" />
                                <span>Export CSV</span>
                            </Button>

                            <Button
                                size="sm"
                                :disabled="downloadLoading"
                                class="rounded-xl shadow-xs"
                                @click="downloadPdf"
                            >
                                <Download class="mr-1.5 h-3.5 w-3.5" />
                                <span>Export PDF</span>
                            </Button>
                        </div>
                    </CardHeader>

                    <CardContent class="p-4 sm:p-6">
                        <div v-if="previewLoading" class="flex h-48 items-center justify-center">
                            <div class="flex flex-col items-center gap-2 text-muted-foreground">
                                <Loader2 class="h-6 w-6 animate-spin text-primary" />
                                <span class="text-xs">Loading report dataset...</span>
                            </div>
                        </div>

                        <div
                            v-else-if="!previewData?.records?.length"
                            class="flex h-48 flex-col items-center justify-center p-6 text-center text-muted-foreground"
                        >
                            <AlertCircle class="h-8 w-8 text-muted-foreground/60" />
                            <p class="mt-2 text-sm font-semibold text-foreground">No records found</p>
                            <p class="text-xs text-muted-foreground">No transaction data matches the selected date range and filter criteria.</p>
                        </div>

                        <!-- SimpleTable Component -->
                        <div v-else>
                            <SimpleTable
                                ref="tableRef"
                                :data="previewData?.records || []"
                                :columns="reportColumns"
                                searchable
                                :per-page="10"
                                mode="client"
                            >
                                <!-- ID Column -->
                                <template v-slot:[`cell-id`]="{ row }">
                                    <span class="font-mono text-xs font-bold">#{{ row.id }}</span>
                                </template>

                                <!-- Tourist Column -->
                                <template v-slot:[`cell-tourist_name`]="{ row }">
                                    <div class="flex flex-col">
                                        <span class="font-semibold text-sm text-foreground">{{ row.tourist_name }}</span>
                                        <span v-if="row.nationality" class="text-[11px] text-muted-foreground">{{ row.nationality }}</span>
                                    </div>
                                </template>

                                <!-- Number of Guests Column -->
                                <template v-slot:[`cell-number_of_guests`]="{ row }">
                                    <span class="font-bold text-sm">{{ row.number_of_guests }}</span>
                                </template>

                                <!-- Booking Status Column -->
                                <template v-slot:[`cell-booking_status`]="{ row }">
                                    <Badge
                                        variant="outline"
                                        :class="[
                                            'capitalize font-semibold text-[11px]',
                                            row.booking_status === 'confirmed'
                                                ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30'
                                                : (row.booking_status === 'cancelled'
                                                    ? 'bg-red-500/10 text-red-600 dark:text-red-400 border-red-500/30'
                                                    : 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/30')
                                        ]"
                                    >
                                        {{ row.booking_status }}
                                    </Badge>
                                </template>

                                <!-- Payment Status Column -->
                                <template v-slot:[`cell-payment_status`]="{ row }">
                                    <Badge
                                        variant="outline"
                                        :class="[
                                            'capitalize font-semibold text-[11px]',
                                            row.payment_status === 'paid' || row.payment_status === 'confirmed'
                                                ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30'
                                                : (row.payment_status === 'partial'
                                                    ? 'bg-sky-500/10 text-sky-700 dark:text-sky-300 border-sky-500/30'
                                                    : 'bg-muted text-muted-foreground border-border')
                                        ]"
                                    >
                                        {{ row.payment_status }}
                                    </Badge>
                                </template>

                                <!-- Tour Schedule Booked Guests Column -->
                                <template v-slot:[`cell-booked_guests`]="{ row }">
                                    <span class="font-bold text-sm text-emerald-600 dark:text-emerald-400">{{ row.booked_guests }}</span>
                                </template>

                                <!-- Tour Schedule Available Slots Column -->
                                <template v-slot:[`cell-available_slots`]="{ row }">
                                    <span class="font-bold text-sm text-primary">{{ row.available_slots }}</span>
                                </template>

                                <!-- Tour Schedule Occupancy Percent Column -->
                                <template v-slot:[`cell-occupancy_percent`]="{ row }">
                                    <span class="font-medium text-sm">{{ row.occupancy_percent }}%</span>
                                </template>

                                <!-- Weather Advisory Badge Column -->
                                <template v-slot:[`cell-advisory_badge`]="{ row }">
                                    <Badge
                                        variant="outline"
                                        :class="[
                                            'capitalize font-semibold text-[11px]',
                                            row.advisory_level === 'green'
                                                ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30'
                                                : (row.advisory_level === 'yellow'
                                                    ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/30'
                                                    : 'bg-red-500/10 text-red-600 dark:text-red-400 border-red-500/30')
                                        ]"
                                    >
                                        {{ row.advisory_badge || row.advisory_level }}
                                    </Badge>
                                </template>

                                <!-- Booking ID Column in Payments -->
                                <template v-slot:[`cell-booking_id`]="{ row }">
                                    <span class="font-mono text-xs font-semibold">#BOOK-{{ row.booking_id }}</span>
                                </template>

                                <!-- Amount Column in Payments -->
                                <template v-slot:[`cell-amount`]="{ row }">
                                    <span class="font-bold text-sm text-foreground">
                                        ₱{{ Number(row.amount).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}
                                    </span>
                                </template>

                                <!-- Price Column in Package Performance -->
                                <template v-slot:[`cell-price`]="{ row }">
                                    <span class="font-semibold text-sm">
                                        ₱{{ Number(row.price).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}
                                    </span>
                                </template>

                                <!-- Confirmed Bookings in Package Performance -->
                                <template v-slot:[`cell-confirmed_bookings`]="{ row }">
                                    <span class="font-bold text-sm text-emerald-600 dark:text-emerald-400">{{ row.confirmed_bookings }}</span>
                                </template>

                                <!-- Revenue in Package Performance -->
                                <template v-slot:[`cell-revenue`]="{ row }">
                                    <span class="font-extrabold text-sm text-emerald-600 dark:text-emerald-400">
                                        ₱{{ Number(row.revenue).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}
                                    </span>
                                </template>

                                <!-- Pickup Schedule Guests Count -->
                                <template v-slot:[`cell-guests_count`]="{ row }">
                                    <span class="font-bold text-sm text-primary">{{ row.guests_count }}</span>
                                </template>

                                <!-- Pickup Schedule Passenger Manifest -->
                                <template v-slot:[`cell-passenger_manifest`]="{ row }">
                                    <span class="text-xs text-muted-foreground">
                                        {{ row.passengers?.length || 0 }} Passenger Party(ies)
                                    </span>
                                </template>

                                <!-- Weather Advisory Wind / Rain Column -->
                                <template v-slot:[`cell-wind_rain`]="{ row }">
                                    <span class="text-xs text-muted-foreground">
                                        {{ row.wind_speed }} • {{ row.rainfall_probability }}
                                    </span>
                                </template>
                            </SimpleTable>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
