<script setup lang="ts">
/*
|--------------------------------------------------------------------------
| Imports
|--------------------------------------------------------------------------
*/

import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

import { index, store } from '@/routes/user/bookings';

import {
    AlertCircle,
    ArrowLeft,
    Bus,
    CalendarDays,
    CheckCircle2,
    Clock3,
    CreditCard,
    FileText,
    Globe,
    MapPin,
    Phone,
    Sparkles,
    Ticket,
    UserRound,
    Users,
} from '@lucide/vue';

import { Button } from '@/components/ui/button';
import WeatherAdvisoryCard, { type WeatherForecastData } from '@/components/weather/WeatherAdvisoryCard.vue';
import PickupMap from '@/components/maps/PickupMap.vue';
import TourRoute from '@/components/tours/TourRoute.vue';

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

type TourTime = {
    id: number;
    tour_date_id: number;
    time: string;
    description: string | null;
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
    tour_date_id: number;
    pickup_location_id: number;
    pickup_time: string;
    pickup_location: PickupLocation | null;
};

type TourDate = {
    id: number;
    tour_date: string;
    capacity: number;
    booked_guests: number;
    remaining_capacity: number;
    package: Package;
    times: TourTime[];
    pickup_schedules: PickupSchedule[];
    weather_forecast?: WeatherForecastData | null;
    booking_closed?: boolean;
    booking_closed_reason?: string | null;
};

/*
|--------------------------------------------------------------------------
| Props
|--------------------------------------------------------------------------
*/

const props = defineProps<{
    tourDate: TourDate;
}>();

/*
|--------------------------------------------------------------------------
| Form
|--------------------------------------------------------------------------
*/

const form = useForm({
    tour_date_id: props.tourDate.id,
    pickup_schedule_id: null as number | null,
    pickup_latitude: null as number | null,
    pickup_longitude: null as number | null,
    number_of_guests: 1,
    phone_number: '',
    nationality: '',
    special_request: '',
});

const selectedPickup = computed(() => {
    return props.tourDate.pickup_schedules.find(
        (pickup) => pickup.id === form.pickup_schedule_id,
    )?.pickup_location ?? null;
});

watch(selectedPickup, (location) => {
    const latitude = Number(location?.latitude);
    const longitude = Number(location?.longitude);

    if (Number.isFinite(latitude) && Number.isFinite(longitude)) {
        form.pickup_latitude = latitude;
        form.pickup_longitude = longitude;
    }
});

function onPick(point: { latitude: number; longitude: number }): void {
    form.pickup_latitude = point.latitude;
    form.pickup_longitude = point.longitude;
}

/*
|--------------------------------------------------------------------------
| Formatting Helpers
|--------------------------------------------------------------------------
*/

function formatDate(date: string): string {
    if (!date) return '';

    const parsed = new Date(`${date}T00:00:00`);

    if (Number.isNaN(parsed.getTime())) return date;

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

function formatPrice(price: number | string): string {
    return Number(price).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

/*
|--------------------------------------------------------------------------
| Submit
|--------------------------------------------------------------------------
*/

function submit() {
    form.post(store().url);
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
                title: 'Bookings',
            },
            {
                href: '#',
                title: 'New Booking',
            },
        ],
    },
});
</script>

