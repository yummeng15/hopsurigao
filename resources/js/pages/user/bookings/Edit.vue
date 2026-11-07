
<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import {
    ArrowLeft,
    CalendarDays,
    CreditCard,
    LockKeyhole,
    MapPin,
    Save,
    Users,
} from '@lucide/vue';
import PickupMap from '@/components/maps/PickupMap.vue';
import { Button } from '@/components/ui/button';
import { index, show, update } from '@/routes/user/bookings';

type PickupLocation = {
    name: string;
    latitude: number | string | null;
    longitude: number | string | null;
};

type PickupSchedule = {
    id: number;
    pickup_time: string | null;
    pickup_location: PickupLocation | null;
};

type Booking = {
    id: number;
    pickup_schedule_id: number;
    pickup_latitude: number | string | null;
    pickup_longitude: number | string | null;
    number_of_guests: number;
    phone_number: string;
    nationality: string;
    special_request: string | null;
    booked_unit_price: number | string | null;
    booked_total: number | string | null;
    price_snapshot_source: string | null;
    has_pending_checkout: boolean;
};

type TourDate = {
    id: number;
    tour_date: string;
    package: {
        id: number;
        package_name: string;
        destination: string;
    };
    pickup_schedules: PickupSchedule[];
};

const props = defineProps<{
    booking: Booking;
    tourDate: TourDate;
}>();

const form = useForm({
    pickup_schedule_id: props.booking.pickup_schedule_id,
    pickup_latitude: props.booking.pickup_latitude == null
        ? null
        : Number(props.booking.pickup_latitude),
    pickup_longitude: props.booking.pickup_longitude == null
        ? null
        : Number(props.booking.pickup_longitude),
    phone_number: props.booking.phone_number,
    nationality: props.booking.nationality,
    special_request: props.booking.special_request ?? '',
});

const selectedPickup = computed(() =>
    props.tourDate.pickup_schedules.find(
        (schedule) => schedule.id === Number(form.pickup_schedule_id),
    )?.pickup_location ?? null,
);

// Selecting a new pickup schedule also moves the suggested map pin.
// The Tourist can still adjust it manually afterward.
watch(() => form.pickup_schedule_id, () => {
    const location = selectedPickup.value;
    const lat = location?.latitude == null ? NaN : Number(location.latitude);
    const lng = location?.longitude == null ? NaN : Number(location.longitude);

    if (Number.isFinite(lat) && Number.isFinite(lng)) {
        form.pickup_latitude = lat;
        form.pickup_longitude = lng;
    }
});

function onPick(point: { latitude: number; longitude: number }): void {
    form.pickup_latitude = point.latitude;
    form.pickup_longitude = point.longitude;
}

function dateLabel(date: string): string {
    const parsed = new Date(`${date.slice(0, 10)}T00:00:00`);
    return Number.isNaN(parsed.getTime())
        ? date
        : parsed.toLocaleDateString('en-US', {
            year: 'numeric', month: 'short', day: 'numeric',
        });
}

function timeLabel(value: string | null): string {
    if (!value) return 'Time not set';
    const match = value.match(/^(\d{1,2}):(\d{2})/);
    if (!match) return value;
    const hours = Number(match[1]);
    const minutes = Number(match[2]);
    if (hours > 23 || minutes > 59) return value;
    return `${hours % 12 || 12}:${String(minutes).padStart(2, '0')} ${hours >= 12 ? 'PM' : 'AM'}`;
}

function money(value: number | string | null): string {
    if (value === null || value === '') return 'Needs review';
    const amount = Number(value);
    return Number.isFinite(amount) && amount > 0
        ? `₱${amount.toLocaleString('en-PH', {
            minimumFractionDigits: 2, maximumFractionDigits: 2,
        })}`
        : 'Needs review';
}

const priceNeedsReview = computed(() =>
    props.booking.price_snapshot_source !== 'at_booking' ||
    props.booking.booked_total === null,
);

function submit(): void {
    if (form.processing) return;
    form.patch(update(props.booking.id).url, {
        preserveScroll: true,
    });
}

defineOptions({
    layout: {
        breadcrumbs: [
            { href: index(), title: 'Bookings' },
            { href: '#', title: 'Edit Booking' },
        ],
    },
});
</script>

