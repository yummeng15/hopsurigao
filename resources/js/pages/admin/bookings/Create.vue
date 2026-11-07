<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import InputError from '@/components/InputError.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Spinner } from '@/components/ui/spinner';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { create, index, store } from '@/routes/admin/bookings';

type PickupSchedule = {
    id: number;
    pickup_time: string | null;
    location_name: string;
};

type TourDateOption = {
    id: number;
    tour_date: string;
    remaining_capacity: number;
    booking_closed?: boolean;
    booking_closed_reason?: string | null;
    pickup_schedules: PickupSchedule[];
};

type PackageOption = {
    id: number;
    package_name: string;
    destination: string | null;
    price: number;
    under_maintenance?: boolean;
    tour_dates: TourDateOption[];
};

const props = defineProps<{
    packages: PackageOption[];
}>();

const form = useForm({
    customer_name: '',
    email: '',
    phone_number: '',
    nationality: 'Filipino',
    package_id: '',
    tour_date_id: '',
    pickup_schedule_id: '',
    number_of_guests: 1,
    special_request: '',
});

const tourDates = ref<TourDateOption[]>([]);
const loadingDates = ref(false);
const datesError = ref('');

const selectedPackage = computed(
    () => props.packages.find((item) => String(item.id) === form.package_id) ?? null,
);

const selectedDate = computed(
    () => tourDates.value.find((item) => String(item.id) === form.tour_date_id) ?? null,
);

const total = computed(() => {
    if (!selectedPackage.value) {
        return 0;
    }

    return selectedPackage.value.price * Number(form.number_of_guests || 0);
});

