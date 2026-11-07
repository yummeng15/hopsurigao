<!-- resources/js/components/weather/WeatherAdvisoryCard.vue -->
<script setup lang="ts">
import { computed } from 'vue';
import WeatherAdvisoryBadge from './WeatherAdvisoryBadge.vue';
import {
    Sun,
    CloudSun,
    CloudRain,
    CloudLightning,
    Cloud,
    Wind,
    Droplets,
    Thermometer,
    Compass,
    CalendarDays,
    MapPin,
    AlertCircle,
    Info,
} from '@lucide/vue';

export type WeatherForecastData = {
    id?: number;
    tour_date_id?: number;
    forecast_date?: string;
    location?: string;
    temperature_min?: number | string | null;
    temperature_max?: number | string | null;
    temperature_current?: number | string | null;
    weather_condition?: string;
    weather_code?: number | null;
    rainfall_probability?: number | null;
    rainfall?: number | string | null;
    wind_speed?: number | string | null;
    wind_gusts?: number | string | null;
    wind_direction?: number | null;
    humidity?: number | null;
    advisory_level?: 'green' | 'yellow' | 'orange' | 'red' | string;
    advisory_badge?: string;
    advisory_message?: string;
    is_outdated?: boolean;
    fetched_at?: string | null;
};

const props = withDefaults(
    defineProps<{
        forecast?: WeatherForecastData | null;
        tourDate?: string | null;
        destination?: string | null;
        packageName?: string | null;
        compact?: boolean;
    }>(),
    {
        forecast: null,
        tourDate: undefined,
        destination: undefined,
        packageName: undefined,
        compact: false,
    }
);

const level = computed(() => props.forecast?.advisory_level || 'green');

const weatherIcon = computed(() => {
    const code = props.forecast?.weather_code;
    if (code === null || code === undefined) {
        return CloudSun;
    }

    if (code === 0 || code === 1) return Sun;
    if (code === 2 || code === 3) return CloudSun;
    if (code >= 45 && code <= 48) return Cloud;
    if (code >= 51 && code <= 67) return CloudRain;
    if (code >= 80 && code <= 82) return CloudRain;
    if (code >= 95) return CloudLightning;

    return CloudSun;
});

const bannerClass = computed(() => {
    switch (level.value) {
        case 'red':
            return 'bg-red-500/10 border-red-500/30 text-red-700 dark:text-red-300';
        case 'orange':
            return 'bg-orange-500/10 border-orange-500/30 text-orange-700 dark:text-orange-300';
        case 'yellow':
            return 'bg-amber-500/10 border-amber-500/30 text-amber-700 dark:text-amber-300';
        case 'green':
        default:
            return 'bg-emerald-500/10 border-emerald-500/30 text-emerald-700 dark:text-emerald-300';
    }
});