<template>
    <Head title="Book Tour" />

    <div class="relative min-h-screen overflow-x-clip bg-background pb-32 text-foreground md:pb-20">
        <!-- Ambient Background Glows -->
        <div class="pointer-events-none absolute inset-0 -z-10 overflow-hidden">
            <div class="absolute left-1/2 top-[-10rem] h-[28rem] w-[42rem] -translate-x-1/2 rounded-full bg-primary/10 blur-3xl" />
            <div class="absolute right-[-10rem] top-64 h-72 w-72 rounded-full bg-primary/5 blur-3xl" />
        </div>

        <div class="mx-auto max-w-7xl px-4 py-4 md:px-5 md:py-8 lg:px-8">
            <!-- Top Navigation & Breadcrumb Tag -->
            <div class="mb-4 flex items-center justify-between gap-2 md:mb-6">
                <Link
                    :href="index()"
                    class="inline-flex items-center gap-2 text-sm font-medium text-muted-foreground transition hover:text-foreground"
                >
                    <ArrowLeft class="h-4 w-4" />
                    <span class="md:hidden">Back</span>
                    <span class="hidden md:inline">Back to Bookings</span>
                </Link>

                <div class="inline-flex items-center gap-2 rounded-full border bg-background/80 px-3 py-1 text-[10px] font-medium backdrop-blur shadow-xs md:text-xs">
                    <Sparkles class="h-3.5 w-3.5 text-primary" />
                    <span>Step 02 — Details</span>
                </div>
            </div>

            <!-- Page Title Area -->
            <div class="mb-4 md:mb-8">
                <h1 class="text-xl font-bold tracking-tight md:text-3xl sm:text-4xl">Book Your Tour</h1>
                <p class="mt-1 text-xs text-muted-foreground md:text-sm">
                    Pickup, contact, and guest count — then pay with GCash.
                </p>
            </div>

            <div class="grid grid-cols-1 gap-8 lg:grid-cols-12 lg:items-start">
                <!-- ======================================================
                     MAIN FORM SECTIONS
                ======================================================= -->
                <div class="space-y-6 lg:col-span-8">
                    <!-- Tour Information Header Card -->
                    <section class="rounded-3xl border bg-card/60 p-4 backdrop-blur shadow-sm sm:p-7">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                    Selected Package
                                </span>
                                <h2 class="mt-1 text-xl font-bold tracking-tight text-foreground sm:text-2xl">
                                    {{ tourDate.package.package_name }}
                                </h2>
                                <p class="mt-1 flex items-center gap-1.5 text-xs text-muted-foreground">
                                    <MapPin class="h-3.5 w-3.5 text-primary" />
                                    <span>{{ tourDate.package.destination }}</span>
                                </p>
                            </div>

                            <div class="rounded-2xl border bg-muted/40 px-4 py-2.5 backdrop-blur">
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                                    Price per guest
                                </p>
                                <p class="mt-0.5 text-lg font-extrabold tracking-tight text-foreground">
                                    ₱{{ formatPrice(tourDate.package.price) }}
                                </p>
                            </div>
                        </div>

                        <!-- Date & Capacity Pills -->
                        <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="rounded-2xl border bg-background/60 p-4 backdrop-blur">
                                <p class="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                                    <CalendarDays class="h-3.5 w-3.5 text-primary" />
                                    <span>Tour Date</span>
                                </p>
                                <p class="mt-1 text-sm font-bold text-foreground">
                                    {{ formatDate(tourDate.tour_date) }}
                                </p>
                            </div>

                            <div class="rounded-2xl border bg-background/60 p-4 backdrop-blur">
                                <p class="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                                    <Users class="h-3.5 w-3.5 text-primary" />
                                    <span>Available Seats</span>
                                </p>
                                <p class="mt-1 text-sm font-bold text-foreground">
                                    {{ tourDate.remaining_capacity }} open slots
                                </p>
                            </div>
                        </div>
                    </section>

                    <!-- Weather Advisory for this date -->
                    <WeatherAdvisoryCard
                        :forecast="tourDate.weather_forecast"
                        :tour-date="tourDate.tour_date"
                        :destination="tourDate.package.destination"
                        :package-name="tourDate.package.package_name"
                    />

                    <!-- Tour Departure Times -->
                    <section class="rounded-3xl border bg-card/60 p-4 backdrop-blur shadow-sm sm:p-7">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                <Clock3 class="h-5 w-5" />
                            </div>
                            <div>
                                <h2 class="text-base font-bold text-foreground">Tour Departure Times</h2>
                                <p class="text-xs text-muted-foreground">Designated activity and departure schedules for this date</p>
                            </div>
                        </div>

                        <div v-if="tourDate.times.length" class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <div
                                v-for="time in tourDate.times"
                                :key="time.id"
                                class="rounded-2xl border bg-background/60 p-4 backdrop-blur"
                            >
                                <p class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
                                    Tour Departure Time
                                </p>
                                <p class="mt-1 text-sm font-bold text-foreground">
                                    {{ formatTime(time.time) }}
                                </p>
                                <p v-if="time.description" class="mt-0.5 text-xs text-muted-foreground">
                                    {{ time.description }}
                                </p>
                            </div>
                        </div>

                        <div
                            v-else
                            class="mt-5 rounded-2xl border border-dashed bg-muted/20 p-6 text-center text-xs text-muted-foreground"
                        >
                            No specific departure batches listed for this date.
                        </div>

                        <p v-if="form.errors.tour_date_id" class="mt-2 text-xs text-destructive">
                            {{ form.errors.tour_date_id }}
                        </p>
                    </section>

                    <!-- Pickup Schedule Selector -->
                    <section class="rounded-3xl border bg-card/60 p-4 backdrop-blur shadow-sm sm:p-7">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                <Bus class="h-5 w-5" />
                            </div>
                            <div>
                                <h2 class="text-base font-bold text-foreground">Pickup Location</h2>
                                <p class="text-xs text-muted-foreground">Choose a pickup time, then pin the exact spot where you want to be picked up</p>
                            </div>
                        </div>

                        <div v-if="tourDate.pickup_schedules.length" class="mt-5 grid gap-3 sm:grid-cols-2">
                            <label
                                v-for="pickup in tourDate.pickup_schedules"
                                :key="pickup.id"
                                class="relative flex cursor-pointer items-start gap-3.5 rounded-2xl border p-4.5 backdrop-blur transition-all duration-200"
                                :class="
                                    form.pickup_schedule_id === pickup.id
                                        ? 'border-primary bg-primary/10 ring-2 ring-primary/20'
                                        : 'border-border/80 bg-background/60 hover:border-primary/40 hover:bg-muted/30'
                                "
                            >
                                <input
                                    v-model="form.pickup_schedule_id"
                                    type="radio"
                                    :value="pickup.id"
                                    class="mt-0.5 h-4 w-4 text-primary focus:ring-primary"
                                />

                                <div class="min-w-0">
                                    <p class="text-sm font-bold text-foreground">
                                        {{ pickup.pickup_location?.name ?? 'Pickup Location' }}
                                    </p>
                                    <p class="mt-1 flex items-center gap-1.5 text-xs text-muted-foreground">
                                        <Clock3 class="h-3.5 w-3.5 text-primary" />
                                        <span>{{ formatTime(pickup.pickup_time) }}</span>
                                    </p>
                                </div>
                            </label>
                        </div>

                        <div
                            v-else
                            class="mt-5 rounded-2xl border border-dashed bg-muted/20 p-6 text-center text-xs text-muted-foreground"
                        >
                            No pickup stations available for this date.
                        </div>

                        <p v-if="form.errors.pickup_schedule_id" class="mt-2 text-xs text-destructive">
                            {{ form.errors.pickup_schedule_id }}
                        </p>

                        <div class="mt-5">
                            <PickupMap
                                interactive
                                :latitude="form.pickup_latitude ?? selectedPickup?.latitude"
                                :longitude="form.pickup_longitude ?? selectedPickup?.longitude"
                                :label="selectedPickup?.name ?? 'Your pickup spot'"
                                hint="Zoom in, then drag the pin or tap the map to point where you want to be picked up."
                                @pick="onPick"
                            />
                            <p v-if="form.errors.pickup_latitude || form.errors.pickup_longitude" class="mt-2 text-xs text-destructive">
                                {{ form.errors.pickup_latitude || form.errors.pickup_longitude }}
                            </p>
                        </div>
                    </section>

                    <!-- Lead Guest Information -->
                    <section class="rounded-3xl border bg-card/60 p-4 backdrop-blur shadow-sm sm:p-7">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                <UserRound class="h-5 w-5" />
                            </div>
                            <div>
                                <h2 class="text-base font-bold text-foreground">Guest & Contact Details</h2>
                                <p class="text-xs text-muted-foreground">Information required for booking verification and manifest</p>
                            </div>
                        </div>

                        <div class="mt-6 space-y-5">
                            <!-- Number of Guests -->
                            <div>
                                <div class="flex items-center justify-between">
                                    <label for="number_of_guests" class="mb-2 block text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                        Number of Guests
                                    </label>
                                    <span class="text-xs text-muted-foreground">
                                        Max available: <strong>{{ tourDate.remaining_capacity }}</strong>
                                    </span>
                                </div>

                                <input
                                    id="number_of_guests"
                                    v-model.number="form.number_of_guests"
                                    type="number"
                                    min="1"
                                    :max="tourDate.remaining_capacity"
                                    class="w-full rounded-xl border bg-background/80 px-4 py-3 text-sm text-foreground transition focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                                    :class="{ 'border-destructive focus:ring-destructive/20': form.errors.number_of_guests }"
                                />

                                <p v-if="form.errors.number_of_guests" class="mt-1.5 text-xs text-destructive">
                                    {{ form.errors.number_of_guests }}
                                </p>
                            </div>

                            <!-- Phone & Nationality -->
                            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                                <div>
                                    <label for="phone_number" class="mb-2 block text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                        Phone Number
                                    </label>
                                    <input
                                        id="phone_number"
                                        v-model="form.phone_number"
                                        type="tel"
                                        placeholder="e.g. 0912 345 6789"
                                        class="w-full rounded-xl border bg-background/80 px-4 py-3 text-sm text-foreground transition focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                                        :class="{ 'border-destructive focus:ring-destructive/20': form.errors.phone_number }"
                                    />
                                    <p v-if="form.errors.phone_number" class="mt-1.5 text-xs text-destructive">
                                        {{ form.errors.phone_number }}
                                    </p>
                                </div>

                                <div>
                                    <label for="nationality" class="mb-2 block text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                        Nationality
                                    </label>
                                    <input
                                        id="nationality"
                                        v-model="form.nationality"
                                        type="text"
                                        placeholder="e.g. Filipino"
                                        class="w-full rounded-xl border bg-background/80 px-4 py-3 text-sm text-foreground transition focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                                        :class="{ 'border-destructive focus:ring-destructive/20': form.errors.nationality }"
                                    />
                                    <p v-if="form.errors.nationality" class="mt-1.5 text-xs text-destructive">
                                        {{ form.errors.nationality }}
                                    </p>
                                </div>
                            </div>

                            <!-- Special Request -->
                            <div>
                                <label for="special_request" class="mb-2 block text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                    Special Request / Dietary Notes <span class="text-muted-foreground/60">(Optional)</span>
                                </label>
                                <textarea
                                    id="special_request"
                                    v-model="form.special_request"
                                    rows="4"
                                    placeholder="Enter any medical considerations, special meal requests, or seating notes..."
                                    class="w-full resize-none rounded-xl border bg-background/80 px-4 py-3 text-sm text-foreground transition focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                                    :class="{ 'border-destructive focus:ring-destructive/20': form.errors.special_request }"
                                />
                                <p v-if="form.errors.special_request" class="mt-1.5 text-xs text-destructive">
                                    {{ form.errors.special_request }}
                                </p>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- ======================================================
                     STICKY BOOKING SUMMARY SIDEBAR
                ======================================================= -->
                <aside class="lg:col-span-4">
                    <div class="sticky top-6 space-y-4">
                        <div class="rounded-3xl border bg-card/80 p-6 backdrop-blur-md shadow-sm sm:p-7">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center gap-1.5 rounded-full border bg-primary/10 px-2.5 py-0.5 text-xs font-semibold text-primary">
                                    <Ticket class="h-3.5 w-3.5" />
                                    <span>Summary</span>
                                </span>
                            </div>

                            <h2 class="mt-3 text-lg font-bold tracking-tight text-foreground">
                                Order Overview
                            </h2>

                            <div class="mt-5 space-y-3.5 text-xs">
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Package</span>
                                    <span class="font-bold text-foreground text-right max-w-[180px] truncate">
                                        {{ tourDate.package.package_name }}
                                    </span>
                                </div>

                                <div class="flex justify-between border-t border-border/40 pt-3">
                                    <span class="text-muted-foreground">Travel Date</span>
                                    <span class="font-bold text-foreground">
                                        {{ formatDate(tourDate.tour_date) }}
                                    </span>
                                </div>

                                <div class="flex justify-between border-t border-border/40 pt-3">
                                    <span class="text-muted-foreground">Guests</span>
                                    <span class="font-bold text-foreground">
                                        {{ form.number_of_guests }} {{ form.number_of_guests === 1 ? 'Guest' : 'Guests' }}
                                    </span>
                                </div>

                                <div class="flex justify-between border-t border-border/40 pt-3">
                                    <span class="text-muted-foreground">Rate per guest</span>
                                    <span class="font-bold text-foreground">
                                        ₱{{ formatPrice(tourDate.package.price) }}
                                    </span>
                                </div>

                                <div class="rounded-2xl border bg-muted/40 p-4.5 mt-4">
                                    <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                                        Estimated Total
                                    </p>
                                    <p class="mt-1 text-2xl font-extrabold tracking-tight text-foreground sm:text-3xl">
                                        ₱{{
                                            formatPrice(
                                                Number(tourDate.package.price) *
                                                (Number(form.number_of_guests) || 1)
                                            )
                                        }}
                                    </p>
                                    <p class="mt-1 text-[11px] text-muted-foreground">
                                        Inclusive of all taxes and scheduled amenities
                                    </p>
                                </div>
                            </div>

                            <!-- Desktop CTA Buttons -->
                            <div class="mt-6 hidden space-y-2.5 md:block">
                                <p
                                    v-if="tourDate.booking_closed"
                                    class="rounded-xl border border-destructive/30 bg-destructive/10 px-3 py-2 text-xs text-destructive"
                                >
                                    {{ tourDate.booking_closed_reason }}
                                </p>

                                <Button
                                    type="button"
                                    :disabled="form.processing || tourDate.remaining_capacity < 1 || tourDate.booking_closed"
                                    class="w-full rounded-xl py-6 text-sm font-semibold shadow-xs"
                                    @click="submit"
                                >
                                    <span>{{ form.processing ? 'Submitting Reservation...' : 'Confirm & Proceed' }}</span>
                                </Button>

                                <Button
                                    type="button"
                                    variant="outline"
                                    as-child
                                    class="w-full rounded-xl border-border/80 bg-background/80 backdrop-blur hover:bg-muted"
                                >
                                    <Link :href="index()">
                                        Cancel
                                    </Link>
                                </Button>
                            </div>

                            <p
                                v-if="tourDate.booking_closed"
                                class="mt-4 rounded-xl border border-destructive/30 bg-destructive/10 px-3 py-2 text-xs text-destructive md:hidden"
                            >
                                {{ tourDate.booking_closed_reason }}
                            </p>
                        </div>

                        <!-- Policy Info Callout -->
                        <div class="flex items-start gap-3 rounded-2xl border border-border/80 bg-muted/20 p-4 text-xs text-muted-foreground backdrop-blur">
                            <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                            <span>
                                Online reservations are confirmed only after the full booking total is paid by GCash. A voluntary cancellation after payment may be non-refundable. Operator no-shows and unsafe weather can be reported for admin review.
                            </span>
                        </div>
                    </div>
                </aside>
            </div>
        </div>

        <!-- Mobile confirm bar above bottom tabs -->
        <div
            class="fixed inset-x-0 z-30 border-t border-border bg-background/95 px-4 py-3 backdrop-blur-md md:hidden "
            style="bottom: calc(4.75rem + env(safe-area-inset-bottom))"
        >
            <div class="mb-2 flex items-center justify-between text-xs">
                <span class="text-muted-foreground">Estimated total</span>
                <span class="font-bold text-foreground">
                    ₱{{
                        formatPrice(
                            Number(tourDate.package.price) * (Number(form.number_of_guests) || 1),
                        )
                    }}
                </span>
            </div>
            <Button
                type="button"
                class="w-full rounded-2xl"
                :disabled="form.processing || tourDate.remaining_capacity < 1 || tourDate.booking_closed"
                @click="submit"
            >
                {{ form.processing ? 'Saving...' : 'Confirm & proceed to pay' }}
            </Button>
        </div>
    </div>
</template>