<template>
    <Head :title="`Edit Booking #${booking.id}`" />

    <div class="mx-auto max-w-4xl space-y-6 px-4 py-6 pb-20 md:px-8">
        <div>
            <Link :href="show(booking.id)" class="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground">
                <ArrowLeft class="h-4 w-4" /> Back to Booking Details
            </Link>
            <h1 class="mt-4 text-2xl font-bold tracking-tight md:text-3xl">Edit Booking #{{ booking.id }}</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                You can update your pickup and contact details while your booking is pending.
            </p>
        </div>

        <!-- The original price and number of guests cannot be edited here. -->
        <section class="rounded-2xl border bg-card p-5 shadow-sm md:p-6">
            <div class="flex items-center gap-2 text-sm font-semibold">
                <LockKeyhole class="h-4 w-4 text-primary" /> Locked Booking Information
            </div>
            <h2 class="mt-3 text-lg font-bold">{{ tourDate.package.package_name }}</h2>
            <p class="mt-1 text-xs text-muted-foreground">{{ tourDate.package.destination }}</p>

            <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-xl bg-muted/30 p-3">
                    <p class="flex items-center gap-1 text-xs text-muted-foreground"><CalendarDays class="h-3.5 w-3.5" /> Tour Date</p>
                    <p class="mt-1 text-sm font-semibold">{{ dateLabel(tourDate.tour_date) }}</p>
                </div>
                <div class="rounded-xl bg-muted/30 p-3">
                    <p class="flex items-center gap-1 text-xs text-muted-foreground"><Users class="h-3.5 w-3.5" /> Guests</p>
                    <p class="mt-1 text-sm font-semibold">{{ booking.number_of_guests }}</p>
                </div>
                <div class="rounded-xl bg-muted/30 p-3">
                    <p class="text-xs text-muted-foreground">Booked Rate / Guest</p>
                    <p class="mt-1 text-sm font-semibold">{{ money(booking.booked_unit_price) }}</p>
                </div>
                <div class="rounded-xl bg-muted/30 p-3">
                    <p class="flex items-center gap-1 text-xs text-muted-foreground"><CreditCard class="h-3.5 w-3.5" /> Agreed Total</p>
                    <p class="mt-1 text-sm font-semibold">{{ money(booking.booked_total) }}</p>
                </div>
            </div>
            <p class="mt-3 text-xs text-muted-foreground">
                Tour date, number of guests, and agreed price cannot be changed from this screen.
            </p>
            <p v-if="priceNeedsReview" class="mt-3 rounded-lg border border-amber-500/30 p-3 text-xs text-amber-700 dark:text-amber-400">
                The original price on this older booking needs verification against the receipt.
            </p>
        </section>

        <div v-if="booking.has_pending_checkout" class="rounded-xl border border-sky-500/30 bg-sky-500/5 p-4 text-sm text-sky-800 dark:text-sky-200">
            A PayMongo checkout is currently pending. You can edit contact and pickup information,
            but your selected payment amount and checkout session will remain unchanged.
        </div>

        <form class="overflow-hidden rounded-2xl border bg-card shadow-sm" @submit.prevent="submit">
            <div class="space-y-6 p-5 md:p-6">
                <div>
                    <h2 class="text-lg font-bold">Pickup & Contact Details</h2>
                    <p class="text-xs text-muted-foreground">Only these details will be updated.</p>
                </div>

                <div>
                    <label for="pickup-schedule" class="mb-2 block text-sm font-semibold">Pickup Location and Time</label>
                    <select
                        id="pickup-schedule"
                        v-model.number="form.pickup_schedule_id"
                        class="w-full rounded-xl border bg-background px-4 py-3 text-sm"
                        :class="form.errors.pickup_schedule_id ? 'border-destructive' : ''"
                        required
                    >
                        <option v-for="schedule in tourDate.pickup_schedules" :key="schedule.id" :value="schedule.id">
                            {{ schedule.pickup_location?.name ?? 'Pickup Location' }} — {{ timeLabel(schedule.pickup_time) }}
                        </option>
                    </select>
                    <p v-if="form.errors.pickup_schedule_id" class="mt-1 text-xs text-destructive">{{ form.errors.pickup_schedule_id }}</p>

                    <div class="mt-4">
                        <PickupMap
                            interactive
                            :latitude="form.pickup_latitude ?? selectedPickup?.latitude"
                            :longitude="form.pickup_longitude ?? selectedPickup?.longitude"
                            :label="selectedPickup?.name ?? 'Your pickup spot'"
                            hint="Tap or drag the pin to update your pickup location."
                            @pick="onPick"
                        />
                    </div>
                    <p v-if="form.errors.pickup_latitude || form.errors.pickup_longitude" class="mt-2 text-xs text-destructive">
                        {{ form.errors.pickup_latitude || form.errors.pickup_longitude }}
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="phone-number" class="mb-2 block text-sm font-semibold">Phone Number</label>
                        <input
                            id="phone-number"
                            v-model="form.phone_number"
                            type="tel"
                            autocomplete="tel"
                            maxlength="255"
                            required
                            class="w-full rounded-xl border bg-background px-4 py-3 text-sm"
                        />
                        <p v-if="form.errors.phone_number" class="mt-1 text-xs text-destructive">{{ form.errors.phone_number }}</p>
                    </div>
                    <div>
                        <label for="nationality" class="mb-2 block text-sm font-semibold">Nationality</label>
                        <input
                            id="nationality"
                            v-model="form.nationality"
                            type="text"
                            maxlength="255"
                            required
                            class="w-full rounded-xl border bg-background px-4 py-3 text-sm"
                        />
                        <p v-if="form.errors.nationality" class="mt-1 text-xs text-destructive">{{ form.errors.nationality }}</p>
                    </div>
                </div>

                <div>
                    <label for="special-request" class="mb-2 block text-sm font-semibold">Special Request (Optional)</label>
                    <textarea
                        id="special-request"
                        v-model="form.special_request"
                        rows="3"
                        maxlength="2000"
                        class="w-full rounded-xl border bg-background px-4 py-3 text-sm"
                        placeholder="Dietary needs or other pickup notes"
                    />
                    <p v-if="form.errors.special_request" class="mt-1 text-xs text-destructive">{{ form.errors.special_request }}</p>
                </div>
            </div>

            <div class="flex flex-wrap justify-end gap-3 border-t bg-muted/20 p-5">
                <Button as-child type="button" variant="outline" :disabled="form.processing">
                    <Link :href="show(booking.id)">Cancel</Link>
                </Button>
                <Button type="submit" :disabled="form.processing">
                    <Save class="mr-2 h-4 w-4" />
                    {{ form.processing ? 'Saving...' : 'Save Changes' }}
                </Button>
            </div>
        </form>
    </div>
</template>
