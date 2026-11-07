<script setup lang="ts">
/*
|--------------------------------------------------------------------------
| Imports
|--------------------------------------------------------------------------
*/

import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

import { Button } from '@/components/ui/button';

import {
    ArrowLeft,
    ArrowRight,
    BadgeCheck,
    Bus,
    CalendarDays,
    CheckCircle2,
    Clock,
    Droplets,
    Clock3,
    CloudSun,
    Compass,
    ImageOff,
    Info,
    ListChecks,
    MapPin,
    Shield,
    Ship,
    Maximize2,
    Sparkles,
    Tag,
    UserRound,
    Utensils,
    Waves,
    Users,
} from '@lucide/vue';

import { index } from '@/routes/user/packages';
import { create as createBookingRoute } from '@/routes/user/bookings';
import MonthCalendar, { type CalendarMarker } from '@/components/bookings/MonthCalendar.vue';
import TourRoute from '@/components/tours/TourRoute.vue';
import PickupMap from '@/components/maps/PickupMap.vue';
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

type Inclusion = {
    id: number;
    description: string;
};

type Reminder = {
    id: number;
    description: string;
};

type PickupLocation = {
    id: number;
    name: string;
    address?: string | null;
    latitude?: number | string | null;
    longitude?: number | string | null;
};

type PickupSchedule = {
    id: number;
    pickup_location_id: number;
    pickup_time: string;
    pickup_location?: PickupLocation;
};

type TourTime = {
    id: number;
    tour_date_id?: number;
    time: string;
    description: string | null;
};

type TourDate = {
    id: number;
    package_id: number;
    tour_date: string;
    capacity: number;
    booked_guests: number;
    remaining_capacity: number;
    is_full: boolean;
    booking_closed?: boolean;
    booking_closed_reason?: string | null;
    times: TourTime[];
    pickup_schedules: PickupSchedule[];
    weather_forecast?: WeatherForecastData | null;
};

type Activity = {
    id: number;
    description: string;
};

type Package = {
    id: number;
    package_name: string;
    boat_name?: string | null;
    destination: string;
    stops?: string[];
    island_count?: number;
    description: string | null;
    price: number | string;
    status: string;
    inclusions: Inclusion[];
    activities?: Activity[];
    reminders: Reminder[];
    tour_dates: TourDate[];
    operator_name?: string | null;
    admin_name?: string | null;
};

/*
|--------------------------------------------------------------------------
| Props
|--------------------------------------------------------------------------
*/

const props = defineProps<{
    package: Package;
    images: PackageImage[];
}>();

/*
|--------------------------------------------------------------------------
| State
|--------------------------------------------------------------------------
*/

const activeImageIndex = ref(0);

const selectedTourDateId = ref<number | null>(null);

const selectedPickupByDate = ref<Record<number, number>>({});

function pickupFor(tourDate: TourDate): PickupSchedule | null {
    const selectedId = selectedPickupByDate.value[tourDate.id];
    const selected = tourDate.pickup_schedules.find((pickup) => pickup.id === selectedId);

    return selected ?? tourDate.pickup_schedules[0] ?? null;
}

function selectPickup(tourDateId: number, pickupId: number): void {
    selectedPickupByDate.value = {
        ...selectedPickupByDate.value,
        [tourDateId]: pickupId,
    };
}

const calendarMarkers = computed<Record<string, CalendarMarker>>(() => {
    return Object.fromEntries(
        props.package.tour_dates.map((tourDate) => {
            const tone: CalendarMarker['tone'] = tourDate.is_full
                ? 'full'
                : tourDate.remaining_capacity <= 5
                    ? 'limited'
                    : 'available';

            return [tourDate.tour_date.slice(0, 10), { tone }];
        }),
    );
});

const firstTourDate = computed(() => props.package.tour_dates[0]?.tour_date.slice(0, 10) ?? null);

