<script setup lang="ts">
/*
|--------------------------------------------------------------------------
| Imports
|--------------------------------------------------------------------------
*/

import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Carousel,
    CarouselContent,
    CarouselItem,
    CarouselNext,
    CarouselPrevious,
} from '@/components/ui/carousel';
import {
    ArrowLeft,
    Bus,
    CalendarDays,
    CheckCircle2,
    Clock,
    CreditCard,
    ExternalLink,
    FileText,
    ImageOff,
    Mail,
    MapPin,
    Phone,
    QrCode,
    Receipt,
    ScanLine,
    ShieldCheck,
    Sparkles,
    Ticket,
    User,
    Users,
} from '@lucide/vue';

import { qrscanner } from '@/routes/admin';
import { show as showBookingRoute } from '@/routes/admin/bookings';

/*
|--------------------------------------------------------------------------
| Types
|--------------------------------------------------------------------------
*/

type PackageImage = {
    name?: string;
    path?: string;
    url: string;
};

type Payment = {
    id: number;
    amount: number | string;
    payment_method: string | null;
    payment_status: string;
    transaction_reference: string | null;
    proof_of_payment_url: string | null;
    proof_of_payment_is_image: boolean | null;
    notes: string | null;
    paid_at: string | null;
    created_at: string | null;
    updated_at: string | null;
};

type Booking = {
    id: number;
    booking_status: string;
    qr_token: string | null;
    number_of_guests: number;
    phone_number: string;
    nationality: string;
    special_request: string | null;
    created_at: string;
    updated_at: string;

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
        price: number | string;
        status: string;
        image_urls: string[];
    } | null;

    tour_date: {
        id: number;
        tour_date: string;
        capacity: number;
        times: {
            id: number;
            time: string;
            description: string | null;
        }[];
    } | null;

    pickup_schedule: {
        id: number;
        pickup_time: string;
        pickup_location: {
            id: number;
            name: string;
            address: string | null;
            description: string | null;
        } | null;
    } | null;

    payments: Payment[];
    amount_received: number;
    remaining_balance: number;
    package_images: PackageImage[];
    payment_proofs: PackageImage[];
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
| Breadcrumbs
|--------------------------------------------------------------------------
*/

defineOptions({
    layout: {
        breadcrumbs: [
            {
                href: qrscanner(),
                title: 'QR Scanner',
            },
            {
                href: '#',
                title: 'Verification Result',
            },
        ],
    },
});

/*
|--------------------------------------------------------------------------
| State
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| Computed
|--------------------------------------------------------------------------
*/

const packageImageUrls = computed<string[]>(() => {
    if (props.booking.package?.image_urls && props.booking.package.image_urls.length > 0) {
        return props.booking.package.image_urls;
    }

    if (props.booking.package_images && props.booking.package_images.length > 0) {
        return props.booking.package_images.map((img) => img.url);
    }

    return [];
});

const payment = computed(() => {
    return props.booking.payments?.[0] ?? null;
});

const balanceForm = useForm({
    amount: '',
});

function recordBalance(): void {
    balanceForm.amount = Number(props.booking.remaining_balance).toFixed(2);
    balanceForm.post(`/admin/bookings/${props.booking.id}/balance`, {
        preserveScroll: true,
    });
}

