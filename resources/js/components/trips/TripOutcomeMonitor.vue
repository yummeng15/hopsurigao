
<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    AlertCircle,
    CheckCircle2,
    ClipboardCheck,
} from '@lucide/vue';

type TripRecord = {
    booking_id: number;
    booking_status: string;
    outcome: string | null;
    notes?: string | null;
    checked_in_at?: string | null;
    recorded_at: string | null;
};

type TripMonitor =
    | {
        mode: 'detail';
        record: TripRecord;
    }
    | {
        mode: 'list';
        records: TripRecord[];
    };

const page = usePage();

const monitor = computed(
    () => (page.props.tripMonitor ?? null) as TripMonitor | null
);

const role = computed(() => {
    const user = page.props.auth?.user as
        | { role?: { name?: string } }
        | undefined;

    return user?.role?.name ?? '';
});

function bookingUrl(id: number): string {
    if (role.value === 'Admin') {
        return `/operator/bookings/${id}`;
    }

    if (role.value === 'Superadmin') {
        return `/admin/bookings/${id}`;
    }

    return `/user/bookings/${id}`;
}

function outcomeLabel(outcome: string | null): string {
    if (outcome === 'completed') {
        return 'Trip Completed';
    }

    if (outcome === 'passenger_no_show') {
        return 'Passenger No-show Reported';
    }

    if (outcome === 'operator_no_show') {
        return 'Boat / Operator No-show Reported';
    }

    return 'Check-in Recorded';
}

function outcomeDetail(outcome: string | null): string {
    if (outcome === 'completed') {
        return 'The Operator recorded this tour as completed.';
    }

    if (outcome === 'passenger_no_show') {
        return 'The Operator reported that the passenger did not arrive. This report requires review; it does not mean the booking was cancelled.';
    }

    if (outcome === 'operator_no_show') {
        return 'The Operator reported that the boat or crew did not arrive. This report requires review; no automatic refund was issued.';
    }

    return 'Check-in was recorded. The trip outcome has not been finalized.';
}

function when(value: string | null | undefined): string {
    if (!value) return 'Not recorded';

    const date = new Date(value);

    return Number.isNaN(date.getTime())
        ? value
        : date.toLocaleString('en-PH', {
            dateStyle: 'medium',
            timeStyle: 'short',
        });
}

const details = computed(() =>
    monitor.value?.mode === 'detail'
        ? monitor.value.record
        : null
);

const recent = computed(() =>
    monitor.value?.mode === 'list'
        ? monitor.value.records
        : []
);
</script>

<template>
    <!-- No trip record means no visible panel. -->
    <section
        v-if="details"
        class="mx-4 mt-4 rounded-3xl border border-amber-500/30 bg-card p-4 text-sm text-foreground shadow-sm sm:mx-6"
        aria-label="Recorded trip outcome"
    >
        <div class="flex items-start gap-3">
            <CheckCircle2
                v-if="details.outcome === 'completed'"
                class="mt-0.5 size-5 shrink-0 text-emerald-600"
            />

            <AlertCircle
                v-else
                class="mt-0.5 size-5 shrink-0 text-amber-600"
            />

            <div class="min-w-0 space-y-1">
                <p class="font-semibold">
                    {{ outcomeLabel(details.outcome) }}
                </p>

                <p>
                    {{ outcomeDetail(details.outcome) }}
                </p>

                <p class="text-xs opacity-75">
                    Booking status: {{ details.booking_status }}
                    · Recorded:
                    {{
                        when(
                            details.recorded_at ??
                            details.checked_in_at
                        )
                    }}
                </p>

                <p
                    v-if="details.notes && details.outcome"
                    class="whitespace-pre-wrap text-xs"
                >
                    Operator's note: {{ details.notes }}
                </p>

                <p class="text-xs font-medium">
                    Trip status and payment status are separate.
                    This record does not change or refund a payment.
                </p>
            </div>
        </div>
    </section>

    <!-- Recent outcomes on the three booking overview pages. -->
    <section
        v-else-if="recent.length"
        class="mx-4 mt-4 rounded-3xl border border-border bg-card p-4 text-sm text-foreground shadow-sm sm:mx-6"
        aria-label="Recent trip outcomes"
    >
        <div class="mb-3 flex items-center gap-2 text-foreground">
            <ClipboardCheck class="size-4 shrink-0 text-primary" />

            <h2 class="font-semibold">
                Recent Trip Outcomes
            </h2>

            <span class="text-xs text-foreground/70">
                Operator-recorded
            </span>
        </div>

        <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
            <Link
                v-for="record in recent"
                :key="record.booking_id"
                :href="bookingUrl(record.booking_id)"
                class="block rounded-xl border border-border bg-muted p-3 text-foreground no-underline transition hover:border-primary"
            >
                <p class="font-semibold text-foreground">
                    Booking #{{ record.booking_id }}
                </p>

                <p class="mt-1 text-xs font-medium text-foreground">
                    {{ outcomeLabel(record.outcome) }}
                </p>

                <p class="mt-1 text-xs text-foreground/70">
                    Booking: {{ record.booking_status }}
                    · {{ when(record.recorded_at) }}
                </p>

                <p
                    v-if="record.outcome !== 'completed'"
                    class="mt-1 text-xs font-medium text-amber-700 dark:text-amber-300"
                >
                    Reported · Needs review
                </p>
            </Link>
        </div>

        <p class="mt-3 text-xs text-foreground/70">
            Latest 12 recorded outcomes.
            No-show reports are not cancellations or refunds.
        </p>
    </section>
</template>
