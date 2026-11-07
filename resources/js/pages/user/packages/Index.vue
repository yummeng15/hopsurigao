<script setup lang="ts">
/*
|--------------------------------------------------------------------------
| Imports
|--------------------------------------------------------------------------
*/

import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

import {
    ArrowRight,
    CalendarDays,
    CloudSun,
    Compass,
    Eye,
    Images,
    MapPin,
    PackageSearch,
    Sparkles,
    Tag,
} from '@lucide/vue';

import { index, show } from '@/routes/user/packages';
import PackageImageDialog from '@/pages/user/packages/PackageImageDialog.vue';
import PackagePhotoPlaceholder from '@/components/packages/PackagePhotoPlaceholder.vue';
import WeatherAdvisoryBadge from '@/components/weather/WeatherAdvisoryBadge.vue';
import { type WeatherForecastData } from '@/components/weather/WeatherAdvisoryCard.vue';

/*
|--------------------------------------------------------------------------
| Types
|--------------------------------------------------------------------------
*/

type PackageImage = {
    name: string;
    path: string;
    url: string;
};

type Package = {
    id: number;
    package_name: string;
    destination: string;
    island_count?: number;
    stops?: string[];
    description: string | null;
    price: number | string;
    status: string;
    tour_dates_count: number;
    next_tour_date?: {
        tour_date: string;
        weather_forecast?: WeatherForecastData | null;
    } | null;
    images: PackageImage[];
};

/*
|--------------------------------------------------------------------------
| Props
|--------------------------------------------------------------------------
*/

defineProps<{
    packages: Package[];
}>();

/*
|--------------------------------------------------------------------------
| State
|--------------------------------------------------------------------------
*/

const imageDialogOpen = ref(false);
const selectedPackageId = ref<number | null>(null);

function openImages(packageItem: Package) {
    selectedPackageId.value = packageItem.id;
    imageDialogOpen.value = true;
}

/*
|--------------------------------------------------------------------------
| Breadcrumbs
|--------------------------------------------------------------------------
*/

defineOptions({
    layout: {
        breadcrumbs: [
            {
                href: index(),
                title: 'Packages',
            },
        ],
    },
});

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function routeLabel(item: Package): string {
    const islands = item.island_count ?? 0;
    const stops = item.stops?.length ?? 0;

    if (islands > 0) {
        return islands === 1 ? '1 island' : `${islands} islands`;
    }

    if (stops > 0) {
        return stops === 1 ? '1 place' : `${stops} places`;
    }

    return '';
}

function formatPrice(price: number | string): string {
    return Number(price).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}
</script>

