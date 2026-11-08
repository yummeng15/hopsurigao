
<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

import { checkout } from '@/actions/App/Http/Controllers/User/PayMongoPaymentController';
import {
    index,
    show,
} from '@/routes/user/bookings';

import {
    ArrowLeft,
    MapPin,
    Sparkles,
    Wallet,
} from '@lucide/vue';

import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import MobileStickyActionBar from '@/components/MobileStickyActionBar.vue';

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
};

type PickupLocation = {
    id: number;
    name: string;
};

type PickupSchedule = {
    id: number;
    pickup_time: string;
    pickup_location: PickupLocation | null;
};

type Payment = {
    id: number;
    amount: number | string;
    payment_method: string | null;
    payment_status: string;
    gateway?: string | null;
    transaction_reference: string | null;
    notes?: string | null;
};

type Booking = {
    id: number;
    number_of_guests: number;
    booking_status: string;
    booked_unit_price: number | string | null;
    booked_total: number | string | null;
    price_snapshot_source?: string | null;
    tour_date: TourDate;
    pickup_schedule: PickupSchedule;
    payments: Payment[];
};

const props = defineProps<{
    booking: Booking;
    existingPayment?: Payment | null;
    paymongoEnabled: boolean;
}>();

/*
|--------------------------------------------------------------------------
| SAVED BOOKING PRICE
|--------------------------------------------------------------------------
|
| Always use the price captured during reservation.
| Never calculate an existing booking's price
| from the current catalog package price.
|
*/

const totalCents = computed<number | null>(() => {
    const raw = props.booking.booked_total;

    if (
        raw === null ||
        raw === undefined ||
        raw === ''
    ) {
        return null;
    }

    const amount = Number(raw);

    return Number.isFinite(amount) && amount > 0
        ? Math.round(amount * 100)
        : null;
});

const hasConfirmedSnapshot = computed(() =>
    totalCents.value !== null &&
    props.booking.price_snapshot_source !== 'legacy_estimate',
);

/*
|--------------------------------------------------------------------------
| PENDING PAYMONGO CHECKOUT
|--------------------------------------------------------------------------
|
| Prevent the UI from changing the selected payment
| amount when an existing checkout session is pending.
|
*/

const pendingPayment = computed<Payment | null>(() => {
    const candidates = [
        props.existingPayment,
        ...(props.booking.payments ?? []),
    ];

    return candidates.find(
        (payment): payment is Payment =>
            payment != null &&
            payment.payment_status === 'pending' &&
            payment.gateway === 'paymongo',
    ) ?? null;
});

/*
|--------------------------------------------------------------------------
| PAYMENT CALCULATIONS
|--------------------------------------------------------------------------
|
| Online checkout always charges the saved full total.
|
*/

const pendingIsFull = computed(() => {
    if (
        !pendingPayment.value ||
        totalCents.value === null
    ) {
        return false;
    }

    return Math.round(Number(pendingPayment.value.amount) * 100) === totalCents.value;
});

const legacyPartialCheckout = computed(() =>
    pendingPayment.value !== null && !pendingIsFull.value,
);

const isEditing = computed(() =>
    Boolean(
        props.existingPayment ||
        props.booking.payments?.length
    ),
);

/*
|--------------------------------------------------------------------------
| PAYMENT FORM
|--------------------------------------------------------------------------
*/

const form = useForm({
    payment_method: 'gcash',
    payment_coverage: 'full' as const,
    accepted_policy: false,
});

const canSubmit = computed(() =>
    props.paymongoEnabled &&
    hasConfirmedSnapshot.value &&
    !legacyPartialCheckout.value &&
    form.accepted_policy &&
    !form.processing,
);

/*
|--------------------------------------------------------------------------
| FORMATTING
|--------------------------------------------------------------------------
*/

function formatDate(date: string): string {
    return new Date(date).toLocaleDateString(
        'en-US',
        {
            month: 'long',
            day: 'numeric',
            year: 'numeric',
        },
    );
}

function formatPrice(price: number | string): string {
    return Number(price).toLocaleString(
        'en-PH',
        {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        },
    );
}

/*
|--------------------------------------------------------------------------
| DISPLAY TOTALS
|--------------------------------------------------------------------------
*/

function total(): number {
    return (totalCents.value ?? 0) / 100;
}

