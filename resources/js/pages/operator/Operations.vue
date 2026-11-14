
<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import {
    ArrowLeft,
    CloudSun,
    MapPin,
    MessageSquare,
    Ship,
    Wrench,
} from '@lucide/vue';

import { Button } from '@/components/ui/button';

type Boat = {
    id: number;
    package_name: string;
    boat_name: string;
    under_maintenance: boolean;
};

type Location = {
    id: number;
    name: string;
    address: string | null;
    latitude: number | null;
    longitude: number | null;
    status: string;
    is_shared: boolean;
};

type Day = {
    id: number;
    date: string;
    package_name: string;
    travel_closed: boolean;
    advisory_level: string;
    advisory_badge: string;
    forecast_updated: string | null;
};

type Feedback = {
    id: number;
    booking_id: number;
    guest_name: string;
    package_name: string;
    enjoyment: string;
    operator_service: string;
    overall_experience: string;
    comment: string | null;
};

type Summary = {
    pending: number;
    confirmed: number;
    completed: number;
    cancelled: number;
};

const props = defineProps<{
    section: 'boats' | 'pickups' | 'weather' | 'feedback';
    operatorName: string;
    boats: Boat[];
    locations: Location[];
    dates: Day[];
    feedback: Feedback[];
    summary: Summary;
}>();

const busy = ref(false);

const sectionTitle = computed(() => {
    switch (props.section) {
        case 'boats':
            return 'Boats';
        case 'pickups':
            return 'Pickup Locations';
        case 'weather':
            return 'Weather';
        case 'feedback':
            return 'Feedback';
        default:
            return 'Operations';
    }
});

const sectionDescription = computed(() => {
    switch (props.section) {
        case 'boats':
            return 'Manage boat names and maintenance for your packages.';
        case 'pickups':
            return 'Manage pickup locations for your tours.';
        case 'weather':
            return 'Refresh forecasts and control travel for upcoming tour dates.';
        case 'feedback':
            return 'Read guest feedback for your bookings.';
        default:
            return '';
    }
});

/*
|--------------------------------------------------------------------------
| BOAT MAINTENANCE
|--------------------------------------------------------------------------
*/

const boatEditor = ref<number | null>(null);

const boatForm = useForm({
    boat_name: '',
    under_maintenance: false,
});

function editBoat(boat: Boat): void {
    boatEditor.value = boat.id;

    boatForm.clearErrors();
    boatForm.boat_name = boat.boat_name;
    boatForm.under_maintenance = boat.under_maintenance;
}

function saveBoat(): void {
    if (boatEditor.value === null) return;

    boatForm.patch(
        `/operator/operations/boats/${boatEditor.value}`,
        {
            preserveScroll: true,
            onSuccess: () => {
                boatEditor.value = null;
            },
        },
    );
}

function switchMaintenance(boat: Boat): void {
    if (busy.value) return;

    busy.value = true;

    router.patch(
        `/operator/operations/boats/${boat.id}`,
        {
            boat_name: boat.boat_name,
            under_maintenance: !boat.under_maintenance,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                busy.value = false;
            },
        },
    );
}

/*
|--------------------------------------------------------------------------
| PICKUP LOCATIONS
|--------------------------------------------------------------------------
*/

const locationEditor = ref<number | null>(null);

const locationForm = useForm({
    name: '',
    address: '',
    latitude: null as number | null,
    longitude: null as number | null,
    status: 'active',
});

function resetLocation(): void {
    locationEditor.value = null;
    locationForm.reset();
    locationForm.clearErrors();
}

function editLocation(location: Location): void {
    locationEditor.value = location.id;

    locationForm.clearErrors();
    locationForm.name = location.name;
    locationForm.address = location.address ?? '';
    locationForm.latitude = location.latitude;
    locationForm.longitude = location.longitude;
    locationForm.status = location.status;
}

function saveLocation(): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => resetLocation(),
    };

    if (locationEditor.value === null) {
        locationForm.post(
            '/operator/operations/pickup-locations',
            options,
        );
    } else {
        locationForm.patch(
            `/operator/operations/pickup-locations/${locationEditor.value}`,
            options,
        );
    }
}