function formatMoney(amount: number | string | null): string {
    return Number(amount ?? 0).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function statusBadgeClass(status: string) {
    switch (status?.toLowerCase()) {
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

function paymentStatusBadgeClass(status?: string) {
    switch (status?.toLowerCase()) {
        case 'paid':
        case 'confirmed':
            return 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20';
        case 'partial':
            return 'bg-sky-500/10 text-sky-700 dark:text-sky-300 border-sky-500/20';
        case 'pending':
            return 'bg-orange-500/10 text-orange-600 dark:text-orange-400 border-orange-500/20';
        case 'rejected':
        case 'cancelled':
        case 'failed':
            return 'bg-destructive/10 text-destructive border-destructive/20';
        default:
            return 'bg-muted text-muted-foreground border-border';
    }
}

function statusLabel(status: string | null | undefined): string {
    if (!status) return '—';
    return status.charAt(0).toUpperCase() + status.slice(1);
}

function formatDate(date: string | null): string {
    if (!date) return '—';

    const parsed = new Date(date);
    if (Number.isNaN(parsed.getTime())) return date;

    return parsed.toLocaleDateString('en-US', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function formatTime(time: string | null): string {
    if (!time) return '—';

    const match = time.match(/^(\d{1,2}):(\d{2})/);
    if (match) {
        const hours = Number(match[1]);
        const minutes = Number(match[2]);
        if (hours >= 0 && hours <= 23 && minutes >= 0 && minutes <= 59) {
            const period = hours >= 12 ? 'PM' : 'AM';
            const hour12 = hours % 12 === 0 ? 12 : hours % 12;
            return `${hour12}:${String(minutes).padStart(2, '0')} ${period}`;
        }
    }

    return time;
}

function formatDateTime(date: string | null): string {
    if (!date) return '—';

    const parsed = new Date(date);
    if (Number.isNaN(parsed.getTime())) return '—';

    return parsed.toLocaleString('en-US', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

function formatPrice(price: number | string | null): string {
    return Number(price ?? 0).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function openImage(url: string) {
    window.open(url, '_blank', 'noopener,noreferrer');
}
</script>

<template>
    <Head title="Verified Booking Details" />

    <div class="px-4 py-8 max-w-7xl mx-auto space-y-8">
        <!-- ==============================================================
             TOP BAR & ACTION ACTIONS
        ============================================================== -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <Button
                    as-child
                    variant="outline"
                    size="sm"
                    class="h-9 w-9 p-0 rounded-xl"
                >
                    <Link :href="qrscanner()">
                        <ArrowLeft class="h-4 w-4" />
                    </Link>
                </Button>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-xl font-extrabold tracking-tight text-foreground sm:text-2xl">
                            Verified Reservation Pass
                        </h1>
                        <Badge variant="outline" class="border-emerald-500/30 bg-emerald-500/10 text-emerald-600 font-semibold gap-1 py-0.5">
                            <ShieldCheck class="h-3 w-3" />
                            Valid Pass
                        </Badge>
                    </div>
                    <p class="text-xs text-muted-foreground mt-0.5">
                        Scanned & verified through secure system terminal
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <Button
                    as-child
                    variant="outline"
                    size="sm"
                    class="gap-1.5 rounded-xl shadow-xs"
                >
                    <Link :href="qrscanner()">
                        <ScanLine class="h-3.5 w-3.5" />
                        <span>Scan Next Code</span>
                    </Link>
                </Button>

                <Button
                    as-child
                    size="sm"
                    class="gap-1.5 rounded-xl shadow-xs"
                >
                    <Link :href="showBookingRoute(booking.id)">
                        <ExternalLink class="h-3.5 w-3.5" />
                        <span>Full Admin Record</span>
                    </Link>
                </Button>
            </div>
        </div>

        <!-- ==============================================================
             VERIFICATION HERO BANNER
        ============================================================== -->
        <div class="relative overflow-hidden rounded-3xl border border-emerald-500/30 bg-gradient-to-br from-emerald-500/15 via-emerald-500/5 to-background p-6 shadow-sm sm:p-8">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                <div class="space-y-2 max-w-2xl">
                    <div class="inline-flex items-center gap-2 rounded-full border border-emerald-500/30 bg-emerald-500/20 px-3 py-1 text-xs font-bold text-emerald-700 dark:text-emerald-300">
                        <CheckCircle2 class="h-3.5 w-3.5" />
                        QR Code Authentic & Authenticated
                    </div>

                    <h2 class="text-2xl font-black tracking-tight text-foreground sm:text-3xl">
                        {{ booking.package?.package_name ?? 'Island Tour Reservation' }}
                    </h2>

                    <p class="text-xs text-muted-foreground flex flex-wrap items-center gap-y-1 gap-x-3">
                        <span>Passenger: <strong>{{ booking.user?.name ?? 'Registered Customer' }}</strong></span>
                        <span>•</span>
                        <span>Party Size: <strong>{{ booking.number_of_guests }} Person(s)</strong></span>
                        <span>•</span>
                        <span>Booking ID: <strong class="font-mono">#{{ booking.id }}</strong></span>
                    </p>
                </div>

                <!-- Status Pill Summary Badges -->
                <div class="flex flex-wrap items-center gap-3">
                    <div class="rounded-2xl border bg-card/80 px-4 py-3 shadow-xs text-center min-w-[130px]">
                        <span class="block text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                            Manifest Status
                        </span>
                        <span
                            class="inline-block mt-1 rounded-full border px-2.5 py-0.5 text-xs font-bold capitalize"
                            :class="statusBadgeClass(booking.booking_status)"
                        >
                            {{ statusLabel(booking.booking_status) }}
                        </span>
                    </div>

                    <div class="rounded-2xl border bg-card/80 px-4 py-3 shadow-xs text-center min-w-[130px]">
                        <span class="block text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                            Payment State
                        </span>
                        <span
                            v-if="payment"
                            class="inline-block mt-1 rounded-full border px-2.5 py-0.5 text-xs font-bold capitalize"
                            :class="paymentStatusBadgeClass(payment.payment_status)"
                        >
                            {{ statusLabel(payment.payment_status) }}
                        </span>
                        <span v-else class="inline-block mt-1 text-xs font-semibold text-muted-foreground">
                            Unpaid / Pending
                        </span>
                    </div>
                    <div class="rounded-2xl border bg-card/80 px-4 py-3 shadow-xs text-center min-w-[130px]">
                        <span class="block text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                            Balance left
                        </span>
                        <span
                            v-if="Number(booking.remaining_balance) > 0"
                            class="mt-1 block text-lg font-black text-sky-700 dark:text-sky-300"
                        >
                            ₱{{ formatMoney(booking.remaining_balance) }}
                        </span>
                        <span v-else-if="Number(booking.amount_received) > 0" class="mt-1 block text-sm font-bold text-emerald-600 dark:text-emerald-400">
                            None — paid in full
                        </span>
                        <span v-else class="mt-1 block text-xs font-semibold text-muted-foreground">
                            No payment yet
                        </span>
                    </div>
                </div>
            </div>

            <form
                v-if="Number(booking.amount_received) > 0 && Number(booking.remaining_balance) > 0"
                class="mt-6 rounded-2xl border bg-card/80 p-4"
                @submit.prevent="recordBalance"
            >
                <p class="text-sm font-semibold text-foreground">Collect the remaining balance before departure</p>
                <p class="mt-1 text-xs text-muted-foreground">
                    ₱{{ formatMoney(booking.amount_received) }} is already received. Record the cash collected now so this guest can leave.
                </p>
                <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-end">
                    <label class="block flex-1 text-xs font-semibold text-muted-foreground">
                        Amount received
                        <input
                            v-model="balanceForm.amount"
                            type="number"
                            min="0.01"
                            step="0.01"
                            :max="booking.remaining_balance"
                            class="mt-1 h-10 w-full rounded-xl border border-border/80 bg-background px-3 text-sm text-foreground"
                            :placeholder="formatMoney(booking.remaining_balance)"
                        />
                    </label>
                    <Button type="submit" :disabled="balanceForm.processing" class="rounded-xl">
                        {{ balanceForm.processing ? 'Saving...' : 'Update balance' }}
                    </Button>
                </div>
                <p v-if="balanceForm.errors.amount" class="mt-2 text-xs text-destructive">
                    {{ balanceForm.errors.amount }}
                </p>
            </form>
        </div>

        <!-- ==============================================================
             MAIN MANIFEST GRID
        ============================================================== -->
        <div class="grid gap-6 lg:grid-cols-12 lg:items-start">
            <!-- Left Column: Core Guest & Schedule Info (7 cols) -->
            <div class="space-y-6 lg:col-span-7">
                <!-- Guest Information Card -->
                <section class="rounded-3xl border bg-card/60 p-6 backdrop-blur-md shadow-sm sm:p-7 space-y-4">
                    <div class="flex items-center gap-3 pb-3 border-b border-border/60">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                            <User class="h-5 w-5" />
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-foreground">
                                Passenger Information
                            </h2>
                            <p class="text-xs text-muted-foreground">Guest profile and direct contact lines</p>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="rounded-2xl border bg-background/60 p-4 backdrop-blur space-y-1">
                            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                                <User class="h-3.5 w-3.5 text-primary" />
                                Full Name
                            </span>
                            <p class="text-base font-bold text-foreground">
                                {{ booking.user?.name ?? '—' }}
                            </p>
                        </div>

                        <div class="rounded-2xl border bg-background/60 p-4 backdrop-blur space-y-1">
                            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                                <Mail class="h-3.5 w-3.5 text-primary" />
                                Email Address
                            </span>
                            <p class="text-sm font-semibold text-foreground truncate">
                                {{ booking.user?.email ?? '—' }}
                            </p>
                        </div>

                        <div class="rounded-2xl border bg-background/60 p-4 backdrop-blur space-y-1">
                            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                                <Phone class="h-3.5 w-3.5 text-primary" />
                                Contact Number
                            </span>
                            <p class="text-sm font-semibold text-foreground">
                                {{ booking.phone_number || '—' }}
                            </p>
                        </div>

                        <div class="rounded-2xl border bg-background/60 p-4 backdrop-blur space-y-1">
                            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                                <Users class="h-3.5 w-3.5 text-primary" />
                                Party Composition
                            </span>
                            <p class="text-sm font-semibold text-foreground">
                                {{ booking.number_of_guests }} Guest(s) • {{ booking.nationality || 'Unspecified' }}
                            </p>
                        </div>
                    </div>

                    <!-- Special Request if any -->
                    <div v-if="booking.special_request" class="rounded-2xl border border-amber-500/30 bg-amber-500/10 p-4 space-y-1">
                        <span class="text-xs font-bold text-amber-700 dark:text-amber-400 flex items-center gap-1.5">
                            <Sparkles class="h-3.5 w-3.5" />
                            Special Request Note
                        </span>
                        <p class="text-xs text-foreground/90 whitespace-pre-line leading-relaxed">
                            {{ booking.special_request }}
                        </p>
                    </div>
                </section>

                <!-- Tour Date & Departure Schedule Card -->
                <section class="rounded-3xl border bg-card/60 p-6 backdrop-blur-md shadow-sm sm:p-7 space-y-4">
                    <div class="flex items-center gap-3 pb-3 border-b border-border/60">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                            <CalendarDays class="h-5 w-5" />
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-foreground">
                                Itinerary & Boarding Details
                            </h2>
                            <p class="text-xs text-muted-foreground">Scheduled tour date and pickup point</p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <!-- Tour Date -->
                            <div class="rounded-2xl border bg-background/60 p-4 backdrop-blur space-y-1">
                                <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                                    <CalendarDays class="h-3.5 w-3.5 text-primary" />
                                    Tour Date
                                </span>
                                <p class="text-base font-bold text-foreground">
                                    {{ formatDate(booking.tour_date?.tour_date ?? null) }}
                                </p>
                            </div>

                            <!-- Boat Time / Departure -->
                            <div class="rounded-2xl border bg-background/60 p-4 backdrop-blur space-y-1">
                                <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
                                    <Clock class="h-3.5 w-3.5 text-primary" />
                                    Departure Time(s)
                                </span>
                                <div class="flex flex-wrap gap-1.5 pt-0.5">
                                    <span
                                        v-for="t in booking.tour_date?.times ?? []"
                                        :key="t.id"
                                        class="rounded-md bg-muted px-2 py-0.5 text-xs font-bold text-foreground"
                                    >
                                        {{ formatTime(t.time) }}
                                    </span>
                                    <span v-if="!booking.tour_date?.times?.length" class="text-xs text-muted-foreground">
                                        Standard departure
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Pickup Schedule -->
                        <div class="rounded-2xl border bg-background/60 p-4.5 backdrop-blur space-y-2">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                    <Bus class="h-3.5 w-3.5 text-primary" />
                                    <span>Pickup Location & Time</span>
                                </div>
                                <span class="rounded-full bg-primary/10 px-3 py-0.5 text-xs font-bold text-primary">
                                    {{ formatTime(booking.pickup_schedule?.pickup_time ?? null) }}
                                </span>
                            </div>

                            <p class="text-base font-bold text-foreground">
                                {{ booking.pickup_schedule?.pickup_location?.name ?? 'Designated Location' }}
                            </p>

                            <p
                                v-if="booking.pickup_schedule?.pickup_location?.address"
                                class="text-xs text-muted-foreground"
                            >
                                {{ booking.pickup_schedule.pickup_location.address }}
                            </p>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Right Column: Package Details & Payment (5 cols) -->
            <div class="space-y-6 lg:col-span-5">
                <!-- Tour Package Details Card matching bookings/Show.vue -->
                <section class="rounded-3xl border bg-card/60 p-6 backdrop-blur-md shadow-sm sm:p-7 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-border/60">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                <MapPin class="h-5 w-5" />
                            </div>
                            <div>
                                <h2 class="text-base font-bold text-foreground">
                                    Package Details
                                </h2>
                                <p class="text-xs text-muted-foreground">Destination and rate summary</p>
                            </div>
                        </div>
                    </div>

                    <!-- Package Image Carousel -->
                    <div>
                        <Carousel v-if="packageImageUrls.length" class="w-full">
                            <CarouselContent>
                                <CarouselItem
                                    v-for="(url, imageIndex) in packageImageUrls"
                                    :key="imageIndex"
                                >
                                    <div class="group relative aspect-video overflow-hidden rounded-2xl border bg-muted/40 shadow-xs">
                                        <img
                                            :src="url"
                                            :alt="booking.package?.package_name"
                                            class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                                        />

                                        <div class="absolute inset-0 flex items-center justify-center bg-black/20 opacity-0 transition duration-200 group-hover:opacity-100">
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-1.5 rounded-full bg-background/90 px-3.5 py-1.5 text-xs font-semibold text-foreground backdrop-blur shadow-xs transition hover:bg-background"
                                                @click="openImage(url)"
                                            >
                                                <ExternalLink class="h-3.5 w-3.5 text-primary" />
                                                View Full Size
                                            </button>
                                        </div>

                                        <span class="absolute bottom-3 right-3 rounded-full bg-black/60 px-2.5 py-1 text-xs font-medium text-white backdrop-blur">
                                            {{ imageIndex + 1 }} / {{ packageImageUrls.length }}
                                        </span>
                                    </div>
                                </CarouselItem>
                            </CarouselContent>

                            <template v-if="packageImageUrls.length > 1">
                                <CarouselPrevious class="left-3 rounded-xl border-border/80 bg-background/80 text-foreground backdrop-blur" />
                                <CarouselNext class="right-3 rounded-xl border-border/80 bg-background/80 text-foreground backdrop-blur" />
                            </template>
                        </Carousel>

                        <!-- No Image Placeholder -->
                        <div
                            v-else
                            class="flex aspect-video flex-col items-center justify-center rounded-2xl border border-dashed bg-muted/20 text-center text-muted-foreground"
                        >
                            <ImageOff class="h-8 w-8 text-muted-foreground/60" />
                            <p class="mt-2 text-xs font-medium">No package images available</p>
                        </div>
                    </div>

                    <!-- Title & Destination -->
                    <div>
                        <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Destination Name
                        </span>
                        <h3 class="mt-1 text-lg font-bold text-foreground">
                            {{ booking.package?.package_name ?? 'Custom Tour Package' }}
                        </h3>
                        <p class="flex items-center gap-1.5 text-xs text-muted-foreground mt-0.5">
                            <MapPin class="h-3.5 w-3.5 text-primary shrink-0" />
                            <span>{{ booking.package?.destination ?? '—' }}</span>
                        </p>
                    </div>

                    <p
                        v-if="booking.package?.description"
                        class="whitespace-pre-line text-xs leading-relaxed text-muted-foreground line-clamp-3"
                    >
                        {{ booking.package.description }}
                    </p>

                    <!-- Pricing Info Card -->
                    <div class="rounded-2xl border bg-muted/40 p-4 backdrop-blur">
                        <div class="flex items-center justify-between">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                                Price per Guest
                            </p>
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize"
                                :class="booking.package?.status === 'active' ? 'bg-primary/10 text-primary' : 'bg-muted text-muted-foreground'"
                            >
                                {{ booking.package?.status ?? 'Active' }}
                            </span>
                        </div>
                        <p class="mt-1 text-2xl font-extrabold tracking-tight text-foreground">
                            ₱{{ formatPrice(booking.package?.price ?? 0) }}
                        </p>
                        <p class="mt-1 text-[11px] text-muted-foreground">
                            Expected Total: <strong>₱{{ formatPrice(Number(booking.package?.price ?? 0) * booking.number_of_guests) }}</strong>
                        </p>
                    </div>
                </section>

                <!-- Payment Status & Proof Card -->
                <section class="rounded-3xl border bg-card/60 p-6 backdrop-blur-md shadow-sm sm:p-7">
                    <div class="flex items-center gap-3 pb-4 border-b border-border/60">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                            <Receipt class="h-5 w-5" />
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-foreground">
                                Payment & Settlement
                            </h2>
                            <p class="text-xs text-muted-foreground">Payment status for this booking</p>
                        </div>
                    </div>

                    <div v-if="payment" class="mt-5 space-y-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Total Paid Amount</p>
                                <p class="text-2xl font-extrabold tracking-tight text-foreground">
                                    ₱{{ formatPrice(payment.amount) }}
                                </p>
                            </div>

                            <span
                                class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold"
                                :class="paymentStatusBadgeClass(payment.payment_status)"
                            >
                                {{ statusLabel(payment.payment_status) }}
                            </span>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2 text-xs pt-2">
                            <div class="rounded-xl border bg-background/60 p-3 space-y-0.5">
                                <span class="text-muted-foreground">Payment Method</span>
                                <p class="font-bold text-foreground">{{ payment.payment_method || 'Online Channel' }}</p>
                            </div>

                            <div class="rounded-xl border bg-background/60 p-3 space-y-0.5">
                                <span class="text-muted-foreground">Reference Number</span>
                                <p class="font-mono font-bold text-foreground truncate">
                                    {{ payment.transaction_reference || 'N/A' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div v-else class="mt-4 rounded-2xl border border-dashed bg-muted/20 p-6 text-center text-xs text-muted-foreground">
                        No payment record submitted yet.
                    </div>
                </section>
            </div>
        </div>

    </div>
</template>