function formatDate(dateStr: string | null | undefined): string {
    if (!dateStr) return 'Scheduled Tour Date';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    return d.toLocaleDateString('en-US', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function formatRelativeTime(isoStr: string | null | undefined): string {
    if (!isoStr) return 'Recently';
    const d = new Date(isoStr);
    if (isNaN(d.getTime())) return 'Recently';
    return d.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <div
        v-if="forecast"
        class="rounded-3xl border bg-card/70 p-5 backdrop-blur shadow-sm sm:p-6 space-y-4"
    >
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                    <component :is="weatherIcon" class="h-5 w-5" />
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-base font-bold text-foreground">
                            Weather Advisory & Forecast
                        </h3>
                        <span
                            v-if="forecast.is_outdated"
                            class="rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 px-2 py-0.5 text-[10px] font-semibold"
                        >
                            Cached / Fallback
                        </span>
                    </div>
                    <p class="text-xs text-muted-foreground flex items-center gap-1 mt-0.5">
                        <CalendarDays class="h-3.5 w-3.5 text-primary shrink-0" />
                        <span>{{ formatDate(forecast.forecast_date || tourDate) }}</span>
                        <span v-if="destination || forecast.location" class="ml-1 flex items-center gap-1">
                            • <MapPin class="h-3 w-3 text-muted-foreground" />
                            {{ destination || forecast.location }}
                        </span>
                    </p>
                </div>
            </div>

            <!-- Severity Badge -->
            <div class="shrink-0">
                <WeatherAdvisoryBadge
                    :level="forecast.advisory_level"
                    :badge-text="forecast.advisory_badge"
                    size="md"
                />
            </div>
        </div>

        <!-- Advisory Notice Alert Box -->
        <div
            class="rounded-2xl border p-4 flex items-start gap-3 transition-colors"
            :class="bannerClass"
        >
            <AlertCircle class="h-5 w-5 shrink-0 mt-0.5" />
            <div class="space-y-1 text-xs leading-relaxed">
                <p class="font-bold text-sm">
                    {{ forecast.weather_condition || 'Fair Weather' }}
                </p>
                <p class="text-foreground/90">
                    {{ forecast.advisory_message }}
                </p>
            </div>
        </div>

        <!-- Metric Cards Grid -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <!-- Temperature -->
            <div class="rounded-2xl border bg-background/60 p-3 backdrop-blur space-y-1">
                <span class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                    <Thermometer class="h-3.5 w-3.5 text-primary" />
                    Temp Range
                </span>
                <p class="text-base font-bold text-foreground">
                    <span v-if="forecast.temperature_max !== null && forecast.temperature_max !== undefined">
                        {{ Math.round(Number(forecast.temperature_max)) }}°C
                    </span>
                    <span v-if="forecast.temperature_min !== null && forecast.temperature_min !== undefined" class="text-xs font-normal text-muted-foreground ml-1">
                        / {{ Math.round(Number(forecast.temperature_min)) }}°C
                    </span>
                </p>
                <p v-if="forecast.temperature_current !== null && forecast.temperature_current !== undefined" class="text-[10px] text-muted-foreground">
                    Current: {{ Math.round(Number(forecast.temperature_current)) }}°C
                </p>
            </div>

            <!-- Wind Speed -->
            <div class="rounded-2xl border bg-background/60 p-3 backdrop-blur space-y-1">
                <span class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                    <Wind class="h-3.5 w-3.5 text-primary" />
                    Wind Speed
                </span>
                <p class="text-base font-bold text-foreground">
                    {{ Number(forecast.wind_speed || 0).toFixed(1) }} <span class="text-xs font-normal text-muted-foreground">km/h</span>
                </p>
                <p v-if="forecast.wind_gusts" class="text-[10px] text-muted-foreground">
                    Gusts: {{ Number(forecast.wind_gusts).toFixed(1) }} km/h
                </p>
            </div>

            <!-- Rain Probability -->
            <div class="rounded-2xl border bg-background/60 p-3 backdrop-blur space-y-1">
                <span class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                    <Droplets class="h-3.5 w-3.5 text-primary" />
                    Rain Chance
                </span>
                <p class="text-base font-bold text-foreground">
                    {{ forecast.rainfall_probability ?? 0 }}%
                </p>
                <p class="text-[10px] text-muted-foreground">
                    Precip: {{ Number(forecast.rainfall || 0).toFixed(1) }} mm
                </p>
            </div>

            <!-- Humidity / Direction -->
            <div class="rounded-2xl border bg-background/60 p-3 backdrop-blur space-y-1">
                <span class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                    <Compass class="h-3.5 w-3.5 text-primary" />
                    Atmosphere
                </span>
                <p class="text-base font-bold text-foreground">
                    {{ forecast.humidity ?? 65 }}% <span class="text-xs font-normal text-muted-foreground">Humidity</span>
                </p>
                <p v-if="forecast.wind_direction !== null && forecast.wind_direction !== undefined" class="text-[10px] text-muted-foreground">
                    Bearing: {{ forecast.wind_direction }}°
                </p>
            </div>
        </div>

        <!-- Maritime Safety Disclaimer & Fetch Timestamp -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between text-[11px] text-muted-foreground pt-1 gap-2 border-t">
            <p class="flex items-center gap-1">
                <Info class="h-3 w-3 shrink-0 text-primary" />
                <span>Automated forecast based on meteorological data. Tour departure remains subject to Coast Guard & operator discretion.</span>
            </p>
            <span v-if="forecast.fetched_at" class="shrink-0 text-[10px]">
                Updated {{ formatRelativeTime(forecast.fetched_at) }}
            </span>
        </div>
    </div>

    <!-- Empty State if no forecast available -->
    <div
        v-else
        class="rounded-3xl border border-dashed bg-card/40 p-6 text-center text-xs text-muted-foreground space-y-2"
    >
        <CloudSun class="h-8 w-8 mx-auto text-muted-foreground/60" />
        <p class="font-medium text-foreground">Weather Advisory Pending</p>
        <p class="text-[11px]">
            Meteorological forecast for this scheduled date will automatically update as the tour approaches.
        </p>
    </div>
</template>

