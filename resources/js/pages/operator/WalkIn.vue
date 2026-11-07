
<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import {
    ArrowLeft,
    Banknote,
    CalendarDays,
    Users,
} from '@lucide/vue';

type PackageOption = {
    id: number;
    name: string;
    destination: string;
    price: string;
};

type PickupOption = {
    id: number;
    name: string;
    time: string;
};

type DateOption = {
    id: number;
    date: string;
    remaining: number;
    pickups: PickupOption[];
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
    cash_received: false,
});

const dates = ref<DateOption[]>([]);
const loading = ref(false);
const error = ref('');

const fieldClass =
    'mt-1 block w-full rounded-lg border border-border bg-card p-2 text-sm text-foreground placeholder:text-foreground/50 disabled:opacity-60';

const selectedPackage = computed(() =>
    props.packages.find(
        p => String(p.id) === form.package_id
    )
);

const selectedDate = computed(() =>
    dates.value.find(
        d => String(d.id) === form.tour_date_id
    )
);

const total = computed(() =>
    Number(selectedPackage.value?.price ?? 0)
    * Number(form.number_of_guests)
);

const pesos = (value: number) =>
    new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(value);

let requestSerial = 0;

watch(() => form.package_id, async (id) => {
    const serial = ++requestSerial;

    dates.value = [];
    form.tour_date_id = '';
    form.pickup_schedule_id = '';
    error.value = '';

    if (!id) return;

    loading.value = true;

    try {
        const response = await fetch(
            `/operator/walk-ins/packages/${encodeURIComponent(id)}/dates`,
            {
                headers: {
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
            }
        );

        if (!response.ok) {
            throw new Error(
                'Unable to load the available tour dates.'
            );
        }

        const data = await response.json() as {
            dates: DateOption[];
        };

        if (serial === requestSerial) {
            dates.value = data.dates;
        }
    } catch (cause) {
        if (serial === requestSerial) {
            error.value = cause instanceof Error
                ? cause.message
                : 'Unable to load dates.';
        }
    } finally {
        if (serial === requestSerial) {
            loading.value = false;
        }
    }
});

watch(
    () => form.tour_date_id,
    () => {
        form.pickup_schedule_id = '';
    }
);

function submit(): void {
    if (form.processing) return;

    form.transform((data) => ({
        customer_name: data.customer_name,
        email: data.email,
        phone_number: data.phone_number,
        nationality: data.nationality,
        tour_date_id: Number(data.tour_date_id),
        pickup_schedule_id: Number(data.pickup_schedule_id),
        number_of_guests: Number(data.number_of_guests),
        special_request: data.special_request,
        cash_received: data.cash_received,
    })).post('/operator/walk-ins', {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="New Walk-in Booking" />

    <main class="mx-auto max-w-3xl space-y-5 px-4 py-6 text-foreground sm:px-6">
        <Link
            href="/operator/bookings"
            class="inline-flex items-center gap-2 text-sm font-medium text-primary"
        >
            <ArrowLeft class="size-4" />
            My Bookings
        </Link>

        <header>
            <h1 class="text-3xl font-bold text-foreground">
                Create Walk-in Booking
            </h1>

            <p class="mt-2 text-sm text-foreground/70">
                For guests paying the
                <strong class="text-foreground">full amount in cash</strong>
                at the counter. Online GCash payments
                stay on PayMongo.
            </p>
        </header>

        <form
            class="space-y-5 rounded-3xl border border-border bg-card p-5 shadow-sm"
            @submit.prevent="submit"
        >
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="text-sm font-medium text-foreground">
                    Guest name *

                    <input
                        v-model="form.customer_name"
                        required
                        maxlength="255"
                        :class="fieldClass"
                    />
                </label>

                <label class="text-sm font-medium text-foreground">
                    Phone number *

                    <input
                        v-model="form.phone_number"
                        required
                        maxlength="30"
                        :class="fieldClass"
                    />
                </label>

                <label class="text-sm font-medium text-foreground">
                    Guest email (optional)

                    <input
                        v-model="form.email"
                        type="email"
                        maxlength="255"
                        :class="fieldClass"
                    />
                </label>

                <label class="text-sm font-medium text-foreground">
                    Nationality *

                    <input
                        v-model="form.nationality"
                        required
                        maxlength="100"
                        :class="fieldClass"
                    />
                </label>
            </div>

            <label class="block text-sm font-medium text-foreground">
                My active tour package *

                <select
                    v-model="form.package_id"
                    required
                    :class="fieldClass"
                >
                    <option value="" disabled>
                        Choose a package
                    </option>

                    <option
                        v-for="p in packages"
                        :key="p.id"
                        :value="String(p.id)"
                    >
                        {{ p.name }} —
                        {{ pesos(Number(p.price)) }} per guest
                    </option>
                </select>
            </label>

            <div class="grid gap-4 sm:grid-cols-2">
                <label class="text-sm font-medium text-foreground">
                    Available tour date *

                    <select
                        v-model="form.tour_date_id"
                        required
                        :disabled="loading || !form.package_id"
                        :class="fieldClass"
                    >
                        <option value="" disabled>
                            {{ loading ? 'Loading dates…' : 'Choose a date' }}
                        </option>

                        <option
                            v-for="date in dates"
                            :key="date.id"
                            :value="String(date.id)"
                        >
                            {{ date.date }}
                            ({{ date.remaining }} seats available)
                        </option>
                    </select>
                </label>

                <label class="text-sm font-medium text-foreground">
                    Pickup location/time *

                    <select
                        v-model="form.pickup_schedule_id"
                        required
                        :disabled="!selectedDate"
                        :class="fieldClass"
                    >
                        <option value="" disabled>
                            Choose pickup
                        </option>

                        <option
                            v-for="pickup in selectedDate?.pickups ?? []"
                            :key="pickup.id"
                            :value="String(pickup.id)"
                        >
                            {{ pickup.name }} — {{ pickup.time }}
                        </option>
                    </select>
                </label>
            </div>

            <label class="block max-w-xs text-sm font-medium text-foreground">
                Number of guests *

                <input
                    v-model.number="form.number_of_guests"
                    type="number"
                    min="1"
                    :max="selectedDate?.remaining ?? 10000"
                    required
                    :class="fieldClass"
                />
            </label>

            <label class="block text-sm font-medium text-foreground">
                Special request (optional)

                <textarea
                    v-model="form.special_request"
                    maxlength="2000"
                    rows="3"
                    :class="fieldClass"
                />
            </label>

            <div class="rounded-xl border border-primary/30 bg-primary/10 p-4 text-foreground">
                <div class="flex items-center gap-2 text-primary">
                    <Banknote class="size-5" />
                    <strong>Cash collected</strong>
                </div>

                <p class="mt-2 text-2xl font-bold text-foreground">
                    {{ pesos(total) }}
                </p>

                <p class="text-xs text-foreground/70">
                    The server recalculates this total
                    from the saved package price and guest count.
                </p>
            </div>

            <label class="flex items-start gap-3 rounded-lg border border-border bg-card p-3 text-sm text-foreground">
                <input
                    v-model="form.cash_received"
                    type="checkbox"
                    required
                    class="mt-1"
                />

                <span>
                    I confirm that I physically received the
                    <strong>full cash payment</strong>
                    for this walk-in booking.
                </span>
            </label>

            <div
                v-if="error || Object.keys(form.errors).length"
                role="alert"
                class="space-y-1 rounded-lg border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive"
            >
                <p v-if="error">{{ error }}</p>

                <p
                    v-for="(message, key) in form.errors"
                    :key="key"
                >
                    {{ message }}
                </p>
            </div>

            <button
                type="submit"
                :disabled="
                    form.processing
                    || !form.cash_received
                    || !selectedDate
                    || !form.pickup_schedule_id
                "
                class="w-full rounded-xl bg-primary px-4 py-3 font-semibold text-primary-foreground disabled:opacity-50"
            >
                {{
                    form.processing
                        ? 'Saving…'
                        : 'Confirm walk-in and record cash'
                }}
            </button>

            <p
                v-if="!packages.length"
                class="text-sm font-medium text-amber-700 dark:text-amber-300"
            >
                No active operator-owned packages are available.
                Configure and publish a package first.
            </p>
        </form>

        <p class="text-xs text-foreground/70">
            <CalendarDays class="inline size-3" />
            Travel closures, remaining capacity, and
            maintenance are checked again when you save.

            <Users class="inline size-3" />
            Duplicate email bookings for the same date are blocked.
        </p>
    </main>
</template>
