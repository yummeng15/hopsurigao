
<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

import {
    Archive,
    ArrowRight,
    Bus,
    CalendarDays,
    CheckCircle2,
    Clock3,
    CloudSun,
    CreditCard,
    Eye,
    History,
    MapPin,
    MessageSquare,
    QrCode,
    Receipt,
    Sailboat,
    Ticket,
    Trash2,
    Users,
    XCircle,
} from '@lucide/vue';

import { Button } from '@/components/ui/button';

import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

import WeatherAdvisoryBadge from '@/components/weather/WeatherAdvisoryBadge.vue';
import type { WeatherForecastData } from '@/components/weather/WeatherAdvisoryCard.vue';

import {
    show as bookingShow,
    qr as bookingQr,
    index as bookingsIndex,
} from '@/routes/user/bookings';

import { index as packagesIndex } from '@/routes/user/packages';
import { history as historyRoute } from '@/routes/user';

type Package = {
    id: number;
    package_name: string;
    destination: string;
    price: number | string;
};

type TourDate = {
    id: number;
    tour_date: string;
    package: Package;
    weather_forecast?: WeatherForecastData | null;
};

type PickupSchedule = {
    id: number;
    pickup_time: string;
    pickup_location?: {
        name: string;
    } | null;
};

type Payment = {
    id: number;
    amount: number | string;
    payment_status: string;
    payment_method: string | null;
    paid_at: string | null;
};

type Booking = {
    id: number;
    booking_status: string;
    number_of_guests: number;
    qr_token: string | null;
    booked_unit_price: number | string | null;
    booked_total: number | string | null;
    price_snapshot_source: string | null;
    tour_date: TourDate;
    pickup_schedule: PickupSchedule | null;
    payments: Payment[];
};

defineProps<{
    bookings: Booking[];
}>();

const processing = ref(false);
const archiveTarget = ref<Booking | null>(null);
const showClearAllModal = ref(false);

/*
|--------------------------------------------------------------------------
| DATE AND PRICE FORMATTING
|--------------------------------------------------------------------------
*/

