
<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    CalendarDays,
    CreditCard,
    Download,
    Eye,
    ShieldCheck,
} from '@lucide/vue';

import SimpleTable from '@/components/tables/DataTable.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';

import {
    index,
    show,
    data,
} from '@/routes/admin/bookings';

/*
|--------------------------------------------------------------------------
| TABLE ROW TYPE
|--------------------------------------------------------------------------
|
| DataTable.vue returns Record<string, unknown>.
| These helpers safely convert values before display.
|
*/

type TableRow = Record<string, unknown>;

const columns = [
    {
        key: 'id',
        label: 'ID',
        sortable: true,
        width: '80px',
    },
    {
        key: 'user_name',
        label: 'Tourist',
        sortable: false,
    },
    {
        key: 'package_name',
        label: 'Package',
        sortable: false,
    },
    {
        key: 'tour_date',
        label: 'Tour Date',
        sortable: false,
    },
    {
        key: 'pickup_location',
        label: 'Pickup',
        sortable: false,
    },
    {
        key: 'number_of_guests',
        label: 'Guests',
        sortable: true,
    },
    {
        key: 'booking_status',
        label: 'Trip Status',
        sortable: true,
    },
    {
        key: 'remaining_balance',
        label: 'Recorded Balance',
        sortable: false,
    },
    {
        key: 'actions',
        label: 'Actions',
        sortable: false,
    },
];

/*
|--------------------------------------------------------------------------
| SAFE TEXT CONVERSION
|--------------------------------------------------------------------------
*/

function displayText(value: unknown): string {
    if (typeof value === 'string') {
        return value.trim() || '—';
    }

    if (
        typeof value === 'number' &&
        Number.isFinite(value)
    ) {
        return String(value);
    }

    return '—';
}

/*
|--------------------------------------------------------------------------
| SAFE NUMBER CONVERSION
|--------------------------------------------------------------------------
*/

function amountFrom(
    value: unknown,
): number | null {
    if (typeof value === 'number') {
        return Number.isFinite(value)
            ? value
            : null;
    }

    if (
        typeof value === 'string' &&
        value.trim() !== ''
    ) {
        const amount = Number(value);

        return Number.isFinite(amount)
            ? amount
            : null;
    }

    return null;
}

/*
|--------------------------------------------------------------------------
| MONEY FORMATTING
|--------------------------------------------------------------------------
*/

