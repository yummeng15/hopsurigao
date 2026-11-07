<!-- resources/js/pages/admin/weather-advisories/Index.vue -->
<script setup lang="ts">
/*
|--------------------------------------------------------------------------
| Imports
|--------------------------------------------------------------------------
*/

import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

import SimpleTable from '@/components/tables/DataTable.vue';
import {
    CloudSun,
    RefreshCw,
    Eye,
    Sun,
    CloudRain,
    CloudLightning,
    Ban,
    Cloud,
    Wind,
    Droplets,
    Thermometer,
    MapPin,
    CalendarDays,
} from '@lucide/vue';

import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

import WeatherAdvisoryBadge from '@/components/weather/WeatherAdvisoryBadge.vue';
import WeatherAdvisoryCard, { type WeatherForecastData } from '@/components/weather/WeatherAdvisoryCard.vue';

import {
    index,
    data,
    refresh,
    refreshAll,
    travel,
} from '@/routes/admin/weather-advisories';

/*
|--------------------------------------------------------------------------
| Types
|--------------------------------------------------------------------------
*/

interface WeatherRow extends WeatherForecastData {
    id: number;
    tour_date_id: number;
    travel_closed?: boolean;
    booking_closed?: boolean;
    booking_closed_reason?: string | null;
    tour_date: string;
    package_id: number;
    package_name: string;
    destination: string;
    capacity: number;
}

/*
|--------------------------------------------------------------------------
| State
|--------------------------------------------------------------------------
*/

const tableRef = ref();
const refreshingAll = ref(false);
const refreshingId = ref<number | null>(null);
const closingId = ref<number | null>(null);
const viewingRow = ref<WeatherRow | null>(null);

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function formatDate(dateStr: string | null | undefined): string {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    return d.toLocaleDateString('en-US', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function getDaysRemaining(dateStr: string): string {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const target = new Date(dateStr);
    target.setHours(0, 0, 0, 0);

    const diffTime = target.getTime() - today.getTime();
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

    if (diffDays === 0) return 'Today';
    if (diffDays === 1) return 'Tomorrow';
    if (diffDays > 1) return `In ${diffDays} days`;
    return 'Past date';
}

function getWeatherIcon(code: number | null | undefined) {
    if (code === null || code === undefined) return CloudSun;
    if (code === 0 || code === 1) return Sun;
    if (code === 2 || code === 3) return CloudSun;
    if (code >= 45 && code <= 48) return Cloud;
    if (code >= 51 && code <= 67) return CloudRain;
    if (code >= 80 && code <= 82) return CloudRain;
    if (code >= 95) return CloudLightning;
    return CloudSun;
}

/*
|--------------------------------------------------------------------------
| Actions
|--------------------------------------------------------------------------
*/

const handleRefreshAll = () => {
    if (refreshingAll.value) return;
    refreshingAll.value = true;

    router.post(
        refreshAll().url,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                tableRef.value?.clearCache('current');
            },
            onFinish: () => {
                refreshingAll.value = false;
            },
        }
    );
};

const handleToggleTravel = (row: WeatherRow) => {
    if (closingId.value === row.id) {
        return;
    }

    closingId.value = row.id;

    router.post(travel(row.tour_date_id).url, {}, {
        preserveScroll: true,
        onSuccess: () => {
            tableRef.value?.clearCache('current');
        },
        onFinish: () => {
            closingId.value = null;
        },
    });
};

const handleRefreshSingle = (row: WeatherRow) => {
    if (refreshingId.value === row.id) return;
    refreshingId.value = row.id;

    router.post(
        refresh(row.tour_date_id).url,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                tableRef.value?.clearCache('current');
            },
            onFinish: () => {
                refreshingId.value = null;
            },
        }
    );
};

/*
|--------------------------------------------------------------------------
| Table Columns
|--------------------------------------------------------------------------
*/

const columns = [
    {
        key: 'tour_date',
        label: 'Tour Date',
        sortable: true,
        width: '150px',
    },
    {
        key: 'package',
        label: 'Package & Destination',
        sortable: false,
    },
    {
        key: 'weather_condition',
        label: 'Forecast',
        sortable: false,
    },
    {
        key: 'wind',
        label: 'Wind Speed',
        sortable: false,
    },
    {
        key: 'rain',
        label: 'Precipitation',
        sortable: false,
    },
    {
        key: 'advisory',
        label: 'Advisory Status',
        sortable: false,
    },
    {
        key: 'actions',
        label: 'Actions',
        sortable: false,
        width: '160px',
    },
];

/*
|--------------------------------------------------------------------------
| Page Options
|--------------------------------------------------------------------------
*/

defineOptions({
    layout: {
        breadcrumbs: [
            {
                href: index(),
                title: 'Weather Advisory',
            },
        ],
    },
});
</script>

