
<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CalendarDays,
    CreditCard,
    FileText,
    ImageOff,
    MapPin,
    Phone,
    QrCode,
    Receipt,
    ShieldCheck,
    UserRound,
    Users,
} from '@lucide/vue';

import { index } from '@/routes/admin/bookings';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import PickupMap from '@/components/maps/PickupMap.vue';
import {
    Carousel,
    CarouselContent,
    CarouselItem,
    CarouselNext,
    CarouselPrevious,
} from '@/components/ui/carousel';

type Payment = {
    id: number;
    amount: number;
    payment_method: string | null;
    payment_status: string;
    gateway: string | null;
    transaction_reference: string | null;
    notes: string | null;
    paid_at: string | null;
    created_at: string | null;
};

type Booking = {
    id: number;
    booking_status: string;
    number_of_guests: number;
    phone_number: string | null;
    nationality: string | null;
    special_request: string | null;
    qr_token: string | null;
    pickup_latitude: number | string | null;
    pickup_longitude: number | string | null;
    created_at: string | null;
    updated_at: string | null;
    user: {
        id: number;
        name: string;
        email: string;
    } | null;
    package: {
        id: number;
        package_name: string;
        destination: string;
        description: string | null;
        status: string;
        image_urls: string[];
    } | null;
    tour_date: {
        id: number;
        tour_date: string | null;
        capacity: number;
        times: { id: number; time: string; description: string | null }[];
    } | null;
    pickup_schedule: {
        id: number;
        pickup_time: string | null;
        pickup_location: {
            id: number;
            name: string;
            address: string | null;
        } | null;
    } | null;
    booked_unit_price: number | null;
    booked_total: number | null;
    price_snapshot_source: string | null;
    amount_received: number;
    remaining_balance: number | null;
    price_needs_review: boolean;
    overall_payment_status: 'paid' | 'partial' | 'pending' | 'unpaid' | 'review';
    payments: Payment[];
};

const props = defineProps<{ booking: Booking }>();

