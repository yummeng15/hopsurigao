
<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    ArrowLeft,
    BadgeCheck,
    CalendarDays,
    CircleAlert,
    QrCode,
    Save,
    Users,
    Wallet,
} from '@lucide/vue';

type Outcome = 'completed' | 'passenger_no_show' | 'operator_no_show';

type TripBooking = {
    id: number;
    guest: string;
    package: string;
    tour_date: string | null;
    booking_status: string;
    guests: number;
    amount_received: number;
    remaining_balance: number;
    price_verified: boolean;
    checked_in_at: string | null;
    outcome: Outcome | null;
    outcome_notes: string | null;
    recorded_at: string | null;
    can_record_today: boolean;
    can_check_in_today: boolean;
};

const props = defineProps<{
    booking: TripBooking;
    scannedToken: string | null;
}>();

const checkIn = useForm({
    qr_token: props.scannedToken ?? '',
});

const tripForm = useForm<{
    outcome: Outcome;
    notes: string;
}>({
    outcome: 'completed',
    notes: '',
});

const canCheckIn = computed(() =>
    props.booking.booking_status === 'confirmed'
    && props.booking.can_check_in_today
    && !props.booking.checked_in_at
    && !props.booking.outcome
    && !!props.scannedToken,
);

const canChooseOutcome = computed(() =>
    props.booking.booking_status === 'confirmed'
    && props.booking.can_record_today
    && !props.booking.outcome,
);

const canSubmit = computed(() => {
    if (!canChooseOutcome.value) return false;

    if (tripForm.outcome === 'completed') {
        return !!props.booking.checked_in_at
            && props.booking.remaining_balance <= 0.009
            && props.booking.price_verified;
    }

    if (
        tripForm.outcome === 'passenger_no_show'
        && props.booking.checked_in_at
    ) {
        return false;
    }

    return tripForm.notes.trim().length >= 10;
});

function currency(value: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(value);
}

function confirmCheckIn(): void {
    if (!canCheckIn.value || checkIn.processing) return;

    checkIn.post(
        `/operator/bookings/${props.booking.id}/trip/check-in`,
        { preserveScroll: true },
    );
}