const selectedTourDate = computed(
    () => props.package.tour_dates.find((item) => item.id === selectedTourDateId.value) ?? null,
);

const mobileSelectedDates = computed(() => {
    if (selectedTourDate.value) {
        return [selectedTourDate.value];
    }

    return props.package.tour_dates.slice(0, 1);
});

const canBookSelected = computed(() => {
    const tourDate = selectedTourDate.value;

    return Boolean(tourDate && !tourDate.is_full && !tourDate.booking_closed);
});

const selectedBookingHref = computed(() => {
    if (!selectedTourDate.value) {
        return '#';
    }

    return createBookingRoute({ query: { tour_date: selectedTourDate.value.id } }).url;
});

const stickyCtaLabel = computed(() => {
    const tourDate = selectedTourDate.value;

    if (!tourDate) {
        return 'Pick a date';
    }

    if (tourDate.booking_closed) {
        return 'Travel closed';
    }

    if (tourDate.is_full) {
        return 'Fully booked';
    }

    return 'Book this date';
});

function selectTourDate(date: string): void {
    const tourDate = props.package.tour_dates.find(
        (item) => item.tour_date.slice(0, 10) === date,
    );

    if (!tourDate) {
        return;
    }

    selectedTourDateId.value = tourDate.id;

    const target =
        document.getElementById(`tour-date-${tourDate.id}`)
        ?? document.getElementById(`tour-date-desktop-${tourDate.id}`);

    target?.scrollIntoView({
        behavior: 'smooth',
        block: 'center',
    });
}

function selectTourDateCard(tourDateId: number): void {
    selectedTourDateId.value = tourDateId;
}

onMounted(() => {
    const firstOpen = props.package.tour_dates.find(
        (tourDate) => !tourDate.is_full && !tourDate.booking_closed,
    );

    selectedTourDateId.value = firstOpen?.id ?? props.package.tour_dates[0]?.id ?? null;
})
/*
|--------------------------------------------------------------------------
| Image Actions
|--------------------------------------------------------------------------
*/

function selectImage(index: number) {
    activeImageIndex.value = index;
}