function money(amount: number | null): string {
    if (amount === null || !Number.isFinite(amount)) return 'Needs review';
    return `₱${amount.toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

function dateOnly(value: string | null): string {
    if (!value) return '—';
    const date = new Date(`${value.slice(0, 10)}T00:00:00`);
    return Number.isNaN(date.getTime())
        ? value
        : date.toLocaleDateString('en-US', {
            year: 'numeric', month: 'long', day: 'numeric',
        });
}

function dateTime(value: string | null): string {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime())
        ? value
        : date.toLocaleString('en-US', {
            dateStyle: 'medium', timeStyle: 'short',
        });
}

function clock(value: string | null): string {
    if (!value) return '—';
    const match = value.match(/^(\d{1,2}):(\d{2})/);
    if (!match) return value;
    const hours = Number(match[1]);
    const minutes = Number(match[2]);
    if (hours > 23 || minutes > 59) return value;
    return `${hours % 12 || 12}:${String(minutes).padStart(2, '0')} ${hours >= 12 ? 'PM' : 'AM'}`;
}

function label(value: string): string {
    return value.replace(/_/g, ' ').replace(/\b\w/g, letter => letter.toUpperCase());
}

function tripVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'confirmed') return 'default';
    if (status === 'cancelled') return 'destructive';
    if (status === 'completed') return 'secondary';
    return 'outline';
}

function paymentLabel(): string {
    switch (props.booking.overall_payment_status) {
        case 'paid': return 'Fully Paid';
        case 'partial': return 'Partially Paid';
        case 'pending': return 'Awaiting Payment';
        case 'review': return 'Price Needs Review';
        default: return 'Unpaid';
    }
}

function paymentTone(status: string): string {
    if (status === 'paid') return 'text-emerald-700 dark:text-emerald-400';
    if (status === 'partial') return 'text-sky-700 dark:text-sky-300';
    if (status === 'review') return 'text-amber-700 dark:text-amber-400';
    return 'text-muted-foreground';
}

defineOptions({
    layout: {
        breadcrumbs: [
            { href: index(), title: 'Booking Oversight' },
            { href: '#', title: 'Booking Details' },
        ],
    },
});
</script>

<template>
    <Head :title="`Booking #${booking.id} — Oversight`" />

    <div class="mx-auto max-w-7xl space-y-6 px-4 py-6 pb-16 md:px-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <Link :href="index()" class="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground">
                    <ArrowLeft class="h-4 w-4" /> Back to Booking Oversight
                </Link>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-bold tracking-tight md:text-3xl">Booking #{{ booking.id }}</h1>
                    <Badge :variant="tripVariant(booking.booking_status)">
                        {{ label(booking.booking_status) }}
                    </Badge>
                </div>
                <p class="mt-1 text-sm text-muted-foreground">
                    Platform-wide, read-only reservation and payment details.
                </p>
            </div>
            <div class="inline-flex items-center gap-2 rounded-xl border bg-muted/30 px-4 py-2 text-xs text-muted-foreground">
                <ShieldCheck class="h-4 w-4" /> Verified online payments are handled by PayMongo
            </div>
        </div>

        <div v-if="booking.price_needs_review" class="rounded-2xl border border-amber-500/40 bg-amber-500/5 p-4 text-sm text-amber-700 dark:text-amber-300">
            <strong>Original price needs verification.</strong>
            This booking has an estimated or missing original price. Check the original booking record and receipts before relying on its balance.
        </div>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border bg-card p-4">
                <div class="flex items-center gap-2 text-xs text-muted-foreground"><Users class="h-4 w-4" /> Guests</div>
                <p class="mt-2 text-2xl font-bold">{{ booking.number_of_guests }}</p>
            </div>
            <div class="rounded-2xl border bg-card p-4">
                <div class="flex items-center gap-2 text-xs text-muted-foreground"><CalendarDays class="h-4 w-4" /> Tour Date</div>
                <p class="mt-2 text-lg font-bold">{{ dateOnly(booking.tour_date?.tour_date ?? null) }}</p>
            </div>
            <div class="rounded-2xl border bg-card p-4">
                <div class="flex items-center gap-2 text-xs text-muted-foreground"><CreditCard class="h-4 w-4" /> Booking Total</div>
                <p class="mt-2 text-2xl font-bold">{{ money(booking.booked_total) }}</p>
            </div>
            <div class="rounded-2xl border bg-card p-4">
                <div class="flex items-center gap-2 text-xs text-muted-foreground"><Receipt class="h-4 w-4" /> Overall Payment</div>
                <p class="mt-2 text-lg font-bold" :class="paymentTone(booking.overall_payment_status)">
                    {{ paymentLabel() }}
                </p>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1.5fr)_minmax(280px,1fr)]">
            <div class="space-y-6">
                <section class="rounded-2xl border bg-card p-5 md:p-6">
                    <h2 class="flex items-center gap-2 text-lg font-bold"><MapPin class="h-5 w-5 text-primary" /> Tour Package</h2>
                    <h3 class="mt-4 text-xl font-bold">{{ booking.package?.package_name ?? 'Unknown package' }}</h3>
                    <p class="mt-1 text-sm text-muted-foreground">{{ booking.package?.destination ?? '—' }}</p>
                    <p v-if="booking.package?.description" class="mt-3 whitespace-pre-line text-sm text-muted-foreground">
                        {{ booking.package.description }}
                    </p>

                    <Carousel v-if="booking.package?.image_urls?.length" class="mt-5 w-full">
                        <CarouselContent>
                            <CarouselItem v-for="url in booking.package.image_urls" :key="url">
                                <img :src="url" :alt="booking.package?.package_name ?? 'Tour image'" class="aspect-video w-full rounded-xl border object-cover" />
                            </CarouselItem>
                        </CarouselContent>
                        <template v-if="booking.package.image_urls.length > 1">
                            <CarouselPrevious class="left-2" />
                            <CarouselNext class="right-2" />
                        </template>
                    </Carousel>
                    <div v-else class="mt-5 flex items-center justify-center gap-2 rounded-xl border border-dashed bg-muted/20 p-8 text-sm text-muted-foreground">
                        <ImageOff class="h-5 w-5" /> No package images
                    </div>

                    <div class="mt-5 grid gap-3 rounded-xl bg-muted/30 p-4 text-sm sm:grid-cols-2">
                        <div>
                            <p class="text-xs text-muted-foreground">Saved rate per guest</p>
                            <p class="font-bold">{{ money(booking.booked_unit_price) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">Saved booking total</p>
                            <p class="font-bold">{{ money(booking.booked_total) }}</p>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-muted-foreground">
                        These prices refer to the original reservation, not the current package catalog.
                    </p>
                </section>

                <section class="rounded-2xl border bg-card p-5 md:p-6">
                    <h2 class="flex items-center gap-2 text-lg font-bold"><CalendarDays class="h-5 w-5 text-primary" /> Tour Schedule & Pickup</h2>
                    <div class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                        <div><p class="text-xs text-muted-foreground">Tour date</p><p class="font-semibold">{{ dateOnly(booking.tour_date?.tour_date ?? null) }}</p></div>
                        <div><p class="text-xs text-muted-foreground">Date capacity</p><p class="font-semibold">{{ booking.tour_date?.capacity ?? '—' }}</p></div>
                        <div><p class="text-xs text-muted-foreground">Pickup station</p><p class="font-semibold">{{ booking.pickup_schedule?.pickup_location?.name ?? '—' }}</p></div>
                        <div><p class="text-xs text-muted-foreground">Pickup time</p><p class="font-semibold">{{ clock(booking.pickup_schedule?.pickup_time ?? null) }}</p></div>
                    </div>
                    <p v-if="booking.pickup_schedule?.pickup_location?.address" class="mt-3 text-xs text-muted-foreground">
                        {{ booking.pickup_schedule.pickup_location.address }}
                    </p>
                    <div v-if="booking.tour_date?.times?.length" class="mt-5 border-t pt-4">
                        <h3 class="text-sm font-semibold">Tour Times</h3>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <span v-for="time in booking.tour_date.times" :key="time.id" class="rounded-lg border bg-muted/20 px-3 py-2 text-xs">
                                {{ clock(time.time) }}<span v-if="time.description"> · {{ time.description }}</span>
                            </span>
                        </div>
                    </div>
                    <PickupMap
                        v-if="booking.pickup_latitude != null && booking.pickup_longitude != null"
                        class="mt-5"
                        :latitude="booking.pickup_latitude"
                        :longitude="booking.pickup_longitude"
                        label="Tourist pickup pin"
                    />
                </section>

                <section id="payment" class="scroll-mt-24 rounded-2xl border bg-card p-5 md:p-6">
                    <h2 class="flex items-center gap-2 text-lg font-bold"><CreditCard class="h-5 w-5 text-primary" /> Recorded Payments</h2>
                    <p class="mt-2 text-xs text-muted-foreground">
                        This section is for audit only. PayMongo confirms GCash; Operators record any remaining cash balance.
                    </p>
                    <div class="mt-5 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-xl border p-3">
                            <p class="text-xs text-muted-foreground">Saved total</p>
                            <p class="mt-1 font-bold">{{ money(booking.booked_total) }}</p>
                        </div>
                        <div class="rounded-xl border p-3">
                            <p class="text-xs text-muted-foreground">Received</p>
                            <p class="mt-1 font-bold">{{ money(booking.amount_received) }}</p>
                        </div>
                        <div class="rounded-xl border p-3">
                            <p class="text-xs text-muted-foreground">Remaining</p>
                            <p class="mt-1 font-bold">{{ money(booking.remaining_balance) }}</p>
                        </div>
                    </div>
                    <div v-if="!booking.payments.length" class="mt-5 rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
                        No payments have been recorded.
                    </div>
                    <div v-else class="mt-5 space-y-3">
                        <div v-for="payment in booking.payments" :key="payment.id" class="rounded-xl border p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold">{{ money(payment.amount) }}</p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ label(payment.payment_method ?? 'Unknown') }}
                                        <span v-if="payment.gateway"> · {{ payment.gateway }}</span>
                                    </p>
                                </div>
                                <Badge :variant="payment.payment_status === 'paid' ? 'default' : 'outline'">
                                    {{ label(payment.payment_status) }}
                                </Badge>
                            </div>
                            <div class="mt-3 space-y-1 text-xs text-muted-foreground">
                                <p>Reference: <span class="break-all font-mono text-foreground">{{ payment.transaction_reference ?? '—' }}</span></p>
                                <p>Paid: {{ dateTime(payment.paid_at) }}</p>
                                <p>Recorded: {{ dateTime(payment.created_at) }}</p>
                                <p v-if="payment.notes" class="whitespace-pre-line">Remarks: {{ payment.notes }}</p>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <div class="space-y-6">
                <section class="rounded-2xl border bg-card p-5 md:p-6">
                    <h2 class="flex items-center gap-2 text-lg font-bold"><UserRound class="h-5 w-5 text-primary" /> Tourist Information</h2>
                    <dl class="mt-4 space-y-4 text-sm">
                        <div><dt class="text-xs text-muted-foreground">Name</dt><dd class="font-semibold">{{ booking.user?.name ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-muted-foreground">Email</dt><dd class="break-all font-semibold">{{ booking.user?.email ?? '—' }}</dd></div>
                        <div><dt class="flex items-center gap-1 text-xs text-muted-foreground"><Phone class="h-3.5 w-3.5" /> Phone</dt><dd class="font-semibold">{{ booking.phone_number ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-muted-foreground">Nationality</dt><dd class="font-semibold">{{ booking.nationality ?? '—' }}</dd></div>
                        <div v-if="booking.special_request"><dt class="flex items-center gap-1 text-xs text-muted-foreground"><FileText class="h-3.5 w-3.5" /> Special request</dt><dd class="whitespace-pre-line">{{ booking.special_request }}</dd></div>
                    </dl>
                </section>
                <section class="rounded-2xl border bg-card p-5 md:p-6">
                    <h2 class="flex items-center gap-2 text-lg font-bold"><QrCode class="h-5 w-5 text-primary" /> Reservation Audit</h2>
                    <dl class="mt-4 space-y-4 text-sm">
                        <div><dt class="text-xs text-muted-foreground">Booking ID</dt><dd class="font-semibold">#{{ booking.id }}</dd></div>
                        <div><dt class="text-xs text-muted-foreground">Created</dt><dd class="font-semibold">{{ dateTime(booking.created_at) }}</dd></div>
                        <div><dt class="text-xs text-muted-foreground">Updated</dt><dd class="font-semibold">{{ dateTime(booking.updated_at) }}</dd></div>
                        <div><dt class="text-xs text-muted-foreground">Price source</dt><dd class="font-semibold">{{ label(booking.price_snapshot_source ?? 'Unavailable') }}</dd></div>
                        <div><dt class="text-xs text-muted-foreground">QR token</dt><dd class="break-all font-mono text-xs">{{ booking.qr_token ?? 'Not generated' }}</dd></div>
                    </dl>
                </section>
                <section class="rounded-2xl border bg-muted/20 p-5 text-sm">
                    <ShieldCheck class="h-5 w-5 text-primary" />
                    <p class="mt-2 font-semibold">Read-only oversight</p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        The old Confirm Payment, Update Status, Record Balance and Delete Booking actions were removed from this page.
                        Use the appropriate verified Tourist/Operator workflows for financial activity.
                    </p>
                    <Button as-child variant="outline" class="mt-4 w-full">
                        <Link :href="index()">Return to Booking Oversight</Link>
                    </Button>
                </section>
            </div>
        </div>
    </div>
</template>