<template>
    <Head title="Weather Advisory" />

    <div class="px-4 py-6 space-y-6">
        <!-- ==========================================================
             Page Header & Top Actions
        =========================================================== -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <Heading
                title="Weather Advisories"
                description="Live meteorological monitoring and automated marine safety advisories for upcoming tours"
            />

            <Button
                variant="outline"
                class="gap-2 shadow-xs"
                :disabled="refreshingAll"
                @click="handleRefreshAll"
            >
                <RefreshCw
                    class="h-4 w-4"
                    :class="{ 'animate-spin': refreshingAll }"
                />
                <span>{{ refreshingAll ? 'Updating Forecasts...' : 'Refresh All Forecasts' }}</span>
            </Button>
        </div>

        <!-- ==========================================================
             Data Table
        =========================================================== -->
        <div class="mt-4">
            <SimpleTable
                ref="tableRef"
                :fetch-url="data().url"
                :columns="columns"
                searchable
                enable-cache
            >
                <!-- Tour Date Column -->
                <template v-slot:[`cell-tour_date`]="{ row }">
                    <div class="flex flex-col">
                        <span class="font-semibold text-sm text-foreground">
                            {{ formatDate(row.tour_date) }}
                        </span>
                        <span class="text-xs text-primary font-medium">
                            {{ getDaysRemaining(row.tour_date) }}
                        </span>
                    </div>
                </template>

                <!-- Package Column -->
                <template v-slot:[`cell-package`]="{ row }">
                    <div class="flex flex-col">
                        <span class="font-medium text-sm text-foreground">
                            {{ row.package_name }}
                        </span>
                        <span class="flex items-center gap-1 text-xs text-muted-foreground">
                            <MapPin class="h-3 w-3 text-primary" />
                            {{ row.destination }}
                        </span>
                    </div>
                </template>

                <!-- Weather Condition & Temp Column -->
                <template v-slot:[`cell-weather_condition`]="{ row }">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <component :is="getWeatherIcon(row.weather_code)" class="h-4 w-4" />
                        </div>
                        <div class="flex flex-col">
                            <span class="text-xs font-semibold text-foreground">
                                {{ row.weather_condition }}
                            </span>
                            <span class="text-[11px] text-muted-foreground">
                                <span v-if="row.temperature_max !== null">
                                    {{ Math.round(row.temperature_max) }}°C
                                </span>
                                <span v-if="row.temperature_min !== null" class="text-muted-foreground/80">
                                    / {{ Math.round(row.temperature_min) }}°C
                                </span>
                            </span>
                        </div>
                    </div>
                </template>

                <!-- Wind Column -->
                <template v-slot:[`cell-wind`]="{ row }">
                    <div class="flex items-center gap-1.5 text-xs">
                        <Wind class="h-3.5 w-3.5 text-muted-foreground shrink-0" />
                        <div>
                            <span class="font-bold text-foreground">
                                {{ Number(row.wind_speed || 0).toFixed(1) }} km/h
                            </span>
                            <span v-if="row.wind_gusts" class="block text-[10px] text-muted-foreground">
                                Gusts: {{ Number(row.wind_gusts).toFixed(1) }} km/h
                            </span>
                        </div>
                    </div>
                </template>

                <!-- Rain Column -->
                <template v-slot:[`cell-rain`]="{ row }">
                    <div class="flex items-center gap-1.5 text-xs">
                        <Droplets class="h-3.5 w-3.5 text-blue-500 shrink-0" />
                        <div>
                            <span class="font-bold text-foreground">
                                {{ row.rainfall_probability ?? 0 }}%
                            </span>
                            <span class="block text-[10px] text-muted-foreground">
                                {{ Number(row.rainfall || 0).toFixed(1) }} mm
                            </span>
                        </div>
                    </div>
                </template>

                <!-- Advisory Column -->
                <template v-slot:[`cell-advisory`]="{ row }">
                    <div class="flex flex-col gap-1 items-start">
                        <WeatherAdvisoryBadge
                            :level="row.advisory_level"
                            :badge-text="row.advisory_badge"
                            size="sm"
                        />
                        <span
                            v-if="row.booking_closed"
                            class="text-[10px] font-medium text-destructive"
                        >
                            Booking closed
                        </span>
                        <span
                            v-if="row.is_outdated"
                            class="text-[10px] text-amber-600 dark:text-amber-400 font-medium"
                        >
                            Cached fallback
                        </span>
                    </div>
                </template>

                <!-- Actions Column -->
                <template v-slot:[`cell-actions`]="{ row }">
                    <div class="inline-flex items-center gap-1.5">
                        <Button
                            variant="outline"
                            size="icon"
                            title="View full weather details"
                            @click="viewingRow = row"
                        >
                            <Eye class="h-4 w-4" />
                        </Button>

                        <Button
                            :variant="row.travel_closed ? 'destructive' : 'outline'"
                            size="icon"
                            :title="row.travel_closed ? 'Reopen travel' : 'Close travel for this date'"
                            :disabled="closingId === row.id"
                            @click="handleToggleTravel(row)"
                        >
                            <Ban class="h-4 w-4" />
                        </Button>

                        <Button
                            variant="outline"
                            size="icon"
                            title="Refresh forecast from API"
                            :disabled="refreshingId === row.id"
                            @click="handleRefreshSingle(row)"
                        >
                            <RefreshCw
                                class="h-4 w-4"
                                :class="{ 'animate-spin': refreshingId === row.id }"
                            />
                        </Button>
                    </div>
                </template>
            </SimpleTable>
        </div>

        <!-- ==========================================================
             Detailed View Dialog
        =========================================================== -->
        <Dialog :open="!!viewingRow" @update:open="(val) => { if (!val) viewingRow = null; }">
            <DialogContent class="sm:max-w-2xl p-6 rounded-3xl">
                <DialogHeader class="pb-2">
                    <DialogTitle class="text-lg font-bold">
                        Tour Weather Advisory Details
                    </DialogTitle>
                    <DialogDescription class="text-xs">
                        Comprehensive meteorological metrics for {{ viewingRow?.package_name }}
                    </DialogDescription>
                </DialogHeader>

                <div v-if="viewingRow" class="mt-2">
                    <WeatherAdvisoryCard
                        :forecast="viewingRow"
                        :tour-date="viewingRow.tour_date"
                        :destination="viewingRow.destination"
                        :package-name="viewingRow.package_name"
                    />
                </div>

                <DialogFooter class="pt-4">
                    <Button variant="outline" @click="viewingRow = null">
                        Close
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>

