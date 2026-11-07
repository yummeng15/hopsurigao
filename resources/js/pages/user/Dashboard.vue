<script setup lang="ts">
/*
|--------------------------------------------------------------------------
| Imports
|--------------------------------------------------------------------------
*/

import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import {
    AlertCircle,
    ArrowRight,
    BadgeCheck,
    CalendarDays,
    CheckCircle2,
    Clock3,
    CloudSun,
    Compass,
    MapPin,
    Package,
    Sparkles,
    Ticket,
    Users,
    XCircle,
} from '@lucide/vue';

import WeatherAdvisoryBadge from '@/components/weather/WeatherAdvisoryBadge.vue';
import { type WeatherForecastData } from '@/components/weather/WeatherAdvisoryCard.vue';

import { dashboard } from '@/routes/user';
import { index as packagesIndex } from '@/routes/user/packages';
import { index as bookingsIndex, show as bookingShow } from '@/routes/user/bookings';

/*
|--------------------------------------------------------------------------
| Types
|--------------------------------------------------------------------------
*/

type Package = {
    id: number;
    package_name: string;
    destination: string;
    price: number | string;
};

type PickupLocation = {
    id: number;
    name: string;
    address?: string;
};

type PickupSchedule = {
    id: number;
    pickup_time: string;
    pickup_location: PickupLocation | null;
};

type TourDate = {
    id: number;
    tour_date: string;
    capacity: number;
    package: Package;
    weather_forecast?: WeatherForecastData | null;
};

type Booking = {
    id: number;
    number_of_guests: number;
    booking_status: string;
    tour_date: TourDate;
    pickup_schedule: PickupSchedule | null;
};

type Stats = {
    total: number;
    pending: number;
    confirmed: number;
    cancelled: number;
    completed?: number;
};

/*
|--------------------------------------------------------------------------
| Props
|--------------------------------------------------------------------------
*/

const props = defineProps<{
    stats: Stats;
    upcomingBookings: Booking[];
    nextBooking: Booking | null;
}>();

const severeWeatherAlerts = computed(() => {
    return props.upcomingBookings.filter((b) => {
        const level = b.tour_date?.weather_forecast?.advisory_level;
        return level === 'orange' || level === 'red';
    });
});

/*
|--------------------------------------------------------------------------
| Breadcrumbs
|--------------------------------------------------------------------------
*/

