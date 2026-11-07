<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { ArrowLeft, CalendarDays, Plus, Save, Trash2 } from '@lucide/vue';

type TimeInput = {
    time: string;
    description: string;
};

type PickupInput = {
    pickup_location_name: string;
    pickup_time: string;
};

type PickupDisplay = PickupInput & {
    pickup_location_id: number;
};

type DateRecord = {
    id: number;
    tour_date: string;
    capacity: number;
    reserved_guests: number;
    has_bookings: boolean;
    travel_closed: boolean;
    times: TimeInput[];
    pickup_schedules: PickupDisplay[];
};

const props = defineProps<{
    package: {
        id: number;
        name: string;
        status: string;
    };
    dates: DateRecord[];
    locations: {
        id: number;
        name: string;
    }[];
    today: string;
}>();

const selected = ref<DateRecord | null>(null);
const showingCreate = ref(false);

const createForm = useForm({
    tour_date: '',
    capacity: 20,
    times: [
        {
            time: '08:00',
            description: 'Departure',
        },
    ] as TimeInput[],
    pickup_schedules: [
        {
            pickup_location_name: '',
            pickup_time: '07:00',
        },
    ] as PickupInput[],
});

const editForm = useForm({
    capacity: 1,
    times: [] as TimeInput[],
    pickup_schedules: [] as PickupInput[],
});

const baseUrl = computed(
    () => `/operator/packages/${props.package.id}/schedules`,
);

function openCreate(): void {
    selected.value = null;
    showingCreate.value = true;
    createForm.clearErrors();
}

function openEdit(date: DateRecord): void {
    showingCreate.value = false;
    selected.value = date;
    editForm.clearErrors();

    editForm.capacity = date.capacity;

    editForm.times = date.times.map((time) => ({
        ...time,
    }));

    editForm.pickup_schedules = date.pickup_schedules.map((pickup) => ({
        pickup_location_name: pickup.pickup_location_name,
        pickup_time: pickup.pickup_time,
    }));
}

function addTime(form: typeof createForm | typeof editForm): void {
    if (form.times.length < 8) {
        form.times.push({
            time: '08:00',
            description: 'Departure',
        });
    }
}

function addPickup(form: typeof createForm | typeof editForm): void {
    if (form.pickup_schedules.length < 15) {
        form.pickup_schedules.push({
            pickup_location_name: '',
            pickup_time: '07:00',
        });
    }
}

function submitCreate(): void {
    createForm.post(baseUrl.value, {
        preserveScroll: true,
        onSuccess: () => {
            showingCreate.value = false;
            createForm.reset();
        },
    });
}