function recordOutcome(): void {
    if (!canSubmit.value || tripForm.processing) return;

    const descriptions: Record<Outcome, string> = {
        completed:
            'Complete this trip? The booking will be marked completed.',
        passenger_no_show:
            'Report that the passenger did not show? This cannot be undone here and may need Super Admin review.',
        operator_no_show:
            'Report that the boat/operator did not arrive? This cannot be undone here and may need Super Admin review.',
    };

    if (!window.confirm(descriptions[tripForm.outcome])) return;

    tripForm.post(
        `/operator/bookings/${props.booking.id}/trip/outcome`,
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="`Trip Management #${booking.id}`" />

    <main class="mx-auto max-w-4xl space-y-6 px-4 py-6 sm:px-6">
        <Link
            :href="`/operator/bookings/${booking.id}`"
            class="inline-flex items-center gap-2 text-sm font-semibold text-primary"
        >
            <ArrowLeft class="size-4" />
            Back to Booking Details
        </Link>

        <header>
            <p class="text-sm font-semibold text-primary">
                Tour Operator Portal
            </p>
            <h1 class="mt-1 text-2xl font-bold sm:text-3xl">
                Trip Management · Booking #{{ booking.id }}
            </h1>
            <p class="mt-2 text-sm text-slate-500">
                Record attendance and the actual trip outcome.
                This page never edits payments.
            </p>
        </header>

        <div class="grid gap-3 sm:grid-cols-2">
            <section class="space-y-2 rounded-xl border bg-white p-5 dark:bg-slate-900">
                <h2 class="font-semibold">{{ booking.package }}</h2>
                <p class="text-sm">{{ booking.guest }}</p>
                <p class="flex items-center gap-2 text-sm text-slate-500">
                    <CalendarDays class="size-4" />
                    {{ booking.tour_date ?? 'Date unavailable' }}
                </p>
                <p class="flex items-center gap-2 text-sm text-slate-500">
                    <Users class="size-4" />
                    {{ booking.guests }} guest(s)
                </p>
                <p class="text-xs text-slate-500">
                    Booking:
                    <span class="font-semibold capitalize">
                        {{ booking.booking_status }}
                    </span>
                </p>
            </section>

            <section class="space-y-2 rounded-xl border bg-white p-5 dark:bg-slate-900">
                <h2 class="flex items-center gap-2 font-semibold">
                    <Wallet class="size-4" />
                    Payment Summary
                </h2>
                <p class="text-sm">
                    Received:
                    <strong>{{ currency(booking.amount_received) }}</strong>
                </p>
                <p class="text-sm">
                    Remaining:
                    <strong>{{ currency(booking.remaining_balance) }}</strong>
                </p>
                <p
                    v-if="!booking.price_verified"
                    class="text-sm text-amber-700 dark:text-amber-400"
                >
                    Legacy estimated price — original quote must be verified
                    before completing a trip.
                </p>
                <Link
                    :href="`/operator/payments/${booking.id}`"
                    class="inline-block text-sm font-semibold text-primary underline"
                >
                    View Payments
                </Link>
            </section>
        </div>

        <section class="space-y-4 rounded-xl border bg-white p-5 dark:bg-slate-900">
            <h2 class="flex items-center gap-2 text-lg font-bold">
                <QrCode class="size-5" />
                Step 1 — Passenger Check-in
            </h2>

            <div
                v-if="booking.checked_in_at"
                class="flex items-center gap-2 text-sm text-emerald-700 dark:text-emerald-400"
            >
                <BadgeCheck class="size-5" />
                Checked in at {{ booking.checked_in_at }}
            </div>

            <template
                v-else-if="booking.booking_status === 'confirmed' && !booking.outcome"
            >
                <p class="text-sm text-slate-500">
                    Scan the Tourist's real QR ticket on the scheduled tour date.
                    You will return here to confirm check-in.
                </p>

                <div class="flex flex-wrap gap-3">
                    <Link
                        href="/operator/qr-scanner"
                        class="inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-semibold hover:bg-slate-100 dark:hover:bg-slate-800"
                    >
                        <QrCode class="size-4" />
                        Scan QR Ticket
                    </Link>

                    <button
                        v-if="scannedToken"
                        type="button"
                        :disabled="!canCheckIn || checkIn.processing"
                        class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground disabled:opacity-50"
                        @click="confirmCheckIn"
                    >
                        {{
                            checkIn.processing
                                ? 'Saving...'
                                : 'Confirm Check-in'
                        }}
                    </button>
                </div>

                <p
                    v-if="scannedToken && !canCheckIn"
                    class="text-xs text-amber-700 dark:text-amber-400"
                >
                    Check-in is allowed only for confirmed trips scheduled today.
                </p>

                <p
                    v-if="checkIn.errors.qr_token"
                    class="text-sm text-red-600"
                >
                    {{ checkIn.errors.qr_token }}
                </p>
            </template>

            <p v-else class="text-sm text-slate-500">
                Check-in is unavailable for this booking.
            </p>
        </section>

        <section class="space-y-4 rounded-xl border bg-white p-5 dark:bg-slate-900">
            <h2 class="text-lg font-bold">
                Step 2 — Complete Trip or Report No-show
            </h2>

            <div
                v-if="booking.outcome"
                class="rounded-lg border border-primary/30 bg-primary/10 p-4 text-sm"
            >
                <p class="font-semibold capitalize">
                    {{ booking.outcome.replaceAll('_', ' ') }}
                </p>
                <p class="mt-1 text-slate-500">
                    Recorded: {{ booking.recorded_at ?? '—' }}
                </p>
                <p
                    v-if="booking.outcome_notes"
                    class="mt-2 whitespace-pre-line"
                >
                    {{ booking.outcome_notes }}
                </p>
                <p
                    v-if="booking.outcome !== 'completed'"
                    class="mt-2 text-amber-700 dark:text-amber-400"
                >
                    No-show reported. The underlying booking remains confirmed
                    pending review; no refund was issued automatically.
                </p>
            </div>

            <template v-else>
                <p class="text-sm text-slate-500">
                    Choose the actual outcome. Reports are recorded permanently
                    for follow-up and do not adjust payments.
                </p>

                <form class="space-y-4" @submit.prevent="recordOutcome">
                    <label class="block space-y-2 text-sm font-semibold">
                        Outcome

                        <select
                            v-model="tripForm.outcome"
                            :disabled="!canChooseOutcome"
                            class="block w-full rounded-lg border bg-transparent px-3 py-2 font-normal"
                        >
                            <option value="completed">Complete Trip</option>
                            <option value="passenger_no_show">
                                Passenger Did Not Show
                            </option>
                            <option value="operator_no_show">
                                Operator / Boat Did Not Arrive
                            </option>
                        </select>
                    </label>

                    <label
                        v-if="tripForm.outcome !== 'completed'"
                        class="block space-y-2 text-sm font-semibold"
                    >
                        Reason / Details (required)

                        <textarea
                            v-model="tripForm.notes"
                            rows="3"
                            maxlength="1000"
                            :disabled="!canChooseOutcome"
                            placeholder="Describe what happened (at least 10 characters)"
                            class="block w-full rounded-lg border bg-transparent px-3 py-2 font-normal"
                        />
                    </label>

                    <p
                        v-if="tripForm.errors.outcome || tripForm.errors.notes"
                        class="text-sm text-red-600"
                    >
                        {{ tripForm.errors.outcome || tripForm.errors.notes }}
                    </p>

                    <p
                        v-if="tripForm.outcome === 'completed' && !booking.checked_in_at"
                        class="text-sm text-amber-700 dark:text-amber-400"
                    >
                        Check in the passenger first.
                    </p>

                    <p
                        v-if="tripForm.outcome === 'completed' && booking.remaining_balance > 0"
                        class="text-sm text-amber-700 dark:text-amber-400"
                    >
                        This older booking still has a balance. Collect it through Payments before completing the trip. New online bookings are already paid in full.
                    </p>

                    <p
                        v-if="tripForm.outcome === 'passenger_no_show' && booking.checked_in_at"
                        class="text-sm text-amber-700 dark:text-amber-400"
                    >
                        This passenger was already checked in.
                    </p>

                    <button
                        type="submit"
                        :disabled="!canSubmit || tripForm.processing"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground disabled:opacity-50"
                    >
                        <Save class="size-4" />
                        {{
                            tripForm.processing
                                ? 'Saving...'
                                : 'Record Outcome'
                        }}
                    </button>
                </form>
            </template>

            <div class="flex items-start gap-2 rounded-lg border border-amber-500/30 p-3 text-xs text-slate-500">
                <CircleAlert class="size-4 shrink-0 text-amber-600" />
                No-show reports may require Super Admin review.
                This page never automatically refunds, charges, or changes a payment.
            </div>
        </section>
    </main>
</template>