function formatMoney(value: unknown): string {
    const amount = amountFrom(value);

    if (amount === null) {
        return '—';
    }

    return amount.toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

/*
|--------------------------------------------------------------------------
| DATE FORMATTING
|--------------------------------------------------------------------------
*/

function formatDate(value: unknown): string {
    if (
        typeof value !== 'string' ||
        !value.trim()
    ) {
        return '—';
    }

    const date = new Date(
        `${value.slice(0, 10)}T00:00:00`,
    );

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

/*
|--------------------------------------------------------------------------
| TIME FORMATTING
|--------------------------------------------------------------------------
*/

function formatTime(value: unknown): string {
    if (
        typeof value !== 'string' ||
        !value.trim()
    ) {
        return '—';
    }

    const match = value.match(
        /^(\d{1,2}):(\d{2})/,
    );

    if (!match) {
        return value;
    }

    const hours = Number(match[1]);
    const minutes = Number(match[2]);

    if (
        hours < 0 ||
        hours >= 24 ||
        minutes < 0 ||
        minutes >= 60
    ) {
        return value;
    }

    const hour12 = hours % 12 || 12;

    return `${hour12}:${String(minutes).padStart(2, '0')} ${
        hours >= 12 ? 'PM' : 'AM'
    }`;
}

/*
|--------------------------------------------------------------------------
| BOOKING STATUS
|--------------------------------------------------------------------------
*/

function bookingStatusClass(
    value: unknown,
):
    | 'default'
    | 'destructive'
    | 'secondary'
    | 'outline' {
    switch (value) {
        case 'confirmed':
            return 'default';

        case 'cancelled':
            return 'destructive';

        case 'completed':
            return 'secondary';

        default:
            return 'outline';
    }
}

/*
|--------------------------------------------------------------------------
| PAYMENT AND BALANCE HELPERS
|--------------------------------------------------------------------------
|
| Laravel calculates booking balances using
| the saved booking price introduced in Fix 4A.
|
*/

function amountReceived(
    row: TableRow,
): number {
    return amountFrom(
        row.amount_received,
    ) ?? 0;
}

function remainingBalance(
    row: TableRow,
): number | null {
    return amountFrom(
        row.remaining_balance,
    );
}

function hasOutstandingBalance(
    row: TableRow,
): boolean {
    const remaining = remainingBalance(row);

    return (
        remaining !== null &&
        remaining > 0
    );
}

function hasRecordedPayment(
    row: TableRow,
): boolean {
    return amountReceived(row) > 0;
}

/*
|--------------------------------------------------------------------------
| SAFE BOOKING ROUTE HANDLING
|--------------------------------------------------------------------------
|
| Wayfinder's show() route requires a number
| or a booking object.
|
| DataTable returns unknown values, so the
| booking ID must be validated first.
|
*/

function bookingId(
    row: TableRow,
): number | null {
    const id = amountFrom(row.id);

    return (
        id !== null &&
        Number.isSafeInteger(id) &&
        id > 0
    )
        ? id
        : null;
}

function bookingDetailsUrl(
    row: TableRow,
): string {
    const id = bookingId(row);

    return id === null
        ? '#'
        : show(id).url;
}

function bookingPaymentUrl(
    row: TableRow,
): string {
    return `${bookingDetailsUrl(row)}#payment`;
}

function hasPaymentRecord(
    row: TableRow,
): boolean {
    const id = amountFrom(
        row.payment_id,
    );

    return (
        id !== null &&
        id > 0
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
                title: 'Booking Oversight',
            },
        ],
    },
});
</script>