defineOptions({
    layout: {
        breadcrumbs: [
            {
                href: dashboard(),
                title: 'Dashboard',
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
    if (!date) return '';

    const dateOnly = date.slice(0, 10);
    const parsed = new Date(`${dateOnly}T00:00:00`);

    if (Number.isNaN(parsed.getTime())) return date;

    return parsed.toLocaleDateString('en-US', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function formatShortDate(date: string): string {
    if (!date) return '';

    const dateOnly = date.slice(0, 10);
    const parsed = new Date(`${dateOnly}T00:00:00`);

    if (Number.isNaN(parsed.getTime())) return date;

    return parsed.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function formatTime(time?: string): string {
    if (!time) return '';

    const [hours, minutes] = time.split(':');
    const parsed = new Date();
    parsed.setHours(Number(hours), Number(minutes), 0, 0);

    return parsed.toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
    });
}

function statusBadgeClass(status: string) {
    switch (status) {
        case 'confirmed':
            return 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20';
        case 'cancelled':
            return 'bg-destructive/10 text-destructive border-destructive/20';
        case 'pending':
            return 'bg-orange-500/10 text-orange-600 dark:text-orange-400 border-orange-500/20';
        default:
            return 'bg-muted text-muted-foreground border-border';
    }
}

function statusLabel(status: string): string {
    return status.charAt(0).toUpperCase() + status.slice(1);
}
</script>

<template>
    <Head title="My Dashboard" />

    <div class="relative min-h-screen overflow-x-clip bg-background pb-8 text-foreground md:pb-20">
        <!-- Ambient Background Glows -->
        <div class="pointer-events-none absolute inset-0 -z-10 overflow-hidden">
            <div class="absolute left-1/2 top-[-10rem] h-[28rem] w-[42rem] -translate-x-1/2 rounded-full bg-primary/10 blur-3xl" />
            <div class="absolute right-[-10rem] top-64 h-72 w-72 rounded-full bg-primary/5 blur-3xl" />
        </div>

        <div class="mx-auto max-w-7xl px-4 py-4 md:px-5 md:py-8 lg:px-8">
            <!-- Desktop header -->
            <div class="mb-6 hidden flex-col gap-5 sm:flex-row sm:items-center sm:justify-between md:mb-0 md:flex">
                <div>
                    <div class="inline-flex items-center gap-2 rounded-full border bg-background/80 px-3 py-1 text-xs font-medium backdrop-blur shadow-xs">
                        <Sparkles class="h-3.5 w-3.5 text-primary" />
                        <span>Traveler Overview</span>
                    </div>

                    <h1 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                        My Dashboard
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Track reservations, view tour itineraries, and plan upcoming getaways.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2.5">
                    <Link
                        :href="packagesIndex()"
                        class="inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-primary-foreground shadow-sm transition hover:opacity-90"
                    >
                        <Compass class="h-4 w-4" />
                        <span>Browse Packages</span>
                    </Link>

                    <Link
                        :href="bookingsIndex()"
                        class="inline-flex items-center gap-2 rounded-xl border bg-card/60 px-4 py-2.5 text-sm font-semibold backdrop-blur transition hover:bg-muted"
                    >
                        <Ticket class="h-4 w-4 text-primary" />
                        <span>All Bookings</span>
                    </Link>
                </div>
            </div>

            <!-- Mobile quick actions -->
            <div class="mb-4 grid grid-cols-2 gap-2 md:hidden">
                <Link
                    :href="packagesIndex()"
                    class="inline-flex items-center justify-center gap-2 rounded-2xl bg-primary px-3 py-3 text-xs font-semibold text-primary-foreground shadow-sm"
                >
                    <Compass class="h-4 w-4" />
                    Browse tours
                </Link>
                <Link
                    :href="bookingsIndex()"
                    class="inline-flex items-center justify-center gap-2 rounded-2xl border bg-card/70 px-3 py-3 text-xs font-semibold backdrop-blur"
                >
                    <Ticket class="h-4 w-4 text-primary" />
                    My bookings
                </Link>
            </div>

            <div class="mt-2 space-y-6 md:mt-8 md:space-y-8">
                <!-- ==========================================================
                     SEVERE WEATHER ADVISORY BANNER (If any booking is orange/red)
                =========================================================== -->
                <div
                    v-if="severeWeatherAlerts.length"
                    class="rounded-3xl border border-amber-500/30 bg-amber-500/10 p-5 text-amber-950 dark:text-amber-200 shadow-sm backdrop-blur"
                >
                    <div class="flex items-start gap-3.5">
                        <AlertCircle class="h-5 w-5 shrink-0 mt-0.5 text-amber-600 dark:text-amber-400" />
                        <div class="space-y-2 text-xs leading-relaxed">
                            <p class="font-bold text-sm">
                                Weather Advisory Alert for Your Scheduled Tour
                            </p>
                            <div v-for="alert in severeWeatherAlerts" :key="alert.id" class="rounded-xl bg-background/60 p-3 border border-amber-500/20">
                                <div class="flex items-center gap-2">
                                    <strong class="text-foreground">{{ alert.tour_date.package.package_name }}</strong>
                                    <span class="text-muted-foreground">• {{ formatDate(alert.tour_date.tour_date) }}</span>
                                    <WeatherAdvisoryBadge
                                        :level="alert.tour_date.weather_forecast?.advisory_level"
                                        :badge-text="alert.tour_date.weather_forecast?.advisory_badge"
                                        size="sm"
                                    />
                                </div>
                                <p class="mt-1 text-foreground/90">
                                    {{ alert.tour_date.weather_forecast?.advisory_message }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ==========================================================
                     FEATURED NEXT TRIP HERO CARD
                =========================================================== -->
                <section v-if="nextBooking" class="ocean-card relative overflow-hidden rounded-3xl border border-white/30 p-6 text-primary-foreground shadow-xl sm:p-8">
                    <div class="pointer-events-none absolute inset-0" aria-hidden="true">
                        <div class="ocean-card__sea absolute inset-0" />
                        <div class="ocean-card__glint absolute -left-10 -top-16 h-52 w-80 rounded-full blur-3xl" />
                        <svg class="ocean-card__wave ocean-card__wave--swell" viewBox="0 0 2880 160" preserveAspectRatio="none">
                            <path d="M0,90 C240,140 480,40 720,80 C960,120 1200,30 1440,90 C1680,140 1920,40 2160,80 C2400,120 2640,30 2880,90 L2880,160 L0,160 Z" />
                        </svg>
                        <svg class="ocean-card__boat" viewBox="0 0 320 150">
                            <ellipse cx="168" cy="142" rx="108" ry="6" fill="rgb(255 255 255 / 30%)" />
                            <path fill="#ffffff" d="M8 96 L36 128 H214 C248 128 278 116 312 92 L296 100 H28 Z" />
                            <path fill="#0c7f90" d="M48 112 H236 L226 120 H62 Z" />
                            <path fill="#ffffff" d="M78 96 V58 H176 V96 Z" />
                            <g fill="#1498ab">
                                <rect x="90" y="68" width="16" height="14" rx="2" />
                                <rect x="114" y="68" width="16" height="14" rx="2" />
                                <rect x="138" y="68" width="16" height="14" rx="2" />
                            </g>
                            <path fill="#ffffff" d="M96 58 V40 H158 V58 Z" />
                            <path fill="#ffffff" d="M88 40 H166 L156 32 H98 Z" />
                            <g fill="#1498ab">
                                <rect x="106" y="44" width="14" height="8" rx="1.5" />
                                <rect x="128" y="44" width="14" height="8" rx="1.5" />
                            </g>
                            <path stroke="#ffffff" stroke-width="3" stroke-linecap="round" d="M126 32 V14" />
                            <path fill="#ffffff" d="M126 14 L142 22 H126 Z" />
                        </svg>
                        <svg class="ocean-card__wave ocean-card__wave--back" viewBox="0 0 2880 120" preserveAspectRatio="none">
                            <path d="M0,64 C240,108 480,22 720,58 C960,94 1200,16 1440,64 C1680,108 1920,22 2160,58 C2400,94 2640,16 2880,64 L2880,120 L0,120 Z" />
                        </svg>
                        <svg class="ocean-card__wave ocean-card__wave--front" viewBox="0 0 2880 120" preserveAspectRatio="none">
                            <path d="M0,78 C220,40 460,108 720,72 C980,36 1220,104 1440,68 C1660,32 1900,108 2160,70 C2420,34 2660,100 2880,64 L2880,120 L0,120 Z" />
                        </svg>
                    </div>

                    <div class="relative z-10 flex flex-col justify-between gap-6 lg:flex-row lg:items-center">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="rounded-full bg-white/15 px-3 py-1 text-xs font-semibold uppercase tracking-wider backdrop-blur">
                                    Next Adventure
                                </span>

                                <span class="rounded-full bg-white/20 px-3 py-1 text-xs font-medium backdrop-blur">
                                    {{ statusLabel(nextBooking.booking_status) }}
                                </span>
                            </div>

                            <h2 class="mt-3 text-2xl font-bold tracking-tight sm:text-3xl lg:text-4xl">
                                {{ nextBooking.tour_date.package.package_name }}
                            </h2>

                            <p class="mt-1.5 flex items-center gap-1.5 text-sm opacity-90 sm:text-base">
                                <MapPin class="h-4 w-4 shrink-0" />
                                <span>{{ nextBooking.tour_date.package.destination }}</span>
                            </p>
                        </div>

                        <!-- CTA Button -->
                        <div class="shrink-0">
                            <Link
                                :href="bookingShow(nextBooking.id)"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-background px-6 py-3.5 text-sm font-semibold text-foreground shadow-sm transition hover:opacity-90 sm:w-auto"
                            >
                                <span>View Trip Details</span>
                                <ArrowRight class="h-4 w-4" />
                            </Link>
                        </div>
                    </div>

                    <!-- Meta Trip Details Cards -->
                    <div
                        class="relative z-10 mt-8 grid grid-cols-1 gap-3.5"
                        :class="nextBooking.tour_date.weather_forecast ? 'sm:grid-cols-2 lg:grid-cols-4' : 'sm:grid-cols-3'"
                    >
                        <div class="rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur">
                            <p class="flex items-center gap-2 text-xs font-medium opacity-80">
                                <CalendarDays class="h-4 w-4" />
                                <span>Departure Date</span>
                            </p>
                            <p class="mt-2 text-base font-bold">
                                {{ formatDate(nextBooking.tour_date.tour_date) }}
                            </p>
                        </div>

                        <div class="rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur">
                            <p class="flex items-center gap-2 text-xs font-medium opacity-80">
                                <Users class="h-4 w-4" />
                                <span>Reserved Seats</span>
                            </p>
                            <p class="mt-2 text-base font-bold">
                                {{ nextBooking.number_of_guests }} {{ nextBooking.number_of_guests === 1 ? 'Guest' : 'Guests' }}
                            </p>
                        </div>

                        <div class="rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur">
                            <p class="flex items-center gap-2 text-xs font-medium opacity-80">
                                <Clock3 class="h-4 w-4" />
                                <span>Pickup Time</span>
                            </p>
                            <p class="mt-2 truncate text-base font-bold">
                                {{ nextBooking.pickup_schedule ? formatTime(nextBooking.pickup_schedule.pickup_time) : 'Standard Departure' }}
                            </p>
                        </div>

                        <!-- Weather Forecast Pill -->
                        <div
                            v-if="nextBooking.tour_date.weather_forecast"
                            class="rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur"
                        >
                            <p class="flex items-center gap-2 text-xs font-medium opacity-80">
                                <CloudSun class="h-4 w-4" />
                                <span>Weather Forecast</span>
                            </p>
                            <p class="mt-2 truncate text-base font-bold flex items-center gap-1.5">
                                <span>{{ nextBooking.tour_date.weather_forecast.weather_condition }}</span>
                                <span v-if="nextBooking.tour_date.weather_forecast.temperature_max !== null" class="text-xs font-normal opacity-90">
                                    ({{ Math.round(Number(nextBooking.tour_date.weather_forecast.temperature_max)) }}°C)
                                </span>
                            </p>
                        </div>
                    </div>
                </section>

                <!-- Empty Next Trip State -->
                <section v-else class="rounded-3xl border bg-card/60 p-8 text-center backdrop-blur shadow-sm sm:p-12">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                        <CalendarDays class="h-7 w-7" />
                    </div>

                    <h2 class="mt-4 text-xl font-bold tracking-tight">No upcoming trips booked</h2>
                    <p class="mx-auto mt-1.5 max-w-md text-sm text-muted-foreground">
                        You don't have any tours scheduled right now. Explore our travel destinations and reserve your slot.
                    </p>

                    <div class="mt-6 flex justify-center">
                        <Link
                            :href="packagesIndex()"
                            class="inline-flex items-center gap-2 rounded-xl bg-primary px-6 py-3 text-sm font-semibold text-primary-foreground shadow-sm transition hover:opacity-90"
                        >
                            <span>Explore Tour Packages</span>
                            <ArrowRight class="h-4 w-4" />
                        </Link>
                    </div>
                </section>

                <!-- ==========================================================
                     STATISTICS CARDS
                =========================================================== -->
                <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <!-- Total -->
                    <div class="rounded-2xl border bg-card/60 p-5 backdrop-blur shadow-xs transition hover:border-primary/40">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Total</span>
                            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                <Ticket class="h-4 w-4" />
                            </div>
                        </div>
                        <p class="mt-3 text-3xl font-extrabold tracking-tight">{{ stats.total }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">All bookings made</p>
                    </div>

                    <!-- Pending -->
                    <div class="rounded-2xl border bg-card/60 p-5 backdrop-blur shadow-xs transition hover:border-orange-500/40">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Pending</span>
                            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-orange-500/10 text-orange-600 dark:text-orange-400">
                                <Clock3 class="h-4 w-4" />
                            </div>
                        </div>
                        <p class="mt-3 text-3xl font-extrabold tracking-tight">{{ stats.pending }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">Awaiting confirmation</p>
                    </div>

                    <!-- Confirmed -->
                    <div class="rounded-2xl border bg-card/60 p-5 backdrop-blur shadow-xs transition hover:border-emerald-500/40">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Confirmed</span>
                            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                                <BadgeCheck class="h-4 w-4" />
                            </div>
                        </div>
                        <p class="mt-3 text-3xl font-extrabold tracking-tight">{{ stats.confirmed }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">Ready for travel</p>
                    </div>

                    <!-- Cancelled -->
                    <div class="rounded-2xl border bg-card/60 p-5 backdrop-blur shadow-xs transition hover:border-destructive/40">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Cancelled</span>
                            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-destructive/10 text-destructive">
                                <XCircle class="h-4 w-4" />
                            </div>
                        </div>
                        <p class="mt-3 text-3xl font-extrabold tracking-tight">{{ stats.cancelled }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">Voided reservations</p>
                    </div>
                </div>

                <!-- ==========================================================
                     UPCOMING BOOKINGS LIST
                =========================================================== -->
                <section class="rounded-3xl border bg-card/60 p-6 backdrop-blur shadow-sm sm:p-8">
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-primary">Reservations</p>
                            <h2 class="text-xl font-bold tracking-tight sm:text-2xl">Upcoming Bookings</h2>
                        </div>

                        <Link
                            :href="bookingsIndex()"
                            class="inline-flex items-center gap-1.5 text-xs font-semibold text-primary transition hover:underline"
                        >
                            <span>View All Bookings</span>
                            <ArrowRight class="h-3.5 w-3.5" />
                        </Link>
                    </div>

                    <!-- Active List -->
                    <div v-if="upcomingBookings.length" class="mt-6 space-y-3.5">
                        <div
                            v-for="booking in upcomingBookings"
                            :key="booking.id"
                            class="flex flex-col gap-4 rounded-2xl border bg-background/80 p-5 backdrop-blur transition hover:border-primary/40 hover:shadow-xs sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="flex min-w-0 items-start gap-4">
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                    <CalendarDays class="h-5 w-5" />
                                </div>

                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="truncate text-base font-bold text-foreground">
                                            {{ booking.tour_date.package.package_name }}
                                        </h3>

                                        <span
                                            class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium"
                                            :class="statusBadgeClass(booking.booking_status)"
                                        >
                                            {{ statusLabel(booking.booking_status) }}
                                        </span>
                                    </div>

                                    <p class="mt-1 flex items-center gap-1.5 text-xs text-muted-foreground">
                                        <MapPin class="h-3.5 w-3.5 text-primary" />
                                        <span>{{ booking.tour_date.package.destination }}</span>
                                    </p>

                                    <div class="mt-2.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                        <span class="font-medium text-foreground">
                                            {{ formatShortDate(booking.tour_date.tour_date) }}
                                        </span>

                                        <span>•</span>

                                        <span>
                                            {{ booking.number_of_guests }} {{ booking.number_of_guests === 1 ? 'guest' : 'guests' }}
                                        </span>

                                        <template v-if="booking.pickup_schedule?.pickup_time">
                                            <span>•</span>
                                            <span>
                                                Pickup: {{ formatTime(booking.pickup_schedule.pickup_time) }}
                                            </span>
                                        </template>

                                        <template v-if="booking.tour_date.weather_forecast">
                                            <span>•</span>
                                            <WeatherAdvisoryBadge
                                                :level="booking.tour_date.weather_forecast.advisory_level"
                                                :badge-text="booking.tour_date.weather_forecast.advisory_badge"
                                                size="sm"
                                            />
                                            <span class="text-[11px] text-muted-foreground">
                                                {{ booking.tour_date.weather_forecast.weather_condition }}
                                            </span>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <div class="shrink-0">
                                <Link
                                    :href="bookingShow(booking.id)"
                                    class="inline-flex w-full items-center justify-center gap-1.5 rounded-xl border bg-card px-4 py-2.5 text-xs font-semibold text-foreground transition hover:bg-muted sm:w-auto"
                                >
                                    <span>View Details</span>
                                    <ArrowRight class="h-3.5 w-3.5" />
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Empty Upcoming List State -->
                    <div v-else class="mt-6 rounded-2xl border border-dashed p-8 text-center">
                        <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-xl bg-muted text-muted-foreground">
                            <Ticket class="h-5 w-5" />
                        </div>
                        <p class="mt-3 text-sm font-semibold">No upcoming bookings</p>
                        <p class="text-xs text-muted-foreground">Any confirmed or pending bookings will appear in this list.</p>
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>

<style scoped>
.ocean-card__sea {
    background:
        radial-gradient(70% 45% at 8% 0%, rgb(255 255 255 / 32%), transparent 58%),
        radial-gradient(55% 70% at 100% 100%, rgb(4 90 104 / 55%), transparent 62%),
        linear-gradient(180deg, #7af0f6 0%, #4fe0e8 34%, #18b7c8 68%, #0c7f90 100%);
}

.ocean-card__glint {
    background: rgb(255 255 255 / 42%);
}

.ocean-card__wave {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 200%;
    fill: rgb(255 255 255 / 28%);
}

.ocean-card__wave--swell {
    bottom: 18%;
    height: 9rem;
    fill: rgb(186 248 252 / 35%);
}

.ocean-card__wave--back {
    height: 8.5rem;
    fill: rgb(10 120 136 / 38%);
}

.ocean-card__wave--front {
    height: 5.75rem;
    fill: rgb(255 255 255 / 34%);
}

.ocean-card__boat {
    position: absolute;
    top: 4.5rem;
    left: 50%;
    z-index: 1;
    width: min(16rem, 48%);
    height: auto;
    transform: translateX(-50%);
    filter: drop-shadow(0 10px 12px rgb(4 70 80 / 18%));
}

@media (prefers-reduced-motion: no-preference) {
    .ocean-card__wave--swell {
        animation: ocean-drift 26s linear infinite;
    }

    .ocean-card__wave--back {
        animation: ocean-drift 18s linear infinite reverse;
    }

    .ocean-card__wave--front {
        animation: ocean-drift 12s linear infinite;
    }

    .ocean-card__boat {
        animation: boat-bob 3.6s ease-in-out infinite alternate;
    }
}

@keyframes ocean-drift {
    from {
        transform: translateX(0);
    }

    to {
        transform: translateX(-50%);
    }
}

@keyframes boat-bob {
    from {
        transform: translateX(-50%) translateY(0);
    }

    to {
        transform: translateX(-50%) translateY(6px);
    }
}
</style>