async function loadTourDates(packageId: string): Promise<void> {
    if (!packageId) {
        tourDates.value = [];

        return;
    }

    loadingDates.value = true;
    datesError.value = '';

    try {
        const response = await fetch(`/admin/bookings/onsite-dates/${packageId}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error('Could not load tour dates.');
        }

        const payload = (await response.json()) as { tour_dates: TourDateOption[] };
        tourDates.value = payload.tour_dates ?? [];
    } catch {
        tourDates.value = [];
        datesError.value = 'Could not load tour dates. Try again.';
    } finally {
        loadingDates.value = false;
    }
}

watch(
    () => form.package_id,
    (packageId) => {
        form.tour_date_id = '';
        form.pickup_schedule_id = '';
        void loadTourDates(packageId);
    },
);

watch(
    () => form.tour_date_id,
    () => {
        form.pickup_schedule_id = '';
    },
);

function formatPrice(amount: number): string {
    return amount.toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function formatDate(value: string): string {
    const date = new Date(`${value}T00:00:00`);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleDateString('en-PH', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function submit(): void {
    form
        .transform((data) => ({
            customer_name: data.customer_name,
            email: data.email,
            phone_number: data.phone_number,
            nationality: data.nationality,
            tour_date_id: Number(data.tour_date_id),
            pickup_schedule_id: Number(data.pickup_schedule_id),
            number_of_guests: Number(data.number_of_guests),
            special_request: data.special_request,
        }))
        .post(store.url(), {
            preserveScroll: true,
        });
}

defineOptions({
    layout: {
        breadcrumbs: [
            { href: index(), title: 'Bookings' },
            { href: create(), title: 'On-site booking' },
        ],
    },
});
</script>

<template>
    <Head title="On-site booking" />

    <div class="px-4 py-6 pb-28 md:pb-6">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <Heading
                title="On-site booking"
                description="Book a guest who is here and paying cash. Online GCash payments stay on PayMongo."
            />

            <Button as-child variant="outline" class="shrink-0">
                <Link :href="index.url()">Back to bookings</Link>
            </Button>
        </div>

        <form
            v-if="packages.length"
            class="mx-auto max-w-3xl space-y-6 rounded-3xl border bg-card/60 p-6 shadow-sm"
            @submit.prevent="submit"
        >
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="customer_name">Guest name</Label>
                    <Input id="customer_name" v-model="form.customer_name" required placeholder="Full name" />
                    <InputError :message="form.errors.customer_name" />
                </div>

                <div class="grid gap-2">
                    <Label for="phone_number">Phone number</Label>
                    <Input id="phone_number" v-model="form.phone_number" required placeholder="09xxxxxxxxx" />
                    <InputError :message="form.errors.phone_number" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">Email</Label>
                    <Input id="email" v-model="form.email" type="email" placeholder="Optional" />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="nationality">Nationality</Label>
                    <Input id="nationality" v-model="form.nationality" required />
                    <InputError :message="form.errors.nationality" />
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2 sm:col-span-2">
                    <Label>Package</Label>
                    <Select v-model="form.package_id">
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="Choose a package" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="item in packages" :key="item.id" :value="String(item.id)">
                                {{ item.package_name }} · ₱{{ formatPrice(item.price) }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="grid gap-2">
                    <Label>Tour date</Label>
                    <Select
                        v-model="form.tour_date_id"
                        :disabled="!selectedPackage || loadingDates || tourDates.length === 0"
                    >
                        <SelectTrigger class="w-full">
                            <SelectValue
                                :placeholder="
                                    loadingDates
                                        ? 'Loading dates...'
                                        : selectedPackage
                                          ? 'Choose a date'
                                          : 'Choose a package first'
                                "
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="date in tourDates"
                                :key="date.id"
                                :value="String(date.id)"
                                :disabled="date.booking_closed"
                            >
                                {{ formatDate(date.tour_date) }} ·
                                {{ date.booking_closed ? 'Closed — weather' : `${date.remaining_capacity} seats left` }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="datesError" class="text-xs text-destructive">{{ datesError }}</p>
                    <p
                        v-else-if="selectedPackage && !loadingDates && tourDates.length === 0"
                        class="text-xs text-muted-foreground"
                    >
                        No open dates with seats for this package in the next 30 days.
                    </p>
                    <InputError :message="form.errors.tour_date_id" />
                </div>

                <div class="grid gap-2">
                    <Label>Pickup</Label>
                    <Select v-model="form.pickup_schedule_id" :disabled="!selectedDate">
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="Choose a pickup" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="pickup in selectedDate?.pickup_schedules ?? []"
                                :key="pickup.id"
                                :value="String(pickup.id)"
                            >
                                {{ pickup.location_name }} · {{ pickup.pickup_time }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.pickup_schedule_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="number_of_guests">Guests</Label>
                    <Input
                        id="number_of_guests"
                        v-model.number="form.number_of_guests"
                        type="number"
                        min="1"
                        :max="selectedDate?.remaining_capacity"
                        required
                    />
                    <InputError :message="form.errors.number_of_guests" />
                </div>

                <div class="grid content-end gap-2">
                    <p class="text-sm text-muted-foreground">Cash to collect</p>
                    <p class="text-2xl font-bold">₱{{ formatPrice(total) }}</p>
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="special_request">Notes</Label>
                <Textarea id="special_request" v-model="form.special_request" placeholder="Optional" />
                <InputError :message="form.errors.special_request" />
            </div>

            <!-- Desktop submit -->
            <div class="hidden justify-end md:flex">
                <Button type="submit" :disabled="form.processing || !form.tour_date_id || !form.pickup_schedule_id">
                    <Spinner v-if="form.processing" class="mr-2" />
                    Save on-site booking
                </Button>
            </div>
        </form>

        <!-- Mobile: keep Save above the bottom tab bar -->
        <div
            v-if="packages.length"
            class="fixed inset-x-0 z-30 border-t border-border bg-background/95 px-4 py-3 backdrop-blur-md md:hidden "
            style="bottom: calc(4.75rem + env(safe-area-inset-bottom))"
        >
            <Button
                type="button"
                class="w-full"
                :disabled="form.processing || !form.tour_date_id || !form.pickup_schedule_id"
                @click="submit"
            >
                <Spinner v-if="form.processing" class="mr-2" />
                Save on-site booking · ₱{{ formatPrice(total) }}
            </Button>
        </div>

        <div v-else class="rounded-3xl border border-dashed p-8 text-center text-sm text-muted-foreground">
            No active packages with upcoming tour dates.
        </div>
    </div>
</template>