<template>
    <Head title="Booking Oversight" />

    <div class="px-4 py-6">
        <!-- Page Header -->
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <div>
                <Heading
                    title="Booking Oversight"
                    description="Review reservations and recorded balances across HopSurigao."
                />

                <p
                    class="mt-2 flex items-center gap-1.5 text-xs text-foreground/70"
                >
                    <ShieldCheck
                        class="h-3.5 w-3.5"
                    />

                    GCash payments are confirmed through
                    PayMongo, not manually.
                </p>
            </div>

            <!-- Header Actions -->
            <div class="flex flex-wrap gap-2">
                <Button
                    as-child
                    variant="outline"
                    class="shrink-0"
                >
                    <a href="/admin/bookings/export">
                        <Download
                            class="mr-2 h-4 w-4"
                        />

                        Export
                    </a>
                </Button>

                <Button
                    as-child
                    variant="outline"
                    class="shrink-0"
                >
                    <Link href="/admin/bookings/calendar">
                        <CalendarDays
                            class="mr-2 h-4 w-4"
                        />

                        Booking Calendar
                    </Link>
                </Button>
            </div>
        </div>

        <!-- Information -->
        <div
            class="mt-5 rounded-xl border border-border bg-card p-3 text-xs text-foreground/70 shadow-sm"
        >
            Recorded balances come from Laravel's
            booking price and payment records.

            Older bookings with estimated prices
            still require receipt verification.

            This overview is read-only and does
            not confirm payments.
        </div>

        <!-- Bookings Table -->
        <div class="mt-6">
            <SimpleTable
                :fetch-url="data().url"
                :columns="columns"
                searchable
                enable-cache
            >
                <!-- Tourist -->
                <template
                    v-slot:[`cell-user_name`]="{ row }"
                >
                    <div>
                        <p class="font-medium">
                            {{ displayText(row.user_name) }}
                        </p>

                        <p
                            v-if="row.user_email"
                            class="text-xs text-foreground/70"
                        >
                            {{ displayText(row.user_email) }}
                        </p>
                    </div>
                </template>

                <!-- Package -->
                <template
                    v-slot:[`cell-package_name`]="{ row }"
                >
                    <div>
                        <p class="font-medium">
                            {{ displayText(row.package_name) }}
                        </p>

                        <p
                            v-if="row.destination"
                            class="text-xs text-foreground/70"
                        >
                            {{ displayText(row.destination) }}
                        </p>
                    </div>
                </template>

                <!-- Tour Date -->
                <template
                    v-slot:[`cell-tour_date`]="{ row }"
                >
                    {{ formatDate(row.tour_date) }}
                </template>

                <!-- Pickup -->
                <template
                    v-slot:[`cell-pickup_location`]="{ row }"
                >
                    <div>
                        <p class="font-medium">
                            {{ displayText(row.pickup_location) }}
                        </p>

                        <p
                            v-if="row.pickup_time"
                            class="text-xs text-foreground/70"
                        >
                            {{ formatTime(row.pickup_time) }}
                        </p>
                    </div>
                </template>

                <!-- Guests -->
                <template
                    v-slot:[`cell-number_of_guests`]="{ row }"
                >
                    {{ displayText(row.number_of_guests) }}
                </template>

                <!-- Trip Status -->
                <template
                    v-slot:[`cell-booking_status`]="{ row }"
                >
                    <Badge
                        :variant="
                            bookingStatusClass(
                                row.booking_status
                            )
                        "
                    >
                        {{ displayText(row.booking_status) }}
                    </Badge>
                </template>

                <!-- Remaining Balance -->
                <template
                    v-slot:[`cell-remaining_balance`]="{ row }"
                >
                    <div
                        v-if="hasOutstandingBalance(row)"
                        class="text-sm"
                    >
                        <p
                            class="font-semibold text-sky-700 dark:text-sky-300"
                        >
                            ₱{{ formatMoney(row.remaining_balance) }}
                            remaining
                        </p>

                        <p
                            class="text-xs text-foreground/70"
                        >
                            Received:
                            ₱{{ formatMoney(row.amount_received) }}
                        </p>
                    </div>

                    <!-- Fully Collected -->
                    <div
                        v-else-if="
                            remainingBalance(row) !== null &&
                            hasRecordedPayment(row)
                        "
                        class="text-sm"
                    >
                        <p
                            class="font-semibold text-emerald-600 dark:text-emerald-400"
                        >
                            ₱0.00 remaining
                        </p>

                        <p
                            class="text-xs text-foreground/70"
                        >
                            Recorded:
                            ₱{{ formatMoney(row.amount_received) }}
                        </p>
                    </div>

                    <!-- No Payments -->
                    <div
                        v-else
                        class="text-xs text-foreground/70"
                    >
                        {{
                            remainingBalance(row) === null
                                ? 'Balance unavailable'
                                : 'No payment recorded'
                        }}
                    </div>
                </template>

                <!-- View Only Actions -->
                <template
                    v-slot:[`cell-actions`]="{ row }"
                >
                    <div
                        class="inline-flex items-center gap-2"
                    >
                        <!-- Booking Details -->
                        <Button
                            v-if="bookingId(row) !== null"
                            as-child
                            variant="outline"
                            size="sm"
                        >
                            <Link
                                :href="bookingDetailsUrl(row)"
                            >
                                <Eye
                                    class="mr-1.5 h-4 w-4"
                                />

                                Details
                            </Link>
                        </Button>

                        <!-- Payment Section -->
                        <Button
                            v-if="
                                bookingId(row) !== null &&
                                hasPaymentRecord(row)
                            "
                            as-child
                            variant="outline"
                            size="sm"
                        >
                            <Link
                                :href="bookingPaymentUrl(row)"
                            >
                                <CreditCard
                                    class="mr-1.5 h-4 w-4"
                                />

                                Payments
                            </Link>
                        </Button>
                    </div>
                </template>
            </SimpleTable>
        </div>
    </div>
</template>
