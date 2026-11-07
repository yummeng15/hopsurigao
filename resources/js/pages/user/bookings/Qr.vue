<script setup lang="ts">
/*
|--------------------------------------------------------------------------
| Imports
|--------------------------------------------------------------------------
*/

import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';

import { index } from '@/routes/user/bookings';

import {
    ArrowLeft,
    Bus,
    CalendarDays,
    CheckCircle2,
    Clock3,
    CloudSun,
    Download,
    MapPin,
    QrCode,
    Sparkles,
    Ticket,
    Users,
} from '@lucide/vue';

import { Button } from '@/components/ui/button';
import * as QRCode from 'qrcode';
import WeatherAdvisoryBadge from '@/components/weather/WeatherAdvisoryBadge.vue';
import { type WeatherForecastData } from '@/components/weather/WeatherAdvisoryCard.vue';

/*
|--------------------------------------------------------------------------
| Types
|--------------------------------------------------------------------------
*/

type Booking = {
    id: number;
    qr_token: string | null;
    booking_status: string;
    number_of_guests: number;
    phone_number: string;
    nationality: string;

    tour_date: {
        tour_date: string;
        weather_forecast?: WeatherForecastData | null;
        package: {
            package_name: string;
            destination: string;
        };
    };

    pickup_schedule: {
        pickup_time: string;
        pickup_location: {
            name: string;
        } | null;
    } | null;
};

/*
|--------------------------------------------------------------------------
| Props
|--------------------------------------------------------------------------
*/

const props = defineProps<{
    booking: Booking;
}>();

/*
|--------------------------------------------------------------------------
| State & Canvas QR Generation
|--------------------------------------------------------------------------
*/

const qrCanvas = ref<HTMLCanvasElement | null>(null);
const qrData = ref('');

onMounted(async () => {
    if (!props.booking.qr_token || !qrCanvas.value) {
        return;
    }

    qrData.value = props.booking.qr_token;

    await QRCode.toCanvas(
        qrCanvas.value,
        qrData.value,
        {
            width: 280,
            margin: 2,
            color: {
                dark: '#0f172a',
                light: '#ffffff',
            },
        }
    );
});

function downloadQr() {
    if (!qrCanvas.value) return;

    const link = document.createElement('a');
    link.download = `booking-${props.booking.id}-qr.png`;
    link.href = qrCanvas.value.toDataURL('image/png');
    link.click();
}

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function formatDate(date: string): string {
    if (!date) return '';

    const parsed = new Date(`${date.slice(0, 10)}T00:00:00`);

    if (Number.isNaN(parsed.getTime())) return date;

    return parsed.toLocaleDateString('en-US', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function formatTime(time: string | null | undefined): string {
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
        case 'completed':
            return 'bg-primary/10 text-primary border-primary/20';
        case 'pending':
            return 'bg-orange-500/10 text-orange-600 dark:text-orange-400 border-orange-500/20';
        default:
            return 'bg-muted text-muted-foreground border-border';
    }
}

function statusLabel(status: string): string {
    return status.charAt(0).toUpperCase() + status.slice(1);
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
                title: 'QR Code',
            },
        ],
    },
});
</script>