<template>
    <Head title="Tour Packages" />

    <div class="relative min-h-screen overflow-x-clip bg-background pb-8 text-foreground md:pb-20">
        <!-- Ambient Background Glows -->
        <div class="pointer-events-none absolute inset-0 -z-10 overflow-hidden">
            <div class="absolute left-1/2 top-[-10rem] h-[28rem] w-[42rem] -translate-x-1/2 rounded-full bg-primary/10 blur-3xl" />
            <div class="absolute right-[-10rem] top-64 h-72 w-72 rounded-full bg-primary/5 blur-3xl" />
        </div>

        <div class="mx-auto max-w-7xl px-4 py-4 md:px-5 md:py-8 lg:px-8">
            <!-- Desktop header -->
            <div class="mb-6 hidden flex-col gap-4 sm:flex-row sm:items-end sm:justify-between md:mb-0 md:flex">
                <div>
                    <div class="inline-flex items-center gap-2 rounded-full border bg-background/80 px-3 py-1 text-xs font-medium backdrop-blur shadow-xs">
                        <Sparkles class="h-3.5 w-3.5 text-primary" />
                        <span>Destinations & Itineraries</span>
                    </div>

                    <h1 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                        Tour Packages
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Find and book your preferred travel destinations and curated tour dates.
                    </p>
                </div>

                <div class="inline-flex items-center gap-2 rounded-2xl border bg-card/60 px-4 py-2 text-xs font-medium backdrop-blur">
                    <Compass class="h-4 w-4 text-primary" />
                    <span>{{ packages.length }} {{ packages.length === 1 ? 'Package Available' : 'Packages Available' }}</span>
                </div>
            </div>

            <div class="mb-3 flex items-center justify-between md:hidden">
                <p class="text-xs text-muted-foreground">
                    Pick a tour, then choose your date.
                </p>
                <span class="rounded-full bg-primary/10 px-2.5 py-1 text-[10px] font-semibold text-primary">
                    {{ packages.length }} available
                </span>
            </div>

            <!-- ==========================================================
                 PACKAGES GRID
            =========================================================== -->
            <div class="mt-2 md:mt-8">
                <div v-if="packages.length" class="grid grid-cols-1 gap-4 md:grid-cols-2 md:gap-6 lg:grid-cols-3">
                    <div
                        v-for="packageItem in packages"
                        :key="packageItem.id"
                        class="group flex flex-col justify-between overflow-hidden rounded-3xl border bg-card/60 backdrop-blur shadow-xs transition duration-300 hover:border-primary/40 hover:shadow-md"
                    >
                        <!-- Image Container with Gallery Trigger -->
                        <div>
                            <button
                                type="button"
                                class="relative block aspect-video w-full overflow-hidden bg-muted transition"
                                @click="openImages(packageItem)"
                            >
                                <img
                                    v-if="packageItem.images?.length"
                                    :src="packageItem.images[0].url"
                                    :alt="packageItem.package_name"
                                    class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                                />

                                <PackagePhotoPlaceholder
                                    v-else
                                    label="Photo coming soon"
                                />

                                <!-- Overlay Hover Badge -->
                                <div class="absolute inset-0 flex items-center justify-center bg-black/20 opacity-0 transition duration-200 group-hover:opacity-100">
                                    <span class="flex items-center gap-1.5 rounded-full bg-background/90 px-3 py-1.5 text-xs font-semibold text-foreground backdrop-blur shadow-xs">
                                        <Eye class="h-3.5 w-3.5 text-primary" />
                                        Preview Photos
                                    </span>
                                </div>

                                <!-- Photo Count Badge -->
                                <span
                                    v-if="packageItem.images?.length > 1"
                                    class="absolute bottom-3 right-3 flex items-center gap-1.5 rounded-full bg-black/60 px-2.5 py-1 text-xs font-medium text-white backdrop-blur"
                                >
                                    <Images class="h-3.5 w-3.5" />
                                    <span>{{ packageItem.images.length }}</span>
                                </span>
                            </button>

                            <!-- Package Content -->
                            <div class="space-y-4 p-6">
                                <div>
                                    <p class="inline-flex items-center gap-1.5 text-xs font-medium text-primary">
                                        <MapPin class="h-3.5 w-3.5 shrink-0" />
                                        <span>{{ packageItem.destination }}</span>
                                    </p>

                                    <h2 class="mt-1.5 text-lg font-bold tracking-tight text-foreground transition group-hover:text-primary">
                                        {{ packageItem.package_name }}
                                    </h2>
                                    <p v-if="routeLabel(packageItem)" class="mt-1 text-xs font-medium text-muted-foreground">
                                        {{ routeLabel(packageItem) }}
                                    </p>
                                </div>

                                <p
                                    v-if="packageItem.description"
                                    class="line-clamp-2 text-xs leading-relaxed text-muted-foreground"
                                >
                                    {{ packageItem.description }}
                                </p>

                                <!-- Upcoming Weather Preview -->
                                <div
                                    v-if="packageItem.next_tour_date?.weather_forecast"
                                    class="flex items-center justify-between gap-2 rounded-xl bg-background/80 px-3 py-2 border text-xs"
                                >
                                    <div class="flex items-center gap-1.5 min-w-0">
                                        <CloudSun class="h-3.5 w-3.5 text-primary shrink-0" />
                                        <span class="truncate text-foreground font-medium text-[11px]">
                                            {{ packageItem.next_tour_date.weather_forecast.weather_condition }}
                                            <span v-if="packageItem.next_tour_date.weather_forecast.temperature_max" class="text-muted-foreground">
                                                • {{ Math.round(Number(packageItem.next_tour_date.weather_forecast.temperature_max)) }}°C
                                            </span>
                                        </span>
                                    </div>
                                    <WeatherAdvisoryBadge
                                        :level="packageItem.next_tour_date.weather_forecast.advisory_level"
                                        :badge-text="packageItem.next_tour_date.weather_forecast.advisory_badge"
                                        size="sm"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Footer / Pricing & Actions -->
                        <div class="border-t bg-muted/20 p-4 pt-3 md:p-6 md:pt-4">
                            <div class="mb-3 flex items-baseline justify-between md:mb-4">
                                <div>
                                    <p class="flex items-center gap-1 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                                        <Tag class="h-3 w-3" />
                                        Starts from
                                    </p>
                                    <p class="mt-0.5 text-xl font-extrabold tracking-tight text-foreground">
                                        ₱{{ formatPrice(packageItem.price) }}
                                    </p>
                                </div>

                                <div class="text-right">
                                    <p class="flex items-center justify-end gap-1 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                                        <CalendarDays class="h-3 w-3" />
                                        Tour Dates
                                    </p>
                                    <span
                                        class="mt-0.5 inline-block text-xs font-semibold"
                                        :class="packageItem.tour_dates_count > 0 ? 'text-primary' : 'text-muted-foreground'"
                                    >
                                        {{ packageItem.tour_dates_count > 0 ? `${packageItem.tour_dates_count} available` : 'No dates set' }}
                                    </span>
                                </div>
                            </div>

                            <Link
                                :href="show(packageItem.id)"
                                class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-2xl bg-primary px-4 py-3 text-sm font-semibold text-primary-foreground shadow-sm transition duration-200 hover:opacity-90 md:rounded-xl md:text-xs"
                            >
                                <span class="md:hidden">Book this tour</span>
                                <span class="hidden md:inline">View Package Details</span>
                                <ArrowRight class="h-3.5 w-3.5" />
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else class="rounded-3xl border border-dashed bg-card/40 p-12 text-center backdrop-blur">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                        <PackageSearch class="h-6 w-6" />
                    </div>

                    <h2 class="mt-4 text-base font-bold">No packages available</h2>
                    <p class="mx-auto mt-1 max-w-sm text-xs text-muted-foreground">
                        There are currently no active tour packages available. Please check back soon for upcoming itineraries.
                    </p>
                </div>
            </div>
        </div>

        <!-- Gallery Dialog -->
        <PackageImageDialog
            :open="imageDialogOpen"
            :package-id="selectedPackageId"
            @update:open="(value: boolean) => (imageDialogOpen = value)"
        />
    </div>
</template>