function formatDate(value: string): string {
    if (!value) return 'Not available';

    const parsed = new Date(
        `${value.slice(0, 10)}T00:00:00`,
    );

    if (Number.isNaN(parsed.getTime())) {
        return value;
    }

    return parsed.toLocaleDateString('en-US', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function formatTime(value?: string | null): string {
    if (!value) return 'Not specified';

    const match = value.match(/^(\d{1,2}):(\d{2})/);

    if (!match) return value;

    const hour = Number(match[1]);
    const minute = Number(match[2]);

    if (
        hour > 23 ||
        minute > 59
    ) {
        return value;
    }

    const period = hour >= 12 ? 'PM' : 'AM';
    const hour12 = hour % 12 || 12;

    return `${hour12}:${String(minute).padStart(2, '0')} ${period}`;
}

function formatPrice(value: number | null): string {
    if (value === null || !Number.isFinite(value)) {
        return 'Needs review';
    }

    return '₱' + value.toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

/*
|--------------------------------------------------------------------------
| SAVED BOOKING PRICES
|--------------------------------------------------------------------------
|
| Existing reservations must use booked_total,
| not tour_date.package.price.
|
*/

function savedTotal(booking: Booking): number | null {
    const raw = booking.booked_total;

    if (
        raw === null ||
        raw === undefined ||
        raw === ''
    ) {
        return null;
    }

    const value = Number(raw);

    return Number.isFinite(value) && value > 0
        ? value
        : null;
}

function savedUnitPrice(booking: Booking): number | null {
    const raw = booking.booked_unit_price;

    if (raw !== null && raw !== undefined && raw !== '') {
        const value = Number(raw);

        if (Number.isFinite(value) && value > 0) {
            return value;
        }
    }

    const total = savedTotal(booking);

    if (
        total === null ||
        booking.number_of_guests < 1
    ) {
        return null;
    }

    return total / booking.number_of_guests;
}

function needsPriceReview(booking: Booking): boolean {
    return (
        booking.price_snapshot_source === 'legacy_estimate' ||
        savedTotal(booking) === null
    );
}

/*
|--------------------------------------------------------------------------
| PAYMENT INFORMATION
|--------------------------------------------------------------------------
|
| Calculate payments from actual recorded transactions.
|
| A completed trip does NOT automatically mean
| that the customer has fully paid.
|
*/

function receivedAmount(booking: Booking): number {
    const cents = (booking.payments ?? [])
        .filter(payment =>
            ['paid', 'partial'].includes(
                payment.payment_status,
            ),
        )
        .reduce(
            (total, payment) => {
                const amount = Number(payment.amount);

                if (
                    !Number.isFinite(amount) ||
                    amount < 0
                ) {
                    return total;
                }

                return total + Math.round(amount * 100);
            },
            0,
        );

    return cents / 100;
}

function paymentState(booking: Booking): string {
    const total = savedTotal(booking);
    const received = receivedAmount(booking);

    if (needsPriceReview(booking)) {
        return received > 0
            ? 'Payment Needs Review'
            : 'Price Needs Review';
    }

    if (
        total !== null &&
        received >= total - 0.005
    ) {
        return 'Fully Paid';
    }

    if (received > 0) {
        return 'Partially Paid';
    }

    if (
        (booking.payments ?? []).some(
            payment => payment.payment_status === 'pending',
        )
    ) {
        return 'Awaiting Payment';
    }

    return 'Unpaid';
}

function paymentStateClass(booking: Booking): string {
    const state = paymentState(booking);

    if (state === 'Fully Paid') {
        return 'text-emerald-600 dark:text-emerald-400';
    }

    if (state === 'Partially Paid') {
        return 'text-sky-600 dark:text-sky-400';
    }

    if (state.includes('Review')) {
        return 'text-amber-600 dark:text-amber-400';
    }

    return 'text-muted-foreground';
}

/*
|--------------------------------------------------------------------------
| ARCHIVE ACTIONS
|--------------------------------------------------------------------------
*/

function openArchive(booking: Booking): void {
    archiveTarget.value = booking;
}

function closeArchive(): void {
    if (!processing.value) {
        archiveTarget.value = null;
    }
}

function performArchive(): void {
    if (
        archiveTarget.value === null ||
        processing.value
    ) {
        return;
    }

    const bookingId = archiveTarget.value.id;

    processing.value = true;

    router.patch(
        `/user/bookings/${bookingId}/archive`,
        {},
        {
            preserveScroll: true,

            onSuccess: () => {
                archiveTarget.value = null;
            },

            onFinish: () => {
                processing.value = false;
            },
        },
    );
}

function openClearAll(): void {
    showClearAllModal.value = true;
}

function closeClearAll(): void {
    if (!processing.value) {
        showClearAllModal.value = false;
    }
}

function performClearAll(): void {
    if (processing.value) return;

    processing.value = true;

    router.post(
        '/user/history/clear',
        {},
        {
            preserveScroll: true,

            onSuccess: () => {
                showClearAllModal.value = false;
            },

            onFinish: () => {
                processing.value = false;
            },
        },
    );
}

defineOptions({
    layout: {
        breadcrumbs: [
            {
                href: '/user/dashboard',
                title: 'Dashboard',
            },
            {
                href: historyRoute(),
                title: 'History',
            },
        ],
    },
});
</script>

<template>
    <Head title="Booking History" />

    <div class="tourist-page">
        <div class="mx-auto max-w-7xl px-4 py-6 lg:px-8">
            <!-- Page heading -->
            <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div class="mb-2 flex items-center gap-2 text-sm text-primary">
                        <History class="h-4 w-4" />
                        Past Trips & Archives
                    </div>

                    <h1 class="text-2xl font-bold tracking-tight md:text-3xl">
                        Booking History
                    </h1>

                    <p class="mt-1 text-sm text-muted-foreground">
                        Review your completed and cancelled reservations.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" as-child class="rounded-xl">
                        <Link :href="bookingsIndex()">
                            <Ticket class="mr-2 h-4 w-4" />
                            Active Bookings
                        </Link>
                    </Button>

                    <Button
                        v-if="bookings.length > 0"
                        variant="outline"
                        class="rounded-xl border-destructive/30 text-destructive"
                        :disabled="processing"
                        @click="openClearAll"
                    >
                        <Trash2 class="mr-2 h-4 w-4" />
                        Clear History
                    </Button>
                </div>
            </div>

            <!-- Booking cards -->
            <div
                v-if="bookings.length > 0"
                class="grid grid-cols-1 gap-5 lg:grid-cols-2"
            >
                <div
                    v-for="booking in bookings"
                    :key="booking.id"
                    class="overflow-hidden rounded-3xl border bg-card shadow-sm"
                >
                    <!-- Header -->
                    <div class="border-b bg-muted/20 p-5">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="text-xs font-semibold text-muted-foreground">
                                Booking #{{ booking.id }}
                            </span>

                            <span
                                v-if="booking.booking_status === 'completed'"
                                class="inline-flex items-center gap-1 rounded-full border border-emerald-500/20 bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-600"
                            >
                                <CheckCircle2 class="h-3.5 w-3.5" />
                                Completed
                            </span>

                            <span
                                v-else
                                class="inline-flex items-center gap-1 rounded-full border border-destructive/20 bg-destructive/10 px-3 py-1 text-xs font-semibold text-destructive"
                            >
                                <XCircle class="h-3.5 w-3.5" />
                                Cancelled
                            </span>
                        </div>

                        <h2 class="mt-3 text-lg font-bold">
                            {{ booking.tour_date.package.package_name }}
                        </h2>

                        <p class="mt-1 flex items-center gap-1.5 text-xs text-muted-foreground">
                            <MapPin class="h-3.5 w-3.5 text-primary" />
                            {{ booking.tour_date.package.destination }}
                        </p>
                    </div>

                    <!-- Tour information -->
                    <div class="grid grid-cols-2 gap-3 p-5">
                        <div class="rounded-xl border bg-background/60 p-3">
                            <p class="flex items-center gap-1 text-xs text-muted-foreground">
                                <CalendarDays class="h-3.5 w-3.5" />
                                Tour Date
                            </p>

                            <p class="mt-1 text-sm font-bold">
                                {{ formatDate(booking.tour_date.tour_date) }}
                            </p>
                        </div>

                        <div class="rounded-xl border bg-background/60 p-3">
                            <p class="flex items-center gap-1 text-xs text-muted-foreground">
                                <Users class="h-3.5 w-3.5" />
                                Guests
                            </p>

                            <p class="mt-1 text-sm font-bold">
                                {{ booking.number_of_guests }}
                                {{ booking.number_of_guests === 1 ? 'Guest' : 'Guests' }}
                            </p>
                        </div>

                        <div class="rounded-xl border bg-background/60 p-3">
                            <p class="flex items-center gap-1 text-xs text-muted-foreground">
                                <Bus class="h-3.5 w-3.5" />
                                Pickup Point
                            </p>

                            <p class="mt-1 truncate text-sm font-bold">
                                {{
                                    booking.pickup_schedule?.pickup_location?.name
                                    ?? 'Not specified'
                                }}
                            </p>
                        </div>

                        <div class="rounded-xl border bg-background/60 p-3">
                            <p class="flex items-center gap-1 text-xs text-muted-foreground">
                                <Clock3 class="h-3.5 w-3.5" />
                                Pickup Time
                            </p>

                            <p class="mt-1 text-sm font-bold">
                                {{ formatTime(booking.pickup_schedule?.pickup_time) }}
                            </p>
                        </div>

                        <!-- Weather -->
                        <div
                            v-if="booking.tour_date.weather_forecast"
                            class="col-span-2 flex items-center justify-between gap-3 rounded-xl border bg-background/60 p-3"
                        >
                            <div class="flex min-w-0 items-center gap-2">
                                <CloudSun class="h-5 w-5 shrink-0 text-primary" />

                                <div class="min-w-0">
                                    <p class="truncate text-xs font-semibold">
                                        {{
                                            booking.tour_date.weather_forecast.weather_condition
                                        }}
                                    </p>

                                    <p class="text-[11px] text-muted-foreground">
                                        Weather advisory for scheduled tour date
                                    </p>
                                </div>
                            </div>

                            <WeatherAdvisoryBadge
                                :level="
                                    booking.tour_date.weather_forecast.advisory_level
                                "
                                :badge-text="
                                    booking.tour_date.weather_forecast.advisory_badge
                                "
                                size="sm"
                            />
                        </div>
                    </div>

                    <!-- Price and payment information -->
                    <div class="border-t bg-muted/20 p-5">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="flex items-center gap-1 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                                    <CreditCard class="h-3.5 w-3.5" />
                                    {{
                                        booking.price_snapshot_source === 'legacy_estimate'
                                            ? 'Estimated Total'
                                            : 'Saved Booking Total'
                                    }}
                                </p>

                                <p class="mt-1 text-lg font-extrabold">
                                    {{ formatPrice(savedTotal(booking)) }}
                                </p>

                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ formatPrice(savedUnitPrice(booking)) }}
                                    per guest
                                </p>
                            </div>

                            <div class="text-right">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                                    Payment Status
                                </p>

                                <p
                                    class="mt-1 flex items-center justify-end gap-1 text-xs font-bold"
                                    :class="paymentStateClass(booking)"
                                >
                                    <Receipt class="h-3.5 w-3.5" />
                                    {{ paymentState(booking) }}
                                </p>

                                <p class="mt-1 text-xs text-muted-foreground">
                                    Received:
                                    {{ formatPrice(receivedAmount(booking)) }}
                                </p>
                            </div>
                        </div>

                        <!-- Older booking warning -->
                        <p
                            v-if="needsPriceReview(booking)"
                            class="mt-4 rounded-xl border border-amber-500/30 bg-amber-500/5 p-3 text-xs text-amber-700 dark:text-amber-400"
                        >
                            This booking's original agreed price
                            needs verification against the booking
                            record or payment receipt.
                        </p>

                        <!-- History actions -->
                        <div class="mt-5 flex flex-wrap gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                as-child
                                class="flex-1 rounded-xl"
                            >
                                <Link :href="bookingShow(booking.id)">
                                    <Eye class="mr-1.5 h-4 w-4" />
                                    View Details
                                </Link>
                            </Button>

                            <Button
                                variant="outline"
                                size="sm"
                                as-child
                                class="rounded-xl"
                            >
                                <Link
                                    :href="`${bookingShow(booking.id).url}#booking-feedback`"
                                >
                                    <MessageSquare class="mr-1.5 h-4 w-4" />
                                    Feedback
                                </Link>
                            </Button>

                            <Button
                                v-if="
                                    booking.qr_token &&
                                    booking.booking_status === 'completed'
                                "
                                variant="outline"
                                size="sm"
                                as-child
                                class="rounded-xl"
                            >
                                <Link :href="bookingQr(booking.id)">
                                    <QrCode class="mr-1.5 h-4 w-4" />
                                    Digital Pass
                                </Link>
                            </Button>

                            <Button
                                size="sm"
                                as-child
                                class="rounded-xl"
                            >
                                <Link
                                    :href="`/user/packages/${booking.tour_date.package.id}`"
                                >
                                    <Sailboat class="mr-1.5 h-4 w-4" />
                                    Book Again
                                </Link>
                            </Button>

                            <Button
                                variant="outline"
                                size="sm"
                                class="rounded-xl border-destructive/30 text-destructive"
                                :disabled="processing"
                                @click="openArchive(booking)"
                            >
                                <Archive class="mr-1.5 h-4 w-4" />
                                Archive
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty history -->
            <div
                v-else
                class="rounded-3xl border border-dashed bg-card/40 p-12 text-center"
            >
                <History class="mx-auto h-12 w-12 text-primary" />

                <h2 class="mt-4 text-lg font-bold">
                    Your history is clear
                </h2>

                <p class="mx-auto mt-2 max-w-sm text-sm text-muted-foreground">
                    Completed and cancelled reservations will
                    appear here until you archive them.
                </p>

                <div class="mt-6 flex flex-wrap justify-center gap-3">
                    <Button as-child class="rounded-xl">
                        <Link :href="packagesIndex()">
                            Explore Packages
                            <ArrowRight class="ml-2 h-4 w-4" />
                        </Link>
                    </Button>

                    <Button variant="outline" as-child class="rounded-xl">
                        <Link :href="bookingsIndex()">
                            <Ticket class="mr-2 h-4 w-4" />
                            Active Bookings
                        </Link>
                    </Button>
                </div>
            </div>
        </div>

        <!-- Archive confirmation -->
        <Dialog
            :open="!!archiveTarget"
            @update:open="
                value => {
                    if (!value) closeArchive();
                }
            "
        >
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        Archive Booking?
                    </DialogTitle>

                    <DialogDescription>
                        Archive Booking #{{ archiveTarget?.id }}?
                        It will be hidden from your personal
                        history but retained in the system.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter class="gap-2">
                    <Button
                        variant="outline"
                        :disabled="processing"
                        @click="closeArchive"
                    >
                        Keep in History
                    </Button>

                    <Button
                        :disabled="processing"
                        @click="performArchive"
                    >
                        {{
                            processing
                                ? 'Archiving...'
                                : 'Archive Booking'
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Clear history confirmation -->
        <Dialog
            :open="showClearAllModal"
            @update:open="
                value => {
                    if (!value) closeClearAll();
                }
            "
        >
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        Clear Booking History?
                    </DialogTitle>

                    <DialogDescription>
                        All {{ bookings.length }} history records
                        will be archived and hidden from your
                        personal history. Their records will
                        remain in the system.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter class="gap-2">
                    <Button
                        variant="outline"
                        :disabled="processing"
                        @click="closeClearAll"
                    >
                        Cancel
                    </Button>

                    <Button
                        variant="destructive"
                        :disabled="processing"
                        @click="performClearAll"
                    >
                        {{
                            processing
                                ? 'Clearing...'
                                : 'Clear History'
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
