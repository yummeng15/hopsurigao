
<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

import {
    index,
    show,
    edit,
    cancel,
    qr,
} from '@/routes/user/bookings';

import { history as historyRoute } from '@/routes/user';

import {
    ArrowRight,
    Bus,
    CalendarDays,
    Clock3,
    CloudSun,
    CreditCard,
    Eye,
    History,
    MapPin,
    Pencil,
    QrCode,
    Sparkles,
    Ticket,
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

type TourTime = {
    id: number;
    time: string;
    description: string | null;
};

type Package = {
    id: number;
    package_name: string;
    destination: string;
    description: string | null;
    price: number | string;
    status: string;
};

type TourDate = {
    id: number;
    package_id: number;
    tour_date: string;
    capacity: number;
    package: Package;
    times: TourTime[];
    weather_forecast?: WeatherForecastData | null;
};

type PickupLocation = {
    id: number;
    name: string;
};

type PickupSchedule = {
    id: number;
    tour_date_id: number;
    pickup_location_id: number;
    pickup_time: string;
    pickup_location: PickupLocation | null;
};

type Payment = {
    id: number;
    booking_id: number;
    amount: number | string;
    payment_method: string | null;
    payment_status: string;
    transaction_reference: string | null;
    notes: string | null;
    paid_at: string | null;
};

type Booking = {
    id: number;
    user_id: number;
    tour_date_id: number;
    pickup_schedule_id: number;
    number_of_guests: number;
    phone_number: string;
    nationality: string;
    special_request: string | null;
    booking_status: string;
    qr_token: string | null;
    created_at: string;
    updated_at: string;

    booked_unit_price: number | string | null;
    booked_total: number | string | null;
    price_snapshot_source: string | null;

    tour_date: TourDate;
    pickup_schedule: PickupSchedule;
    payments: Payment[];
};

defineProps<{
    bookings: Booking[];
}>();

const cancelTarget = ref<Booking | null>(null);
const processing = ref(false);

/*
|--------------------------------------------------------------------------
| DATE AND TIME HELPERS
|--------------------------------------------------------------------------
*/

function formatDate(date: string): string {
    if (!date) return '—';

    const parsed = new Date(
        `${date.slice(0, 10)}T00:00:00`,
    );

    if (Number.isNaN(parsed.getTime())) {
        return date;
    }

    return parsed.toLocaleDateString('en-US', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function formatTime(
    time: string | null | undefined,
): string {
    if (!time) return 'TBD';

    const match = time.match(
        /^(\d{1,2}):(\d{2})/,
    );

    if (!match) return time;

    const hours = Number(match[1]);
    const minutes = Number(match[2]);

    if (
        hours < 0 ||
        hours > 23 ||
        minutes < 0 ||
        minutes > 59
    ) {
        return time;
    }

    const period = hours >= 12 ? 'PM' : 'AM';

    const hour12 = hours % 12 || 12;

    return `${hour12}:${String(minutes).padStart(2, '0')} ${period}`;
}

/*
|--------------------------------------------------------------------------
| SAVED BOOKING PRICE
|--------------------------------------------------------------------------
|
| Never use booking.tour_date.package.price
| when displaying an existing reservation.
|
*/

function savedTotal(
    booking: Booking,
): number | null {
    if (
        booking.booked_total === null ||
        booking.booked_total === undefined
    ) {
        return null;
    }

    const amount = Number(
        booking.booked_total,
    );

    if (
        !Number.isFinite(amount) ||
        amount <= 0
    ) {
        return null;
    }

    return amount;
}

function savedUnitPrice(
    booking: Booking,
): number | null {
    const raw = booking.booked_unit_price;

    if (
        raw !== null &&
        raw !== undefined
    ) {
        const amount = Number(raw);

        if (
            Number.isFinite(amount) &&
            amount > 0
        ) {
            return amount;
        }
    }

    const total = savedTotal(booking);

    if (
        total === null ||
        booking.number_of_guests <= 0
    ) {
        return null;
    }

    return total / booking.number_of_guests;
}

function formatPrice(
    amount: number | null,
): string {
    if (amount === null) {
        return 'Review required';
    }

    return '₱' + amount.toLocaleString(
        'en-PH',
        {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        },
    );
}

function isLegacyPrice(
    booking: Booking,
): boolean {
    return booking.price_snapshot_source ===
        'legacy_estimate';
}

/*
|--------------------------------------------------------------------------
| PAYMENT STATUS
|--------------------------------------------------------------------------
|
| Multiple payment records may exist:
| GCash downpayment + cash balance.
|
| Calculate the overall state from actual
| recorded transactions instead of using
| only the first payment record.
|
*/

function receivedAmount(
    booking: Booking,
): number {
    const cents = (
        booking.payments ?? []
    )
        .filter(
            payment =>
                payment.payment_status === 'paid' ||
                payment.payment_status === 'partial',
        )
        .reduce(
            (sum, payment) =>
                sum +
                Math.round(
                    Number(payment.amount) * 100,
                ),
            0,
        );

    return cents / 100;
}

function paymentStatus(
    booking: Booking,
): string {
    const total = savedTotal(booking);

    const received = receivedAmount(booking);

    if (
        total !== null &&
        received >= total - 0.005
    ) {
        return 'Fully Paid';
    }

    if (received > 0) {
        return 'Partially Paid';
    }

    const pending = (
        booking.payments ?? []
    ).some(
        payment =>
            payment.payment_status === 'pending',
    );

    return pending
        ? 'Awaiting Payment'
        : 'Unpaid';
}

function paymentStatusClass(
    booking: Booking,
): string {
    const status = paymentStatus(booking);

    if (status === 'Fully Paid') {
        return 'text-emerald-600 dark:text-emerald-400';
    }

    if (status === 'Partially Paid') {
        return 'text-sky-700 dark:text-sky-300';
    }

    if (status === 'Awaiting Payment') {
        return 'text-orange-600 dark:text-orange-400';
    }

    return 'text-muted-foreground';
}

/*
|--------------------------------------------------------------------------
| BOOKING STATUS
|--------------------------------------------------------------------------
*/

function statusLabel(
    status: string,
): string {
    return status.charAt(0).toUpperCase()
        + status.slice(1);
}

function statusBadgeClass(
    status: string,
): string {
    switch (status) {
        case 'confirmed':
            return 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400';

        case 'cancelled':
            return 'border-destructive/20 bg-destructive/10 text-destructive';

        case 'completed':
            return 'border-primary/20 bg-primary/10 text-primary';

        case 'pending':
            return 'border-orange-500/20 bg-orange-500/10 text-orange-600 dark:text-orange-400';

        default:
            return 'border-border bg-muted text-muted-foreground';
    }
}

/*
|--------------------------------------------------------------------------
| CANCEL BOOKING
|--------------------------------------------------------------------------
*/

function openCancel(
    booking: Booking,
): void {
    cancelTarget.value = booking;
}

function closeCancel(): void {
    if (!processing.value) {
        cancelTarget.value = null;
    }
}

function performCancel(): void {
    if (
        !cancelTarget.value ||
        processing.value
    ) {
        return;
    }

    const bookingId = cancelTarget.value.id;

    processing.value = true;

    router.patch(
        cancel(bookingId).url,
        {},
        {
            preserveScroll: true,

            onSuccess: () => {
                cancelTarget.value = null;
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
                href: index(),
                title: 'Bookings',
            },
        ],
    },
});
</script>

<template>
    <Head title="My Bookings" />

    <div
        class="relative min-h-screen overflow-x-clip bg-background pb-8 text-foreground md:pb-20"
    >
        <div
            class="pointer-events-none absolute inset-0 -z-10 overflow-hidden"
        >
            <div
                class="absolute left-1/2 top-[-10rem] h-[28rem] w-[42rem] -translate-x-1/2 rounded-full bg-primary/10 blur-3xl"
            />

            <div
                class="absolute right-[-10rem] top-64 h-72 w-72 rounded-full bg-primary/5 blur-3xl"
            />
        </div>

        <div
            class="mx-auto max-w-7xl px-4 py-4 md:px-5 md:py-8 lg:px-8"
        >
            <!-- Page heading -->
            <div
                class="mb-6 flex flex-wrap items-end justify-between gap-4"
            >
                <div>
                    <div
                        class="inline-flex items-center gap-2 rounded-full border bg-background/80 px-3 py-1 text-xs font-medium"
                    >
                        <Sparkles class="h-3.5 w-3.5 text-primary" />

                        <span>Reservation Management</span>
                    </div>

                    <h1
                        class="mt-3 text-2xl font-bold tracking-tight sm:text-3xl"
                    >
                        My Bookings
                    </h1>

                    <p
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        Manage your reservations, payments,
                        schedules, and QR tickets.
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <Link
                        :href="historyRoute()"
                        class="inline-flex items-center gap-2 rounded-xl border bg-background px-4 py-2 text-xs font-semibold transition hover:bg-muted"
                    >
                        <History class="h-4 w-4 text-primary" />

                        <span>History</span>
                    </Link>

                    <div
                        class="inline-flex items-center gap-2 rounded-xl border bg-background px-4 py-2 text-xs font-medium"
                    >
                        <Ticket class="h-4 w-4 text-primary" />

                        <span>
                            {{ bookings.length }} Active
                        </span>
                    </div>
                </div>
            </div>

            <!-- Booking list -->
            <div
                v-if="bookings.length"
                class="grid grid-cols-1 gap-4 md:gap-6 lg:grid-cols-2"
            >
                <div
                    v-for="booking in bookings"
                    :key="booking.id"
                    class="group overflow-hidden rounded-3xl border bg-card/60 shadow-xs transition hover:border-primary/40 hover:shadow-md"
                >
                    <!-- Header -->
                    <div
                        class="border-b bg-muted/20 p-5 sm:p-6"
                    >
                        <div
                            class="flex items-start justify-between gap-3"
                        >
                            <div class="min-w-0">
                                <span
                                    class="text-xs font-semibold uppercase tracking-wider text-muted-foreground"
                                >
                                    Booking #{{ booking.id }}
                                </span>

                                <h2
                                    class="mt-1 text-lg font-bold tracking-tight text-foreground"
                                >
                                    {{ booking.tour_date.package.package_name }}
                                </h2>

                                <p
                                    class="mt-1 flex items-center gap-1.5 text-xs text-muted-foreground"
                                >
                                    <MapPin
                                        class="h-3.5 w-3.5 text-primary"
                                    />

                                    <span>
                                        {{ booking.tour_date.package.destination }}
                                    </span>
                                </p>
                            </div>

                            <span
                                class="shrink-0 rounded-full border px-3 py-1 text-xs font-semibold"
                                :class="
                                    statusBadgeClass(
                                        booking.booking_status,
                                    )
                                "
                            >
                                {{ statusLabel(booking.booking_status) }}
                            </span>
                        </div>
                    </div>

                    <!-- Booking information -->
                    <div
                        class="grid grid-cols-2 gap-3 p-5 sm:p-6"
                    >
                        <div
                            class="rounded-2xl border bg-background/60 p-3.5"
                        >
                            <p
                                class="flex items-center gap-1.5 text-xs text-muted-foreground"
                            >
                                <CalendarDays
                                    class="h-3.5 w-3.5 text-primary"
                                />

                                Tour Date
                            </p>

                            <p
                                class="mt-1 text-sm font-bold"
                            >
                                {{ formatDate(booking.tour_date.tour_date) }}
                            </p>
                        </div>

                        <div
                            class="rounded-2xl border bg-background/60 p-3.5"
                        >
                            <p
                                class="flex items-center gap-1.5 text-xs text-muted-foreground"
                            >
                                <Users
                                    class="h-3.5 w-3.5 text-primary"
                                />

                                Guests
                            </p>

                            <p
                                class="mt-1 text-sm font-bold"
                            >
                                {{ booking.number_of_guests }}
                                {{
                                    booking.number_of_guests === 1
                                        ? 'Guest'
                                        : 'Guests'
                                }}
                            </p>
                        </div>

                        <div
                            class="rounded-2xl border bg-background/60 p-3.5"
                        >
                            <p
                                class="flex items-center gap-1.5 text-xs text-muted-foreground"
                            >
                                <Bus
                                    class="h-3.5 w-3.5 text-primary"
                                />

                                Pickup Point
                            </p>

                            <p
                                class="mt-1 truncate text-sm font-bold"
                            >
                                {{
                                    booking.pickup_schedule?.pickup_location?.name
                                    ?? 'Not specified'
                                }}
                            </p>
                        </div>

                        <div
                            class="rounded-2xl border bg-background/60 p-3.5"
                        >
                            <p
                                class="flex items-center gap-1.5 text-xs text-muted-foreground"
                            >
                                <Clock3
                                    class="h-3.5 w-3.5 text-primary"
                                />

                                Pickup Time
                            </p>

                            <p
                                class="mt-1 text-sm font-bold"
                            >
                                {{
                                    formatTime(
                                        booking.pickup_schedule?.pickup_time,
                                    )
                                }}
                            </p>
                        </div>

                        <!-- Weather -->
                        <div
                            v-if="booking.tour_date.weather_forecast"
                            class="col-span-2 flex items-center justify-between gap-3 rounded-2xl border bg-background/60 p-3.5"
                        >
                            <div
                                class="flex min-w-0 items-center gap-2"
                            >
                                <CloudSun
                                    class="h-5 w-5 shrink-0 text-primary"
                                />

                                <div class="min-w-0">
                                    <p
                                        class="truncate text-xs font-bold"
                                    >
                                        {{
                                            booking.tour_date.weather_forecast.weather_condition
                                        }}
                                    </p>

                                    <p
                                        class="text-[10px] text-muted-foreground"
                                    >
                                        Wind:
                                        {{
                                            Number(
                                                booking.tour_date.weather_forecast.wind_speed
                                                || 0,
                                            ).toFixed(1)
                                        }}
                                        km/h
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

                    <!-- Saved price and payment -->
                    <div
                        class="border-t bg-muted/30 p-5 sm:p-6"
                    >
                        <div
                            class="mb-4 grid grid-cols-2 gap-4"
                        >
                            <div>
                                <p
                                    class="flex items-center gap-1 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground"
                                >
                                    <CreditCard class="h-3 w-3" />

                                    {{
                                        isLegacyPrice(booking)
                                            ? 'Estimated Rate / Guest'
                                            : 'Booked Rate / Guest'
                                    }}
                                </p>

                                <p
                                    class="mt-1 text-base font-extrabold"
                                >
                                    {{
                                        formatPrice(
                                            savedUnitPrice(booking),
                                        )
                                    }}
                                </p>

                                <p
                                    class="mt-1 text-xs text-muted-foreground"
                                >
                                    {{
                                        isLegacyPrice(booking)
                                            ? 'Estimated Total'
                                            : 'Saved Booking Total'
                                    }}:
                                    <strong>
                                        {{
                                            formatPrice(
                                                savedTotal(booking),
                                            )
                                        }}
                                    </strong>
                                </p>
                            </div>

                            <div class="text-right">
                                <p
                                    class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground"
                                >
                                    Payment Status
                                </p>

                                <p
                                    class="mt-1 text-xs font-semibold"
                                    :class="
                                        paymentStatusClass(booking)
                                    "
                                >
                                    {{ paymentStatus(booking) }}
                                </p>

                                <p
                                    class="mt-1 text-xs text-muted-foreground"
                                >
                                    Received:
                                    {{
                                        formatPrice(
                                            receivedAmount(booking),
                                        )
                                    }}
                                </p>
                            </div>
                        </div>

                        <!-- Legacy price warning -->
                        <p
                            v-if="isLegacyPrice(booking)"
                            class="mb-4 rounded-xl border border-amber-500/30 bg-amber-500/5 p-3 text-xs text-amber-700 dark:text-amber-400"
                        >
                            This older booking has an estimated price.
                            Confirm the amount against the original
                            booking record or payment receipt.
                        </p>

                        <p
                            v-else-if="savedTotal(booking) === null"
                            class="mb-4 rounded-xl border border-destructive/30 p-3 text-xs text-destructive"
                        >
                            Booking price is unavailable.
                            Please contact the operator for verification.
                        </p>

                        <!-- Actions -->
                        <div
                            class="flex flex-wrap items-center gap-2"
                        >
                            <Button
                                as-child
                                size="sm"
                                class="flex-1 rounded-xl"
                            >
                                <Link
                                    :href="show(booking.id)"
                                    class="inline-flex items-center gap-1.5"
                                >
                                    <Eye class="h-4 w-4" />

                                    Details
                                </Link>
                            </Button>

                            <Button
                                v-if="
                                    booking.booking_status === 'pending'
                                "
                                variant="outline"
                                size="sm"
                                as-child
                                class="rounded-xl"
                            >
                                <Link
                                    :href="edit(booking.id)"
                                    class="inline-flex items-center gap-1.5"
                                >
                                    <Pencil class="h-4 w-4" />

                                    Edit
                                </Link>
                            </Button>

                            <Button
                                v-if="
                                    booking.qr_token &&
                                    booking.booking_status === 'confirmed'
                                "
                                variant="outline"
                                size="sm"
                                as-child
                                class="rounded-xl"
                            >
                                <Link
                                    :href="qr(booking.id)"
                                    class="inline-flex items-center gap-1.5"
                                >
                                    <QrCode class="h-4 w-4" />

                                    QR
                                </Link>
                            </Button>

                            <Button
                                v-if="
                                    booking.booking_status === 'pending'
                                "
                                variant="outline"
                                size="sm"
                                :disabled="processing"
                                class="rounded-xl border-destructive/30 text-destructive"
                                @click="openCancel(booking)"
                            >
                                <XCircle class="h-4 w-4" />

                                Cancel
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <div
                v-else
                class="rounded-3xl border border-dashed bg-card/40 p-10 text-center"
            >
                <CalendarDays
                    class="mx-auto h-12 w-12 text-primary"
                />

                <h2
                    class="mt-4 text-lg font-bold"
                >
                    No active bookings found
                </h2>

                <p
                    class="mx-auto mt-2 max-w-sm text-sm text-muted-foreground"
                >
                    You don't have any active reservations.
                    Browse available packages or view your
                    previous trips in History.
                </p>

                <div
                    class="mt-6 flex flex-wrap justify-center gap-3"
                >
                    <Link
                        href="/user/packages"
                        class="inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-xs font-semibold text-primary-foreground"
                    >
                        Browse Packages

                        <ArrowRight class="h-4 w-4" />
                    </Link>

                    <Link
                        :href="historyRoute()"
                        class="inline-flex items-center gap-2 rounded-xl border px-5 py-2.5 text-xs font-semibold"
                    >
                        <History class="h-4 w-4" />

                        View History
                    </Link>
                </div>
            </div>
        </div>

        <!-- Cancellation dialog -->
        <Dialog
            :open="!!cancelTarget"
            @update:open="
                value => {
                    if (!value) closeCancel();
                }
            "
        >
            <DialogContent
                class="rounded-3xl sm:max-w-md"
            >
                <DialogHeader>
                    <DialogTitle>
                        Cancel Reservation?
                    </DialogTitle>

                    <DialogDescription>
                        Are you sure you want to cancel
                        Booking #{{ cancelTarget?.id }}?

                        Your reserved slots will be released,
                        and this reservation will move to
                        Booking History.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter class="gap-2">
                    <Button
                        variant="outline"
                        :disabled="processing"
                        @click="closeCancel"
                    >
                        Keep Booking
                    </Button>

                    <Button
                        variant="destructive"
                        :disabled="processing"
                        @click="performCancel"
                    >
                        {{
                            processing
                                ? 'Cancelling...'
                                : 'Confirm Cancellation'
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