function savedUnitPrice(): number {
    const raw = props.booking.booked_unit_price;

    if (
        raw !== null &&
        raw !== undefined &&
        Number.isFinite(Number(raw))
    ) {
        return Number(raw);
    }

    return props.booking.number_of_guests > 0
        ? total() / props.booking.number_of_guests
        : 0;
}

function amountDue(): number {
    return total();
}

/*
|--------------------------------------------------------------------------
| PAYMONGO CHECKOUT
|--------------------------------------------------------------------------
*/

function submit(): void {
    if (!canSubmit.value) {
        return;
    }

    form.post(
        checkout(props.booking.id).url,
        {
            preserveScroll: true,
        },
    );
}

/*
|--------------------------------------------------------------------------
| PAGE BREADCRUMBS
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
                title: 'Payment',
            },
        ],
    },
});
</script>

<template>
    <Head
        :title="
            isEditing
                ? `Payment - Booking #${booking.id}`
                : `Payment - Booking #${booking.id}`
        "
    />

    <div
        class="tourist-page tourist-page--with-sticky"
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
            <!-- Back navigation -->
            <div
                class="mb-4 flex items-center justify-between gap-2 md:mb-6"
            >
                <Link
                    :href="show(booking.id)"
                    class="inline-flex items-center gap-2 text-sm font-medium text-muted-foreground transition hover:text-foreground"
                >
                    <ArrowLeft class="h-4 w-4" />

                    <span class="md:hidden">
                        Back
                    </span>

                    <span class="hidden md:inline">
                        Back to Booking Details
                    </span>
                </Link>

                <div
                    class="inline-flex items-center gap-2 rounded-full border bg-background/80 px-3 py-1 text-[10px] font-medium shadow-xs backdrop-blur md:text-xs"
                >
                    <Sparkles
                        class="h-3.5 w-3.5 text-primary"
                    />

                    <span>Step 03 — Pay</span>
                </div>
            </div>

            <!-- Page heading -->
            <div class="mb-4 md:mb-6">
                <h1
                    class="text-xl font-bold tracking-tight md:text-2xl"
                >
                    Complete Payment
                </h1>

                <p
                    class="mt-1 text-xs text-muted-foreground md:text-sm"
                >
                    Pay the full booking total with GCash via PayMongo.
                </p>
            </div>

            <div
                class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]"
            >
                <!-- Payment form -->
                <form
                    class="overflow-hidden rounded-3xl border bg-background/80 shadow-xs backdrop-blur"
                    @submit.prevent="submit"
                >
                    <div class="space-y-6 p-5 sm:p-6">
                        <div>
                            <h2
                                class="text-base font-bold text-foreground"
                            >
                                Payment Details
                            </h2>

                            <p
                                class="text-xs text-muted-foreground"
                            >
                                Full payment by GCash. There is no downpayment option.
                            </p>
                        </div>

                        <!-- Payment coverage -->
                        <div>
                            <div
                                class="rounded-2xl border border-primary bg-primary/10 p-4"
                            >
                                <p class="text-sm font-bold text-foreground">
                                    Total amount
                                </p>
                                <p class="mt-1 text-2xl font-extrabold text-foreground">
                                    ₱{{ formatPrice(total()) }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Full payment via GCash. The operator does not collect a cash balance for this online reservation.
                                </p>
                            </div>

                            <p
                                v-if="pendingIsFull"
                                class="mt-2 text-xs text-muted-foreground"
                            >
                                A PayMongo checkout for this full amount is already pending.
                            </p>

                            <p
                                v-if="legacyPartialCheckout"
                                class="mt-2 rounded-lg border border-destructive/30 p-3 text-xs text-destructive"
                            >
                                An older checkout for less than the full amount is still open. It cannot be continued or replaced from here. No new charge was created.
                            </p>

                            <!-- Missing or unverified price -->
                            <p
                                v-if="!hasConfirmedSnapshot"
                                class="mt-2 rounded-lg border border-destructive/30 p-3 text-xs text-destructive"
                            >
                                This booking does not have a verified
                                saved price. Please ask the operator
                                to review the booking amount before paying.
                            </p>

                            <p
                                v-if="form.errors.payment_coverage"
                                class="mt-2 text-xs text-destructive"
                            >
                                {{ form.errors.payment_coverage }}
                            </p>
                        </div>

                        <div
                            class="rounded-2xl border border-border/80 bg-muted/20 p-4 text-xs text-muted-foreground"
                        >
                            <p class="font-semibold text-foreground">
                                Cancellation and refund policy
                            </p>

                            <ul class="mt-2 list-disc space-y-1 pl-4">
                                <li>
                                    Online reservations require 100% of the booking total by GCash before the booking is confirmed.
                                </li>
                                <li>
                                    A voluntary cancellation or change of mind after payment is not eligible for an ordinary refund under this policy.
                                </li>
                                <li>
                                    If the operator or boat does not arrive, open My Bookings → View Details to request a refund or reschedule. Your tour operator reviews the request.
                                </li>
                                <li>
                                    Unsafe weather cancellations can also be reported for reschedule or refund review. HopSurigao uses PayMongo Test Mode and never sends an automatic or real-money refund.
                                </li>
                            </ul>

                            <label class="mt-3 flex items-start gap-3 text-foreground">
                                <input
                                    v-model="form.accepted_policy"
                                    type="checkbox"
                                    class="mt-0.5 h-4 w-4"
                                    :disabled="legacyPartialCheckout"
                                />
                                <span>
                                    I have read and accept this cancellation and refund policy.
                                </span>
                            </label>

                            <p
                                v-if="form.errors.accepted_policy"
                                class="mt-2 text-xs text-destructive"
                            >
                                {{ form.errors.accepted_policy }}
                            </p>
                        </div>

                        <!-- GCash only — no other online methods -->
                        <div>
                            <Label
                                class="mb-3 block text-xs font-semibold uppercase tracking-wider text-muted-foreground"
                            >
                                Payment Method
                            </Label>

                            <div
                                class="flex items-center justify-between rounded-2xl border border-primary bg-primary/10 p-4 ring-2 ring-primary/20 backdrop-blur"
                            >
                                <div class="flex items-center gap-3">
                                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary/15 text-primary">
                                        <Wallet class="h-5 w-5" />
                                    </div>
                                    <div>
                                        <p class="text-sm font-bold text-foreground">
                                            GCash
                                        </p>
                                        <p class="text-xs text-muted-foreground">
                                            Only online payment method. No Maya, card, or bank transfer.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <p
                                v-if="form.errors.payment_method"
                                class="mt-2 text-xs text-destructive"
                            >
                                {{ form.errors.payment_method }}
                            </p>
                        </div>

                        <!-- PayMongo information -->
                        <div
                            v-if="paymongoEnabled"
                            class="rounded-2xl border border-primary/30 bg-primary/5 p-4 text-sm"
                        >
                            <p
                                class="font-semibold text-foreground"
                            >
                                Pay with GCash via PayMongo
                            </p>

                            <p class="mt-1 text-muted-foreground">
                                You will pay
                                ₱{{ formatPrice(amountDue()) }}
                                on PayMongo's secure GCash page.

                                HopSurigao confirms payment only
                                after PayMongo verifies the transaction.
                                A screenshot cannot confirm payment.
                            </p>
                        </div>

                        <div
                            v-else
                            class="rounded-2xl border border-destructive/30 bg-destructive/5 p-4 text-sm"
                        >
                            <p
                                class="font-semibold text-foreground"
                            >
                                Online payment is not connected yet
                            </p>

                            <p class="mt-1 text-muted-foreground">
                                GCash requires the PayMongo connection.
                                Please contact the operator for assistance.
                            </p>
                        </div>
                    </div>

                    <!-- Desktop payment actions -->
                    <div
                        class="hidden flex-col-reverse gap-3 border-t bg-muted/20 p-5 sm:flex-row sm:justify-end sm:p-6 md:flex"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="form.processing"
                            as-child
                            class="rounded-xl"
                        >
                            <Link :href="show(booking.id)">
                                Cancel
                            </Link>
                        </Button>

                        <Button
                            type="submit"
                            :disabled="!canSubmit"
                            class="rounded-xl shadow-xs"
                        >
                            <span v-if="form.processing">
                                Opening secure payment...
                            </span>

                            <span v-else>
                                Pay ₱{{ formatPrice(amountDue()) }} securely
                            </span>
                        </Button>
                    </div>
                </form>

                <!-- Booking summary -->
                <aside class="space-y-4">
                    <div
                        class="rounded-3xl border bg-background/80 p-5 shadow-xs backdrop-blur"
                    >
                        <h2
                            class="text-lg font-bold tracking-tight text-foreground"
                        >
                            Booking Summary
                        </h2>

                        <p
                            class="mt-1 text-xs text-muted-foreground"
                        >
                            Based on the price saved when you reserved,
                            not today's package price.
                        </p>

                        <div class="mt-5 space-y-3.5 text-xs">
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">
                                    Booking ID
                                </span>

                                <span class="font-bold text-foreground">
                                    #{{ booking.id }}
                                </span>
                            </div>

                            <div
                                class="flex justify-between border-t border-border/40 pt-3"
                            >
                                <span class="text-muted-foreground">
                                    Package
                                </span>

                                <span
                                    class="max-w-[170px] truncate text-right font-bold text-foreground"
                                >
                                    {{ booking.tour_date.package.package_name }}
                                </span>
                            </div>

                            <div
                                class="flex justify-between border-t border-border/40 pt-3"
                            >
                                <span class="text-muted-foreground">
                                    Destination
                                </span>

                                <span class="font-semibold text-foreground">
                                    {{ booking.tour_date.package.destination }}
                                </span>
                            </div>

                            <div
                                class="flex justify-between border-t border-border/40 pt-3"
                            >
                                <span class="text-muted-foreground">
                                    Tour Date
                                </span>

                                <span class="font-bold text-foreground">
                                    {{ formatDate(booking.tour_date.tour_date) }}
                                </span>
                            </div>

                            <div
                                class="flex justify-between border-t border-border/40 pt-3"
                            >
                                <span class="text-muted-foreground">
                                    Guests
                                </span>

                                <span class="font-bold text-foreground">
                                    {{ booking.number_of_guests }}
                                    {{
                                        booking.number_of_guests === 1
                                            ? 'Guest'
                                            : 'Guests'
                                    }}
                                </span>
                            </div>

                            <div
                                class="flex justify-between border-t border-border/40 pt-3"
                            >
                                <span class="text-muted-foreground">
                                    Agreed price per guest
                                </span>

                                <span class="font-bold text-foreground">
                                    ₱{{ formatPrice(savedUnitPrice()) }}
                                </span>
                            </div>

                            <div
                                class="flex justify-between border-t border-border/40 pt-3"
                            >
                                <span class="text-muted-foreground">
                                    Agreed booking total
                                </span>

                                <span class="font-bold text-foreground">
                                    ₱{{ formatPrice(total()) }}
                                </span>
                            </div>

                            <!-- Selected amount -->
                            <div
                                class="mt-4 rounded-2xl border bg-muted/40 p-4"
                            >
                                <p
                                    class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground"
                                >
                                    Full payment via GCash
                                </p>

                                <p
                                    class="mt-1 text-2xl font-extrabold tracking-tight text-foreground sm:text-3xl"
                                >
                                    ₱{{ formatPrice(amountDue()) }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Pickup -->
                    <div
                        class="rounded-2xl border border-border/80 bg-muted/20 p-4 text-xs text-muted-foreground backdrop-blur"
                    >
                        <div
                            class="flex items-center gap-2 font-semibold text-foreground"
                        >
                            <MapPin class="h-3.5 w-3.5 text-primary" />

                            <span>
                                Pickup Information
                            </span>
                        </div>

                        <p
                            class="mt-1.5 font-medium text-foreground"
                        >
                            {{
                                booking.pickup_schedule?.pickup_location?.name
                                    ?? 'Assigned Pickup Station'
                            }}
                        </p>
                    </div>
                </aside>
            </div>
        </div>

        <!-- Mobile payment button -->
        <MobileStickyActionBar>
            <Button
                type="button"
                class="w-full rounded-2xl"
                :disabled="!canSubmit"
                @click="submit"
            >
                <Wallet class="mr-2 h-4 w-4" />

                <span v-if="form.processing">
                    Opening PayMongo...
                </span>

                <span v-else>
                    Pay ₱{{ formatPrice(amountDue()) }} with GCash
                </span>
            </Button>
        </MobileStickyActionBar>
    </div>
</template>