<template>

    <Head :title="`Booking #${booking.id} QR Code`" />

    <div class="relative min-h-screen overflow-x-clip bg-background pb-20 text-foreground">
        <!-- Ambient Background Glows -->
        <div class="pointer-events-none absolute inset-0 -z-10 overflow-hidden">
            <div
                class="absolute left-1/2 top-[-10rem] h-[28rem] w-[42rem] -translate-x-1/2 rounded-full bg-primary/10 blur-3xl" />
            <div class="absolute right-[-10rem] top-64 h-72 w-72 rounded-full bg-primary/5 blur-3xl" />
        </div>

        <div class="mx-auto max-w-xl px-5 py-8 lg:px-8">
            <!-- Back Navigation & Breadcrumb Tag -->
            <div class="mb-6 flex items-center justify-between">
                <Link :href="index()"
                    class="inline-flex items-center gap-2 text-sm font-medium text-muted-foreground transition hover:text-foreground">
                    <ArrowLeft class="h-4 w-4" />
                    <span>Back to Bookings</span>
                </Link>

                <div
                    class="inline-flex items-center gap-2 rounded-full border bg-background/80 px-3 py-1 text-xs font-medium backdrop-blur shadow-xs">
                    <Sparkles class="h-3.5 w-3.5 text-primary" />
                    <span>Digital Pass</span>
                </div>
            </div>

            <!-- Main Pass Card Container -->
            <div class="relative overflow-hidden rounded-3xl border bg-card/75 p-6 backdrop-blur-md shadow-sm sm:p-8">
                <!-- Header Info -->
                <div class="text-center">
                    <div
                        class="inline-flex items-center gap-1.5 rounded-full border bg-primary/10 px-3 py-1 text-xs font-semibold text-primary">
                        <QrCode class="h-3.5 w-3.5" />
                        <span>Booking #{{ booking.id }}</span>
                    </div>

                    <h1 class="mt-3 text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                        {{ booking.tour_date.package.package_name }}
                    </h1>

                    <p class="mt-1 flex items-center justify-center gap-1.5 text-xs text-muted-foreground sm:text-sm">
                        <MapPin class="h-3.5 w-3.5 text-primary" />
                        <span>{{ booking.tour_date.package.destination }}</span>
                    </p>
                </div>

                <!-- Canvas QR Box -->
                <div class="mt-6 flex flex-col items-center justify-center rounded-2xl border bg-white p-6 shadow-xs">
                    <canvas v-if="booking.qr_token" ref="qrCanvas" class="max-w-full" />

                    <div v-else
                        class="flex h-64 w-64 flex-col items-center justify-center text-center text-xs text-muted-foreground">
                        <QrCode class="h-10 w-10 text-muted-foreground/40" />
                        <p class="mt-2 font-medium">QR Token Not Available</p>
                        <p class="mt-0.5 text-[11px]">This booking has not been assigned a valid QR verification token.
                        </p>
                    </div>
                </div>

                <!-- Reservation Meta Summary -->
                <div class="mt-6 space-y-3 rounded-2xl border bg-background/60 p-4.5 text-xs backdrop-blur">
                    <div class="flex items-center justify-between">
                        <span class="flex items-center gap-1.5 text-muted-foreground">
                            <CalendarDays class="h-3.5 w-3.5 text-primary" />
                            Tour Date
                        </span>
                        <span class="font-bold text-foreground">
                            {{ formatDate(booking.tour_date.tour_date) }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between border-t border-border/40 pt-2.5">
                        <span class="flex items-center gap-1.5 text-muted-foreground">
                            <Users class="h-3.5 w-3.5 text-primary" />
                            Registered Guests
                        </span>
                        <span class="font-bold text-foreground">
                            {{ booking.number_of_guests }} {{ booking.number_of_guests === 1 ? 'Guest' : 'Guests' }}
                        </span>
                    </div>

                    <div v-if="booking.pickup_schedule"
                        class="flex items-center justify-between border-t border-border/40 pt-2.5">
                        <span class="flex items-center gap-1.5 text-muted-foreground">
                            <Bus class="h-3.5 w-3.5 text-primary" />
                            Pickup Location
                        </span>
                        <span class="font-bold text-foreground">
                            {{ booking.pickup_schedule.pickup_location?.name ?? 'Assigned Station' }}
                        </span>
                    </div>

                    <div v-if="booking.pickup_schedule"
                        class="flex items-center justify-between border-t border-border/40 pt-2.5">
                        <span class="flex items-center gap-1.5 text-muted-foreground">
                            <Clock3 class="h-3.5 w-3.5 text-primary" />
                            Pickup Departure
                        </span>
                        <span class="font-bold text-foreground">
                            {{ formatTime(booking.pickup_schedule.pickup_time) || 'TBD' }}
                        </span>
                    </div>

                    <div v-if="booking.tour_date.weather_forecast"
                        class="flex items-center justify-between border-t border-border/40 pt-2.5">
                        <span class="flex items-center gap-1.5 text-muted-foreground">
                            <CloudSun class="h-3.5 w-3.5 text-primary" />
                            Weather Advisory
                        </span>
                        <div class="flex items-center gap-1.5">
                            <span class="text-foreground font-semibold">
                                {{ booking.tour_date.weather_forecast.weather_condition }}
                            </span>
                            <WeatherAdvisoryBadge
                                :level="booking.tour_date.weather_forecast.advisory_level"
                                :badge-text="booking.tour_date.weather_forecast.advisory_badge"
                                size="sm"
                            />
                        </div>
                    </div>

                    <div class="flex items-center justify-between border-t border-border/40 pt-2.5">
                        <span class="flex items-center gap-1.5 text-muted-foreground">
                            <Ticket class="h-3.5 w-3.5 text-primary" />
                            Status
                        </span>
                        <span
                            class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-semibold"
                            :class="statusBadgeClass(booking.booking_status)">
                            {{ statusLabel(booking.booking_status) }}
                        </span>
                    </div>
                </div>

                <!-- Download Action -->
                <div v-if="booking.qr_token" class="mt-6">
                    <Button type="button" class="w-full rounded-xl shadow-xs" @click="downloadQr">
                        <Download class="mr-2 h-4 w-4" />
                        <span>Download Pass</span>
                    </Button>
                </div>

                <!-- Footer Hint -->
                <p class="mt-5 text-center text-xs leading-relaxed text-muted-foreground">
                    Present this verification code upon arrival. The tour coordinator or dispatcher will scan this QR
                    token to confirm your reservation.
                </p>
            </div>
        </div>
    </div>
</template>