function submitEdit(): void {
    if (!selected.value) {
        return;
    }

    // Existing bookings must not have their
    // departure or pickup schedules changed.
    if (selected.value.has_bookings) {
        editForm.transform((data) => ({
            capacity: data.capacity,
        }));
    } else {
        editForm.transform((data) => data);
    }

    editForm.put(`${baseUrl.value}/${selected.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            selected.value = null;
        },
    });
}
</script>

<template>
    <Head :title="`Schedules - ${props.package.name}`" />

    <main class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6">
        <!-- PAGE HEADER -->
        <header class="space-y-3">
            <Link
                href="/operator/packages"
                class="inline-flex items-center gap-2 text-sm font-medium text-primary hover:underline"
            >
                <ArrowLeft class="size-4" />
                My Tour Packages
            </Link>

            <h1 class="text-3xl font-bold">Tour Schedules</h1>

            <p class="text-sm text-slate-500 dark:text-slate-400">
                {{ props.package.name }}
                · Package status: {{ props.package.status }}
            </p>

            <p
                v-if="props.package.status !== 'active'"
                class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/30 dark:text-amber-200"
            >
                This package is inactive. Adding schedules does not publish it
                automatically.
            </p>
        </header>

        <!-- SCHEDULE HEADER -->
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-xl font-semibold">
                Scheduled dates ({{ props.dates.length }})
            </h2>

            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground hover:opacity-90"
                @click="openCreate"
            >
                <Plus class="size-4" />
                Add tour date
            </button>
        </div>

        <!-- EMPTY STATE -->
        <section
            v-if="props.dates.length === 0"
            class="rounded-xl border border-dashed p-8 text-center text-sm text-slate-500"
        >
            No dates have been configured for this package yet.
        </section>

        <!-- EXISTING SCHEDULES -->
        <section v-else class="grid gap-3 md:grid-cols-2">
            <article
                v-for="date in props.dates"
                :key="date.id"
                class="space-y-3 rounded-xl border bg-white p-4 shadow-sm dark:bg-slate-900"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="flex items-center gap-2 font-bold">
                        <CalendarDays class="size-4" />
                        {{ date.tour_date }}
                    </h3>

                    <span
                        v-if="date.travel_closed"
                        class="rounded-full bg-red-100 px-2 py-1 text-xs font-medium text-red-800"
                    >
                        Travel closed
                    </span>
                </div>

                <p class="text-sm">
                    Capacity:
                    <strong>{{ date.capacity }}</strong>
                    · Reserved:
                    <strong>{{ date.reserved_guests }}</strong>
                </p>

                <p class="text-xs text-slate-500">
                    {{
                        date.times.map((time) => time.time).join(', ') ||
                        'No departure time'
                    }}
                </p>

                <p class="text-xs text-slate-500">
                    {{ date.pickup_schedules.length }}
                    pickup point(s)
                </p>

                <p v-if="date.has_bookings" class="text-xs text-amber-700">
                    Bookings exist: times and pickup locations are locked.
                </p>

                <button
                    type="button"
                    class="rounded-lg border px-3 py-2 text-sm font-semibold hover:bg-slate-50 dark:hover:bg-slate-800"
                    @click="openEdit(date)"
                >
                    Edit schedule
                </button>
            </article>
        </section>

        <!-- CREATE NEW TOUR DATE -->
        <section
            v-if="showingCreate"
            class="space-y-5 rounded-2xl border bg-white p-5 shadow-sm dark:bg-slate-900"
        >
            <h2 class="text-xl font-bold">New Tour Date</h2>

            <form class="space-y-5" @submit.prevent="submitCreate">
                <!-- DATE AND CAPACITY -->
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="space-y-1 text-sm font-medium">
                        Tour date

                        <input
                            v-model="createForm.tour_date"
                            type="date"
                            :min="props.today"
                            required
                            class="block w-full rounded-lg border bg-transparent px-3 py-2"
                        />
                    </label>

                    <label class="space-y-1 text-sm font-medium">
                        Guest capacity

                        <input
                            v-model.number="createForm.capacity"
                            type="number"
                            min="1"
                            max="10000"
                            required
                            class="block w-full rounded-lg border bg-transparent px-3 py-2"
                        />
                    </label>
                </div>

                <!-- DEPARTURE TIMES -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="font-semibold">Departure times</h3>

                        <button
                            type="button"
                            :disabled="createForm.times.length >= 8"
                            class="text-sm text-primary disabled:opacity-40"
                            @click="addTime(createForm)"
                        >
                            + Add time
                        </button>
                    </div>

                    <div
                        v-for="(time, index) in createForm.times"
                        :key="index"
                        class="flex flex-wrap items-end gap-2"
                    >
                        <label class="min-w-30 flex-1 text-xs">
                            Time

                            <input
                                v-model="time.time"
                                type="time"
                                required
                                class="mt-1 block w-full rounded-lg border bg-transparent px-2 py-2"
                            />
                        </label>

                        <label class="min-w-36 flex-2 text-xs">
                            Description

                            <input
                                v-model="time.description"
                                type="text"
                                maxlength="255"
                                required
                                class="mt-1 block w-full rounded-lg border bg-transparent px-2 py-2"
                            />
                        </label>

                        <button
                            type="button"
                            :disabled="createForm.times.length <= 1"
                            class="rounded-lg border p-2 disabled:opacity-30"
                            aria-label="Remove time"
                            @click="createForm.times.splice(index, 1)"
                        >
                            <Trash2 class="size-4" />
                        </button>
                    </div>
                </div>

                <!-- PICKUP SCHEDULES -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="font-semibold">Pickup schedules</h3>

                        <button
                            type="button"
                            :disabled="createForm.pickup_schedules.length >= 15"
                            class="text-sm text-primary disabled:opacity-40"
                            @click="addPickup(createForm)"
                        >
                            + Add pickup
                        </button>
                    </div>

                    <div
                        v-for="(pickup, index) in createForm.pickup_schedules"
                        :key="index"
                        class="flex flex-wrap items-end gap-2"
                    >
                        <!-- FILLABLE LOCATION -->
                        <label class="min-w-40 flex-2 text-xs">
                            Location

                            <input
                                v-model.trim="pickup.pickup_location_name"
                                type="text"
                                maxlength="255"
                                placeholder="e.g., Surigao City Port"
                                autocomplete="off"
                                required
                                class="mt-1 block w-full rounded-lg border bg-transparent px-2 py-2"
                            />
                        </label>

                        <!-- PICKUP TIME -->
                        <label class="min-w-30 flex-1 text-xs">
                            Pickup time

                            <input
                                v-model="pickup.pickup_time"
                                type="time"
                                required
                                class="mt-1 block w-full rounded-lg border bg-transparent px-2 py-2"
                            />
                        </label>

                        <button
                            type="button"
                            :disabled="createForm.pickup_schedules.length <= 1"
                            class="rounded-lg border p-2 disabled:opacity-30"
                            aria-label="Remove pickup"
                            @click="
                                createForm.pickup_schedules.splice(index, 1)
                            "
                        >
                            <Trash2 class="size-4" />
                        </button>
                    </div>
                </div>

                <!-- LOCATION INFORMATION -->
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Type the pickup location directly. New locations will be
                    saved automatically.
                </p>

                <!-- VALIDATION ERRORS -->
                <div
                    v-if="Object.keys(createForm.errors).length"
                    class="space-y-1 rounded-lg border border-red-300 p-3 text-sm text-red-700"
                >
                    <p v-for="(message, key) in createForm.errors" :key="key">
                        {{ message }}
                    </p>
                </div>

                <!-- CREATE ACTIONS -->
                <div class="flex gap-3">
                    <button
                        type="submit"
                        :disabled="createForm.processing"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 font-semibold text-primary-foreground disabled:opacity-50"
                    >
                        <Save class="size-4" />
                        Save schedule
                    </button>

                    <button
                        type="button"
                        class="rounded-lg border px-4 py-2"
                        @click="showingCreate = false"
                    >
                        Cancel
                    </button>
                </div>
            </form>
        </section>

        <!-- EDIT EXISTING SCHEDULE -->
        <section
            v-if="selected"
            class="space-y-5 rounded-2xl border bg-white p-5 shadow-sm dark:bg-slate-900"
        >
            <h2 class="text-xl font-bold">Edit {{ selected.tour_date }}</h2>

            <p v-if="selected.has_bookings" class="text-sm text-amber-700">
                This date has bookings. Only capacity may be changed. It cannot
                be lower than
                {{ selected.reserved_guests }} reserved guests.
            </p>

            <form class="space-y-5" @submit.prevent="submitEdit">
                <!-- EDIT CAPACITY -->
                <label class="block max-w-xs space-y-1 text-sm font-medium">
                    Guest capacity

                    <input
                        v-model.number="editForm.capacity"
                        type="number"
                        :min="Math.max(1, selected.reserved_guests)"
                        max="10000"
                        required
                        class="block w-full rounded-lg border bg-transparent px-3 py-2"
                    />
                </label>

                <!-- EDIT TIMES AND PICKUPS -->
                <template v-if="!selected.has_bookings">
                    <!-- DEPARTURE TIMES -->
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <h3 class="font-semibold">Departure times</h3>

                            <button
                                type="button"
                                :disabled="editForm.times.length >= 8"
                                class="text-sm text-primary disabled:opacity-40"
                                @click="addTime(editForm)"
                            >
                                + Add
                            </button>
                        </div>

                        <div
                            v-for="(time, index) in editForm.times"
                            :key="index"
                            class="flex flex-wrap items-end gap-2"
                        >
                            <input
                                v-model="time.time"
                                type="time"
                                required
                                class="min-w-28 flex-1 rounded-lg border bg-transparent px-2 py-2"
                                aria-label="Departure time"
                            />

                            <input
                                v-model="time.description"
                                type="text"
                                required
                                maxlength="255"
                                class="min-w-40 flex-2 rounded-lg border bg-transparent px-2 py-2"
                                aria-label="Time description"
                            />

                            <button
                                type="button"
                                :disabled="editForm.times.length <= 1"
                                class="rounded-lg border p-2 disabled:opacity-30"
                                aria-label="Remove time"
                                @click="editForm.times.splice(index, 1)"
                            >
                                <Trash2 class="size-4" />
                            </button>
                        </div>
                    </div>

                    <!-- EDIT PICKUP LOCATIONS -->
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <h3 class="font-semibold">Pickup schedules</h3>

                            <button
                                type="button"
                                :disabled="
                                    editForm.pickup_schedules.length >= 15
                                "
                                class="text-sm text-primary disabled:opacity-40"
                                @click="addPickup(editForm)"
                            >
                                + Add
                            </button>
                        </div>

                        <div
                            v-for="(pickup, index) in editForm.pickup_schedules"
                            :key="index"
                            class="flex flex-wrap items-end gap-2"
                        >
                            <!-- EDITABLE LOCATION -->
                            <input
                                v-model.trim="pickup.pickup_location_name"
                                type="text"
                                maxlength="255"
                                placeholder="Pickup location"
                                autocomplete="off"
                                required
                                class="min-w-40 flex-2 rounded-lg border bg-transparent px-2 py-2"
                                aria-label="Pickup location"
                            />

                            <input
                                v-model="pickup.pickup_time"
                                type="time"
                                required
                                class="min-w-28 flex-1 rounded-lg border bg-transparent px-2 py-2"
                                aria-label="Pickup time"
                            />

                            <button
                                type="button"
                                :disabled="
                                    editForm.pickup_schedules.length <= 1
                                "
                                class="rounded-lg border p-2 disabled:opacity-30"
                                aria-label="Remove pickup"
                                @click="
                                    editForm.pickup_schedules.splice(index, 1)
                                "
                            >
                                <Trash2 class="size-4" />
                            </button>
                        </div>
                    </div>
                </template>

                <!-- EDIT ERRORS -->
                <div
                    v-if="Object.keys(editForm.errors).length"
                    class="space-y-1 rounded-lg border border-red-300 p-3 text-sm text-red-700"
                >
                    <p v-for="(message, key) in editForm.errors" :key="key">
                        {{ message }}
                    </p>
                </div>

                <!-- EDIT ACTIONS -->
                <div class="flex gap-3">
                    <button
                        type="submit"
                        :disabled="editForm.processing"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 font-semibold text-primary-foreground disabled:opacity-50"
                    >
                        <Save class="size-4" />
                        Save changes
                    </button>

                    <button
                        type="button"
                        class="rounded-lg border px-4 py-2"
                        @click="selected = null"
                    >
                        Cancel
                    </button>
                </div>
            </form>
        </section>
    </main>
</template>