function openExternalImage(url: string | undefined) {
    if (!url) return;
    window.open(url, '_blank', 'noopener,noreferrer');
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
| Formatting Helpers
|--------------------------------------------------------------------------
*/

function formatDate(date: string): string {
    const dateOnly = date?.slice(0, 10);
    if (!dateOnly) return '';

    const parsed = new Date(`${dateOnly}T00:00:00`);
    if (Number.isNaN(parsed.getTime())) return '';

    return parsed.toLocaleDateString('en-US', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function formatTime(time: string): string {
    if (!time) return '';

    const [hours, minutes] = time.split(':');
    const parsed = new Date();
    parsed.setHours(Number(hours), Number(minutes), 0, 0);

    return parsed.toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
    });
}

function inclusionIcon(description: string) {
    const text = description.toLowerCase();

    if (text.includes('van') || text.includes('land transport')) {
        return Bus;
    }

    if (text.includes('boat') || text.includes('cruise') || text.includes('bangka')) {
        return Ship;
    }

    if (text.includes('guide') || text.includes('captain') || text.includes('coordinator')) {
        return UserRound;
    }

    if (text.includes('lunch') || text.includes('snack') || text.includes('food') || text.includes('buffet')) {
        return Utensils;
    }

    if (text.includes('water') || text.includes('drink') || text.includes('coconut') || text.includes('beverage')) {
        return Droplets;
    }

    if (text.includes('vest') || text.includes('helmet') || text.includes('safety') || text.includes('insurance')) {
        return Shield;
    }

    if (text.includes('snorkel') || text.includes('swim') || text.includes('gear')) {
        return Waves;
    }

    return CheckCircle2;
}

function formatPrice(price: number | string): string {
    return Number(price).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}
</script>

<template>
    <Head :title="package.package_name" />

    <div class="relative min-h-screen overflow-x-clip bg-background pb-32 text-foreground md:pb-20">
        <!-- Decorative Ambient Background -->
        <div class="pointer-events-none absolute inset-0 -z-10 overflow-hidden">
            <div class="absolute left-1/2 top-[-10rem] h-[28rem] w-[42rem] -translate-x-1/2 rounded-full bg-primary/10 blur-3xl" />
            <div class="absolute right-[-10rem] top-64 h-72 w-72 rounded-full bg-primary/5 blur-3xl" />
        </div>

        <div class="mx-auto max-w-7xl px-4 py-4 md:px-5 md:py-8 lg:px-8">
            <!-- Back Navigation & Breadcrumb Tag -->
            <div class="mb-3 flex items-center justify-between md:mb-6">
                <Link
                    :href="index()"
                    class="inline-flex min-h-11 items-center gap-2 text-sm font-medium text-muted-foreground transition hover:text-foreground"
                >
                    <ArrowLeft class="h-4 w-4" />
                    <span class="md:hidden">Tours</span>
                    <span class="hidden md:inline">Back to Packages</span>
                </Link>

                <div class="hidden items-center gap-2 rounded-full border bg-background/80 px-3 py-1 text-xs font-medium shadow-xs backdrop-blur md:inline-flex">
                    <Sparkles class="h-3.5 w-3.5 text-primary" />
                    <span>Package Details</span>
                </div>
            </div>

            <div class="flex flex-col gap-5 md:gap-8">
                <!-- ==========================================================
                     Package Hero Card (Images + Summary)
                =========================================================== -->
                <section class="order-1 overflow-hidden rounded-3xl border bg-card/60 shadow-sm backdrop-blur md:p-6 lg:p-8">
                    <div class="grid grid-cols-1 gap-4 md:gap-8 lg:grid-cols-12 lg:items-start">
                        
                        <!-- Media Showcase -->
                        <div class="lg:col-span-7">
                            <div v-if="images.length" class="space-y-3">
                                <button
                                    type="button"
                                    class="group relative block aspect-[4/3] w-full overflow-hidden bg-muted shadow-xs transition md:aspect-video md:rounded-2xl md:border"
                                    @click="openExternalImage(images[activeImageIndex]?.url)"
                                >
                                    <img
                                        :src="images[activeImageIndex]?.url"
                                        :alt="images[activeImageIndex]?.name || package.package_name"
                                        class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                                    />

                                    <div class="absolute inset-0 flex items-center justify-center bg-black/20 opacity-0 transition duration-200 group-hover:opacity-100">
                                        <span class="flex items-center gap-1.5 rounded-full bg-background/90 px-4 py-2 text-xs font-semibold text-foreground shadow-sm backdrop-blur">
                                            <Maximize2 class="h-3.5 w-3.5 text-primary" />
                                            View Fullscreen
                                        </span>
                                    </div>

                                    <span class="absolute bottom-3 right-3 rounded-full bg-black/60 px-3 py-1 text-xs font-medium text-white backdrop-blur">
                                        {{ activeImageIndex + 1 }} / {{ images.length }}
                                    </span>
                                </button>

                                <!-- Gallery Thumbnails -->
                                <div v-if="images.length > 1" class="flex gap-2.5 overflow-x-auto px-4 pb-1 md:px-0">
                                    <button
                                        v-for="(img, idx) in images"
                                        :key="img.path"
                                        type="button"
                                        class="h-14 w-20 shrink-0 overflow-hidden rounded-xl border transition-all md:h-16 md:w-24"
                                        :class="activeImageIndex === idx ? 'border-primary ring-2 ring-primary/30' : 'opacity-60 hover:opacity-100'"
                                        @click="selectImage(idx)"
                                    >
                                        <img :src="img.url" :alt="img.name" class="h-full w-full object-cover" />
                                    </button>
                                </div>
                            </div>

                            <div
                                v-else
                                class="flex aspect-[4/3] w-full flex-col items-center justify-center border-dashed bg-muted/30 text-muted-foreground md:aspect-video md:rounded-2xl md:border"
                            >
                                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                                    <ImageOff class="h-6 w-6" />
                                </div>
                                <p class="mt-3 text-xs font-medium text-muted-foreground">No photos uploaded for this package</p>
                            </div>
                        </div>

                        <!-- Core Details & Price Summary -->
                        <div class="flex flex-col justify-between gap-4 px-4 pb-4 md:gap-6 md:px-0 md:pb-0 lg:col-span-5">
                            <div class="space-y-3 md:space-y-4">
                                <div class="inline-flex items-center gap-2 rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary">
                                    <MapPin class="h-3.5 w-3.5" />
                                    <span>{{ package.destination }}</span>
                                </div>

                                <h1 class="text-xl font-bold tracking-tight md:text-3xl lg:text-4xl">
                                    {{ package.package_name }}
                                </h1>

                                <p class="text-sm text-muted-foreground">
                                    Tour Operator: {{ package.operator_name || 'Unassigned' }}
                                    · Operator Admin: {{ package.admin_name || 'Unassigned' }}
                                </p>

                                <p v-if="package.description" class="line-clamp-3 whitespace-pre-line text-sm leading-relaxed text-muted-foreground md:line-clamp-none sm:text-base">
                                    {{ package.description }}
                                </p>
                            </div>

                            <!-- Pricing Badge Card -->
                            <div class="rounded-2xl border bg-muted/40 p-4 backdrop-blur md:p-5">
                                <div class="flex items-baseline justify-between">
                                    <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Per guest</p>
                                    <span class="rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-medium text-primary">Bookable</span>
                                </div>
                                
                                <div class="mt-2 flex items-baseline gap-1">
                                    <span class="text-2xl font-extrabold tracking-tight text-foreground md:text-4xl">₱{{ formatPrice(package.price) }}</span>
                                    <span class="text-xs text-muted-foreground">/ person</span>
                                </div>

                                <div class="mt-3 flex items-center gap-2 text-xs text-muted-foreground md:mt-4">
                                    <BadgeCheck class="h-4 w-4 text-primary" />
                                    <span>Includes tour features & taxes</span>
                                </div>
                            </div>
                        </div>

                    </div>
                </section>

                <!-- ==========================================================
                     Inclusions & Reminders (after dates on phones)
                =========================================================== -->
                <div
                    v-if="package.inclusions.length || package.activities?.length || package.reminders.length || package.stops?.length"
                    class="order-3 space-y-4 md:order-2 md:space-y-6"
                >
                    <section v-if="package.stops?.length" class="rounded-3xl border bg-card p-6 shadow-sm sm:p-7">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                <MapPin class="h-5 w-5" />
                            </div>
                            <div>
                                <h2 class="text-base font-bold">Destinations to visit</h2>
                                <p class="text-xs text-muted-foreground">The places on this tour, in order</p>
                            </div>
                        </div>

                        <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            <article
                                v-for="(stop, index) in package.stops"
                                :key="`${index}-${stop}`"
                                class="overflow-hidden rounded-2xl border bg-background/70"
                            >
                                <div class="bg-primary/10 px-4 py-3">
                                    <p class="text-[11px] font-semibold uppercase tracking-wider text-primary">
                                        {{ index === 0 ? 'First stop' : 'Next' }}
                                    </p>
                                </div>
                                <div class="flex items-start gap-3 p-4">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-bold text-primary-foreground">
                                        {{ index + 1 }}
                                    </span>
                                    <div>
                                        <h3 class="text-sm font-bold text-foreground">{{ stop }}</h3>
                                        <p class="mt-1 text-xs text-muted-foreground">Stop {{ index + 1 }} on this tour</p>
                                    </div>
                                </div>
                            </article>
                        </div>

                        <TourRoute
                            class="mt-5"
                            :stops="package.stops ?? []"
                            :island-count="package.island_count ?? 0"
                        />
                    </section>

                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <section v-if="package.inclusions.length" class="rounded-3xl border bg-card p-6 shadow-sm sm:p-7">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                <ListChecks class="h-5 w-5" />
                            </div>
                            <div>
                                <h2 class="text-base font-bold">Inclusions</h2>
                                <p class="text-xs text-muted-foreground">What is included in this package</p>
                            </div>
                        </div>

                        <ul class="mt-5 space-y-3">
                            <li
                                v-for="inclusion in package.inclusions"
                                :key="inclusion.id"
                                class="flex items-start gap-3 text-sm text-foreground"
                            >
                                <component :is="inclusionIcon(inclusion.description)" class="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                                <span>{{ inclusion.description }}</span>
                            </li>
                        </ul>
                    </section>

                    <section v-if="package.activities?.length" class="rounded-3xl border bg-card p-6 shadow-sm sm:p-7">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                <CheckCircle2 class="h-5 w-5" />
                            </div>
                            <div>
                                <h2 class="text-base font-bold">Activities</h2>
                                <p class="text-xs text-muted-foreground">What you can do on this tour</p>
                            </div>
                        </div>

                        <ul class="mt-5 space-y-3">
                            <li
                                v-for="activity in package.activities"
                                :key="activity.id"
                                class="flex items-start gap-3 text-sm text-foreground"
                            >
                                <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                                <span>{{ activity.description }}</span>
                            </li>
                        </ul>
                    </section>
                    </div>

                    <!-- Reminders Section -->
                    <section v-if="package.reminders.length" class="rounded-3xl border bg-card p-6 shadow-sm sm:p-7">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-orange-500/10 text-orange-600 dark:text-orange-400">
                                <Info class="h-5 w-5" />
                            </div>
                            <div>
                                <h2 class="text-base font-bold">Important Reminders</h2>
                                <p class="text-xs text-muted-foreground">Guidelines and requirements before your departure</p>
                            </div>
                        </div>

                        <ul class="mt-5 space-y-3">
                            <li
                                v-for="reminder in package.reminders"
                                :key="reminder.id"
                                class="flex items-start gap-3 text-sm text-foreground"
                            >
                                <span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-orange-500" />
                                <span>{{ reminder.description }}</span>
                            </li>
                        </ul>
                    </section>
                </div>

                <!-- ==========================================================
                     Available Tour Dates & Booking Slots
                =========================================================== -->
                <section class="order-2 rounded-3xl border bg-card p-4 shadow-sm md:order-3 md:p-6 sm:p-8">
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-primary">Step 1 — Pick a date</p>
                            <h2 class="text-lg font-bold tracking-tight md:text-2xl">Available Tour Dates</h2>
                        </div>
                        <p class="text-xs text-muted-foreground">Tap a date, then book below</p>
                    </div>

                    <div v-if="package.tour_dates.length" class="mt-4 grid grid-cols-1 gap-4 md:mt-6 md:gap-6 xl:grid-cols-[22rem_minmax(0,1fr)]">
                        <div class="space-y-3">
                            <MonthCalendar
                                :markers="calendarMarkers"
                                :selected="package.tour_dates.find((item) => item.id === selectedTourDateId)?.tour_date.slice(0, 10) ?? null"
                                :initial-date="firstTourDate"
                                @select="selectTourDate"
                            />

                            <div class="flex flex-wrap gap-3 px-1 text-xs text-muted-foreground">
                                <span class="inline-flex items-center gap-1.5">
                                    <span class="h-2.5 w-2.5 rounded-full bg-primary" />
                                    Seats open
                                </span>
                                <span class="inline-flex items-center gap-1.5">
                                    <span class="h-2.5 w-2.5 rounded-full bg-orange-500" />
                                    Few seats
                                </span>
                                <span class="inline-flex items-center gap-1.5">
                                    <span class="h-2.5 w-2.5 rounded-full bg-destructive" />
                                    Full
                                </span>
                            </div>
                        </div>

                        <!-- Mobile: only the selected date -->
                        <div class="space-y-4 md:hidden">
                            <div
                                v-for="tourDate in mobileSelectedDates"
                                :key="`mobile-${tourDate.id}`"
                                :id="`tour-date-${tourDate.id}`"
                                class="scroll-mt-24 rounded-2xl border bg-card/60 p-4 transition"
                                :class="selectedTourDateId === tourDate.id ? 'border-primary ring-2 ring-primary/30' : ''"
                                @click="selectTourDateCard(tourDate.id)"
                            >
                                <div class="flex items-start gap-3">
                                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                        <CalendarDays class="h-5 w-5" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <h3 class="text-base font-bold text-foreground">
                                            {{ formatDate(tourDate.tour_date) }}
                                        </h3>
                                        <p class="mt-1 text-xs text-muted-foreground">
                                            <span
                                                :class="tourDate.remaining_capacity > 0 ? 'font-medium text-primary' : 'font-medium text-destructive'"
                                            >
                                                {{ tourDate.remaining_capacity > 0 ? `${tourDate.remaining_capacity} seats left` : 'Fully booked' }}
                                            </span>
                                            <span v-if="package.boat_name"> · {{ package.boat_name }}</span>
                                        </p>
                                        <div
                                            v-if="tourDate.times.length"
                                            class="mt-2 space-y-1.5"
                                        >
                                            <p class="flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
                                                <Clock3 class="h-3 w-3 text-primary" />
                                                Tour Departure Time
                                            </p>
                                            <div class="flex flex-wrap gap-1.5">
                                                <span
                                                    v-for="time in tourDate.times"
                                                    :key="time.id"
                                                    class="inline-flex items-center rounded-lg border bg-muted/60 px-2.5 py-1 text-xs font-medium"
                                                >
                                                    {{ formatTime(time.time) }}
                                                    <span v-if="time.description" class="ml-1 font-normal text-muted-foreground">({{ time.description }})</span>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Desktop: full date list -->
                        <div class="hidden space-y-4 md:block">
                        <div
                            v-for="tourDate in package.tour_dates"
                            :key="tourDate.id"
                            :id="`tour-date-desktop-${tourDate.id}`"
                            class="scroll-mt-24 rounded-2xl border bg-card/60 p-5 transition hover:border-primary/40 hover:shadow-sm"
                            :class="selectedTourDateId === tourDate.id ? 'border-primary ring-2 ring-primary/30' : ''"
                            @click="selectTourDateCard(tourDate.id)"
                        >
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex items-start gap-4">
                                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                        <CalendarDays class="h-5 w-5" />
                                    </div>

                                    <div>
                                        <h3 class="text-base font-bold text-foreground">
                                            {{ formatDate(tourDate.tour_date) }}
                                        </h3>

                                        <p class="mt-1 flex flex-wrap items-center gap-2 text-xs">
                                            <span v-if="package.boat_name" class="inline-flex items-center gap-1 font-medium text-foreground">
                                                <Ship class="h-3.5 w-3.5 text-primary" />
                                                {{ package.boat_name }}
                                            </span>
                                            <span
                                                v-if="!tourDate.booking_closed && !tourDate.is_full"
                                                class="font-semibold text-primary"
                                            >
                                                Available
                                            </span>
                                        </p>

                                        <div class="mt-1 flex flex-wrap items-center gap-2 text-xs">
                                            <span class="text-muted-foreground">
                                                Capacity: <strong class="text-foreground">{{ tourDate.capacity }}</strong>
                                            </span>
                                            <span class="text-muted-foreground">•</span>
                                            <span
                                                :class="tourDate.remaining_capacity > 0 ? 'text-primary font-medium' : 'text-destructive font-medium'"
                                            >
                                                {{ tourDate.remaining_capacity > 0 ? `${tourDate.remaining_capacity} seats left` : 'Fully booked' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Action CTA -->
                                <div>
                                    <Button
                                        v-if="tourDate.booking_closed"
                                        disabled
                                        variant="outline"
                                        class="w-full cursor-not-allowed rounded-xl opacity-60 sm:w-auto"
                                        :title="tourDate.booking_closed_reason ?? 'Travel is closed'"
                                    >
                                        Travel closed
                                    </Button>

                                    <Button
                                        v-else-if="!tourDate.is_full"
                                        as-child
                                        class="w-full rounded-xl shadow-xs sm:w-auto"
                                    >
                                        <Link :href="createBookingRoute({ query: { tour_date: tourDate.id } }).url" class="inline-flex items-center gap-2">
                                            <span>Book This Date</span>
                                            <ArrowRight class="h-4 w-4" />
                                        </Link>
                                    </Button>

                                    <Button
                                        v-else
                                        disabled
                                        variant="outline"
                                        class="w-full cursor-not-allowed rounded-xl opacity-60 sm:w-auto"
                                    >
                                        Fully Booked
                                    </Button>
                                </div>
                            </div>

                            <!-- Capacity Progress Indicator -->
                            <div class="mt-4">
                                <div class="h-1.5 overflow-hidden rounded-full bg-muted">
                                    <div
                                        class="h-full rounded-full transition-all duration-300"
                                        :class="tourDate.is_full ? 'bg-destructive' : tourDate.remaining_capacity <= 5 ? 'bg-orange-500' : 'bg-primary'"
                                        :style="{
                                            width: tourDate.capacity > 0
                                                ? `${(tourDate.remaining_capacity / tourDate.capacity) * 100}%`
                                                : '0%',
                                        }"
                                    />
                                </div>
                            </div>

                            <!-- Tour Date Weather Forecast -->
                            <div
                                v-if="tourDate.weather_forecast"
                                class="mt-4 flex items-center justify-between gap-3 rounded-xl border bg-background/60 p-3 text-xs backdrop-blur"
                            >
                                <div class="flex items-center gap-2 min-w-0">
                                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                        <CloudSun class="h-3.5 w-3.5" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate text-xs font-bold text-foreground">
                                            {{ tourDate.weather_forecast.weather_condition }}
                                            <span v-if="tourDate.weather_forecast.temperature_max !== null" class="text-muted-foreground font-normal">
                                                • {{ Math.round(Number(tourDate.weather_forecast.temperature_max)) }}°C
                                            </span>
                                        </p>
                                        <p class="truncate text-[10px] text-muted-foreground">
                                            Wind: {{ Number(tourDate.weather_forecast.wind_speed || 0).toFixed(1) }} km/h • Rain Chance: {{ tourDate.weather_forecast.rainfall_probability ?? 0 }}%
                                        </p>
                                    </div>
                                </div>

                                <WeatherAdvisoryBadge
                                    :level="tourDate.weather_forecast.advisory_level"
                                    :badge-text="tourDate.weather_forecast.advisory_badge"
                                    size="sm"
                                />
                            </div>
                            <div
                                v-else
                                class="mt-4 flex items-center gap-2 rounded-xl border border-dashed bg-background/60 p-3 text-xs text-muted-foreground"
                            >
                                <CloudSun class="h-3.5 w-3.5 shrink-0 text-primary" />
                                <span>Weather advisory for this date appears here before you book.</span>
                            </div>

                            <!-- Tour Times & Pickup Meta -->
                            <div
                                v-if="tourDate.times.length || tourDate.pickup_schedules.length"
                                class="mt-4 grid grid-cols-1 gap-4 border-t pt-4 sm:grid-cols-2"
                            >
                                <!-- Times -->
                                <div v-if="tourDate.times.length" class="space-y-2">
                                    <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                        <Clock3 class="h-3.5 w-3.5 text-primary" />
                                        Tour Departure Time
                                    </p>
                                    <div class="flex flex-wrap gap-1.5">
                                        <span
                                            v-for="time in tourDate.times"
                                            :key="time.id"
                                            class="inline-flex items-center rounded-lg border bg-muted/60 px-2.5 py-1 text-xs font-medium text-foreground"
                                        >
                                            {{ formatTime(time.time) }}
                                            <span v-if="time.description" class="ml-1 font-normal text-muted-foreground">({{ time.description }})</span>
                                        </span>
                                    </div>
                                </div>

                                <!-- Pickup Location Info -->
                                <div v-if="tourDate.pickup_schedules.length" class="space-y-2">
                                    <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                        <Bus class="h-3.5 w-3.5 text-primary" />
                                        Pickup Locations
                                    </p>
                                    <div class="flex flex-wrap gap-1.5">
                                        <button
                                            v-for="pickup in tourDate.pickup_schedules"
                                            :key="pickup.id"
                                            type="button"
                                            class="inline-flex items-center rounded-lg border px-2.5 py-1 text-xs font-medium text-foreground transition"
                                            :class="pickupFor(tourDate)?.id === pickup.id ? 'border-primary bg-primary/10' : 'bg-muted/60'"
                                            @click="selectPickup(tourDate.id, pickup.id)"
                                        >
                                            {{ pickup.pickup_location?.name ?? 'Pickup' }} — {{ formatTime(pickup.pickup_time) }}
                                        </button>
                                    </div>
                                    <PickupMap
                                        v-if="pickupFor(tourDate)?.pickup_location"
                                        class="mt-3"
                                        :latitude="pickupFor(tourDate)?.pickup_location?.latitude"
                                        :longitude="pickupFor(tourDate)?.pickup_location?.longitude"
                                        :label="pickupFor(tourDate)?.pickup_location?.name"
                                    />
                                </div>
                            </div>
                        </div>
                        </div>
                    </div>

                    <!-- Empty Date State -->
                    <div v-else class="mt-6 rounded-2xl border border-dashed p-8 text-center">
                        <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-xl bg-muted text-muted-foreground">
                            <CalendarDays class="h-5 w-5" />
                        </div>
                        <p class="mt-3 text-sm font-medium">No dates scheduled</p>
                        <p class="text-xs text-muted-foreground">Check back later or explore other tour packages.</p>
                    </div>
                </section>
            </div>
        </div>

        <!-- Mobile book bar above bottom tabs -->
        <div
            class="fixed inset-x-0 z-30 border-t border-border bg-background/95 px-4 py-3 backdrop-blur-md md:hidden "
            style="bottom: calc(4.75rem + env(safe-area-inset-bottom))"
        >
            <div class="mb-2 flex items-center justify-between gap-3 text-xs">
                <div class="min-w-0">
                    <p class="text-muted-foreground">
                        {{ selectedTourDate ? formatDate(selectedTourDate.tour_date) : 'Select a date' }}
                    </p>
                    <p class="truncate font-bold text-foreground">
                        ₱{{ formatPrice(package.price) }}
                        <span class="font-normal text-muted-foreground">/ guest</span>
                    </p>
                </div>
            </div>
            <Button
                v-if="canBookSelected"
                as-child
                class="w-full rounded-2xl"
            >
                <Link
                    :href="selectedBookingHref"
                    class="inline-flex min-h-11 w-full items-center justify-center gap-2"
                >
                    <span>{{ stickyCtaLabel }}</span>
                    <ArrowRight class="h-4 w-4" />
                </Link>
            </Button>
            <Button
                v-else
                disabled
                class="w-full rounded-2xl"
            >
                {{ stickyCtaLabel }}
            </Button>
        </div>
    </div>
</template>