function deleteLocation(location: Location): void {
    if (
        busy.value ||
        !window.confirm(
            `Delete ${location.name}? Locations used by schedules cannot be deleted.`,
        )
    ) {
        return;
    }

    busy.value = true;

    router.delete(
        `/operator/operations/pickup-locations/${location.id}`,
        {
            preserveScroll: true,
            onFinish: () => {
                busy.value = false;
            },
        },
    );
}

/*
|--------------------------------------------------------------------------
| WEATHER
|--------------------------------------------------------------------------
*/

function changeTravel(day: Day): void {
    if (busy.value) return;

    busy.value = true;

    router.post(
        `/operator/operations/weather/${day.id}/toggle`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                busy.value = false;
            },
        },
    );
}

function refreshWeather(day: Day): void {
    if (busy.value) return;

    busy.value = true;

    router.post(
        `/operator/operations/weather/${day.id}/refresh`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                busy.value = false;
            },
        },
    );
}

</script>

<template>
    <Head :title="`${sectionTitle} · Operations`" />

    <main
        class="mx-auto max-w-6xl space-y-8 px-4 py-6 pb-20 sm:px-6"
    >
        <!-- Header -->
        <header>
            <Link
                href="/operator/dashboard"
                class="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
            >
                <ArrowLeft class="h-4 w-4" />

                Back to Dashboard
            </Link>

            <p class="mt-3 text-sm font-semibold text-primary">
                Operations · {{ operatorName }}
            </p>

            <h1 class="mt-1 text-3xl font-bold">
                {{ sectionTitle }}
            </h1>

            <p class="mt-1 text-sm text-muted-foreground">
                {{ sectionDescription }}
            </p>
        </header>

        <!-- BOAT MAINTENANCE -->
        <section
            v-if="section === 'boats'"
            class="rounded-2xl border bg-card p-5 sm:p-6"
        >
            <h2 class="flex items-center gap-2 text-xl font-bold">
                <Ship class="h-5 w-5 text-primary" />
                Boat Maintenance
            </h2>

            <p class="mt-1 text-sm text-muted-foreground">
                Marking a boat under maintenance stops new bookings
                for its package. Existing bookings are not
                automatically cancelled.
            </p>

            <div
                v-if="boats.length === 0"
                class="mt-5 text-sm text-muted-foreground"
            >
                No packages assigned to your operator.
            </div>

            <div v-else class="mt-5 space-y-3">
                <div
                    v-for="boat in boats"
                    :key="boat.id"
                    class="flex flex-wrap items-center justify-between gap-3 rounded-xl border p-4"
                >
                    <div>
                        <p class="font-semibold">
                            {{ boat.boat_name || 'Unnamed boat' }}
                        </p>

                        <p class="text-xs text-muted-foreground">
                            {{ boat.package_name }}
                        </p>

                        <p
                            class="mt-1 text-xs"
                            :class="
                                boat.under_maintenance
                                    ? 'text-orange-600'
                                    : 'text-emerald-600'
                            "
                        >
                            {{
                                boat.under_maintenance
                                    ? 'Under Maintenance'
                                    : 'Available'
                            }}
                        </p>
                    </div>

                    <div class="flex gap-2">
                        <Button
                            size="sm"
                            variant="outline"
                            @click="editBoat(boat)"
                        >
                            Rename
                        </Button>

                        <Button
                            size="sm"
                            :disabled="busy"
                            :variant="
                                boat.under_maintenance
                                    ? 'default'
                                    : 'destructive'
                            "
                            @click="switchMaintenance(boat)"
                        >
                            <Wrench class="mr-1.5 h-4 w-4" />

                            {{
                                boat.under_maintenance
                                    ? 'Mark Available'
                                    : 'Under Maintenance'
                            }}
                        </Button>
                    </div>
                </div>
            </div>

            <form
                v-if="boatEditor !== null"
                class="mt-4 flex flex-wrap items-end gap-3 rounded-xl bg-muted/30 p-4"
                @submit.prevent="saveBoat"
            >
                <div class="flex-1">
                    <label class="mb-1 block text-xs font-semibold">
                        Boat name
                    </label>

                    <input
                        v-model="boatForm.boat_name"
                        maxlength="255"
                        required
                        class="w-full rounded-lg border bg-background px-3 py-2 text-sm"
                    />

                    <p
                        v-if="boatForm.errors.boat_name"
                        class="text-xs text-destructive"
                    >
                        {{ boatForm.errors.boat_name }}
                    </p>
                </div>

                <Button
                    type="submit"
                    :disabled="boatForm.processing"
                >
                    Save
                </Button>

                <Button
                    type="button"
                    variant="outline"
                    @click="boatEditor = null"
                >
                    Cancel
                </Button>
            </form>
        </section>

        <!-- PICKUP LOCATIONS -->
        <section
            v-if="section === 'pickups'"
            class="rounded-2xl border bg-card p-5 sm:p-6"
        >
            <h2 class="flex items-center gap-2 text-xl font-bold">
                <MapPin class="h-5 w-5 text-primary" />

                Pickup Locations
            </h2>

            <p class="mt-1 text-sm text-muted-foreground">
                You can manage your locations. Shared pickup points
                are visible but cannot be edited or deleted.
            </p>

            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                <div
                    v-for="location in locations"
                    :key="location.id"
                    class="rounded-xl border p-4"
                >
                    <p class="font-semibold">
                        {{ location.name }}
                    </p>

                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ location.address ?? 'No address' }}
                    </p>

                    <p class="mt-1 text-xs text-muted-foreground">
                        {{
                            location.is_shared
                                ? 'Shared point (read-only)'
                                : 'My location'
                        }}
                        · {{ location.status }}
                    </p>

                    <div
                        v-if="!location.is_shared"
                        class="mt-3 flex gap-2"
                    >
                        <Button
                            size="sm"
                            variant="outline"
                            @click="editLocation(location)"
                        >
                            Edit
                        </Button>

                        <Button
                            size="sm"
                            variant="destructive"
                            :disabled="busy"
                            @click="deleteLocation(location)"
                        >
                            Delete
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Create / Edit Pickup Location -->
            <form
                class="mt-5 space-y-3 rounded-xl border bg-muted/20 p-4"
                @submit.prevent="saveLocation"
            >
                <h3 class="font-semibold">
                    {{
                        locationEditor === null
                            ? 'Add Pickup Location'
                            : 'Edit Pickup Location'
                    }}
                </h3>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-semibold">
                            Location name
                        </label>

                        <input
                            v-model="locationForm.name"
                            required
                            maxlength="255"
                            class="w-full rounded-lg border bg-background px-3 py-2 text-sm"
                        />

                        <p
                            v-if="locationForm.errors.name"
                            class="text-xs text-destructive"
                        >
                            {{ locationForm.errors.name }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold">
                            Address
                        </label>

                        <input
                            v-model="locationForm.address"
                            maxlength="500"
                            class="w-full rounded-lg border bg-background px-3 py-2 text-sm"
                        />
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold">
                            Latitude (optional)
                        </label>

                        <input
                            v-model.number="locationForm.latitude"
                            type="number"
                            step="any"
                            min="-90"
                            max="90"
                            class="w-full rounded-lg border bg-background px-3 py-2 text-sm"
                        />
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold">
                            Longitude (optional)
                        </label>

                        <input
                            v-model.number="locationForm.longitude"
                            type="number"
                            step="any"
                            min="-180"
                            max="180"
                            class="w-full rounded-lg border bg-background px-3 py-2 text-sm"
                        />
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold">
                            Status
                        </label>

                        <select
                            v-model="locationForm.status"
                            class="w-full rounded-lg border bg-background px-3 py-2 text-sm"
                        >
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <p
                    v-if="
                        locationForm.errors.latitude ||
                        locationForm.errors.longitude
                    "
                    class="text-xs text-destructive"
                >
                    {{
                        locationForm.errors.latitude ||
                        locationForm.errors.longitude
                    }}
                </p>

                <div class="flex gap-2">
                    <Button
                        type="submit"
                        :disabled="locationForm.processing"
                    >
                        {{
                            locationEditor === null
                                ? 'Add Location'
                                : 'Save Location'
                        }}
                    </Button>

                    <Button
                        v-if="locationEditor !== null"
                        variant="outline"
                        type="button"
                        @click="resetLocation"
                    >
                        Cancel Edit
                    </Button>
                </div>
            </form>
        </section>

        <!-- WEATHER -->
        <section
            v-if="section === 'weather'"
            class="rounded-2xl border bg-card p-5 sm:p-6"
        >
            <h2 class="flex items-center gap-2 text-xl font-bold">
                <CloudSun class="h-5 w-5 text-primary" />

                Weather & Travel Controls
            </h2>

            <p class="mt-1 text-sm text-muted-foreground">
                Upcoming 30 days (up to 100 schedules).
                Orange/red weather still blocks new bookings
                even if manual closure is lifted.
            </p>

            <div
                v-if="dates.length === 0"
                class="mt-5 text-sm text-muted-foreground"
            >
                No upcoming tour schedules.
            </div>

            <div v-else class="mt-5 space-y-3">
                <div
                    v-for="day in dates"
                    :key="day.id"
                    class="flex flex-wrap items-center justify-between gap-3 rounded-xl border p-4"
                >
                    <div>
                        <p class="font-semibold">
                            {{ day.package_name }} · {{ day.date }}
                        </p>

                        <p class="text-xs text-muted-foreground">
                            {{ day.advisory_badge }}
                            ({{ day.advisory_level }})
                            ·
                            {{
                                day.forecast_updated ??
                                'Forecast not fetched'
                            }}
                        </p>

                        <p
                            class="mt-1 text-xs"
                            :class="
                                day.travel_closed
                                    ? 'text-orange-600'
                                    : 'text-emerald-600'
                            "
                        >
                            {{
                                day.travel_closed
                                    ? 'Manually Closed'
                                    : 'No manual closure'
                            }}
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Button
                            size="sm"
                            variant="outline"
                            :disabled="busy"
                            @click="refreshWeather(day)"
                        >
                            Refresh
                        </Button>

                        <Button
                            size="sm"
                            :variant="
                                day.travel_closed
                                    ? 'default'
                                    : 'destructive'
                            "
                            :disabled="busy"
                            @click="changeTravel(day)"
                        >
                            {{
                                day.travel_closed
                                    ? 'Reopen Manually'
                                    : 'Close Travel'
                            }}
                        </Button>
                    </div>
                </div>
            </div>
        </section>

        <!-- CUSTOMER FEEDBACK -->
        <section
            v-if="section === 'feedback'"
            class="rounded-2xl border bg-card p-5 sm:p-6"
        >
            <h2 class="flex items-center gap-2 text-xl font-bold">
                <MessageSquare class="h-5 w-5 text-primary" />

                Customer Tour Feedback
            </h2>

            <p class="mt-1 text-sm text-muted-foreground">
                Latest 50 guest reviews about your packages and service — not about the website.
            </p>

            <p
                v-if="feedback.length === 0"
                class="mt-4 text-sm text-muted-foreground"
            >
                No feedback yet.
            </p>

            <div v-else class="mt-4 space-y-3">
                <div
                    v-for="item in feedback"
                    :key="item.id"
                    class="rounded-xl border p-4"
                >
                    <p class="text-sm font-semibold">
                        {{ item.guest_name }}
                        · {{ item.package_name }}
                        · Booking #{{ item.booking_id }}
                    </p>

                    <p class="mt-1 text-xs text-muted-foreground">
                        Enjoyment: {{ item.enjoyment }}
                        · Operator service: {{ item.operator_service }}
                        · Overall: {{ item.overall_experience }}
                    </p>

                    <p
                        v-if="item.comment"
                        class="mt-2 whitespace-pre-line text-sm"
                    >
                        {{ item.comment }}
                    </p>
                </div>
            </div>
        </section>

    </main>
</template>
