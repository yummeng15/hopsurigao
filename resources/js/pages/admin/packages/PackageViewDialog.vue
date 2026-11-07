<!-- resources/js/pages/admin/packages/PackageViewDialog.vue -->

<script setup lang="ts">

import { ref, watch } from 'vue';

import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { Separator } from '@/components/ui/separator';

import {
    Carousel,
    CarouselContent,
    CarouselItem,
    CarouselPrevious,
    CarouselNext,
} from '@/components/ui/carousel';

import { show } from '@/routes/admin/packages';

import {
    MapPin,
    ImageOff,
    CheckCircle2,
    BellRing,
    CalendarDays,
    Clock,
    Users,
    Bus,
    ExternalLink,
} from '@lucide/vue';


/*
|--------------------------------------------------------------------------
| Types
|--------------------------------------------------------------------------
*/


/**
 * Filesystem image.
 *
 * IMPORTANT:
 * This image does NOT come from the database.
 *
 * The backend discovers the files from storage
 * and sends this information to Vue.
 */
type PackageImage = {
    name: string;
    path: string;
    url: string;
};


type PackageDetails = {

    id: number;

    package_name: string;

    destination: string;

    destinations?: {
        id: number;
        name: string;
    }[];

    description: string | null;

    price: string;

    status: string;

    operator_name?: string | null;

    admin_name?: string | null;


    /*
     * NEW IMAGE STRUCTURE
     *
     * No:
     *
     * image
     * image_urls
     *
     * anymore.
     */
    images: PackageImage[];


    inclusions: {
        id: number;
        description: string;
    }[];


    reminders: {
        id: number;
        description: string;
    }[];


    tour_dates: {

        id: number;

        tour_date: string;

        capacity: number;


        times: {
            id: number;
            time: string;
            description: string | null;
        }[];


        pickup_schedules: {

            id: number;

            pickup_time: string;

            pickup_location: {
                id: number;
                name: string;
            } | null;

        }[];

    }[];
};


/*
|--------------------------------------------------------------------------
| Props
|--------------------------------------------------------------------------
*/

const props = defineProps<{
    open: boolean;
    packageId: number | null;
}>();


const emit = defineEmits<{
    'update:open': [value: boolean];
}>();


/*
|--------------------------------------------------------------------------
| State
|--------------------------------------------------------------------------
*/

const loading = ref(false);

const pkg = ref<PackageDetails | null>(null);


/*
|--------------------------------------------------------------------------
| Image Helpers
|--------------------------------------------------------------------------
*/

function openImageInNewTab(
    url: string
) {
    window.open(
        url,
        '_blank',
        'noopener,noreferrer'
    );
}


/*
|--------------------------------------------------------------------------
| Fetch Package
|--------------------------------------------------------------------------
*/

async function fetchDetails(
    id: number
) {

    loading.value = true;

    pkg.value = null;


    try {
        const response = await fetch(show(id).url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (! response.ok) {
            throw new Error(`Failed to load package (${response.status})`);
        }

        /*
         * Backend should now return:
         *
         * {
         *     ...
         *     images: [...]
         * }
         */
        pkg.value = await response.json();
    } catch (error) {
        console.error(
            'Failed to load package details:',
            error
        );

        pkg.value = null;
    } finally {
        loading.value = false;
    }
}


/*
|--------------------------------------------------------------------------
| Date Formatting
|--------------------------------------------------------------------------
*/

function formatDate(
    dateStr: string
) {

    const d =
        new Date(dateStr);


    if (
        isNaN(
            d.getTime()
        )
    ) {
        return dateStr;
    }


    return d.toLocaleDateString(
        'en-PH',
        {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        }
    );
}


/*
|--------------------------------------------------------------------------
| Time Formatting
|--------------------------------------------------------------------------
*/

function formatTime(
    timeStr: string
) {

    const [
        h,
        m,
    ] =
        timeStr
            .split(':')
            .map(Number);


    if (
        isNaN(h) ||
        isNaN(m)
    ) {
        return timeStr;
    }


    const period =
        h >= 12
            ? 'PM'
            : 'AM';


    const hour12 =
        h % 12 === 0
            ? 12
            : h % 12;


    return `${hour12}:${String(m).padStart(2, '0')} ${period}`;
}


/*
|--------------------------------------------------------------------------
| Fetch When Dialog Opens
|--------------------------------------------------------------------------
*/

watch(

    () => [
        props.open,
        props.packageId,
    ] as const,

    ([isOpen, id]) => {

        if (
            isOpen &&
            id
        ) {
            fetchDetails(id);
        }

    },

    {
        immediate: true,
    }

);

</script>


<template>

    <Dialog
        :open="open"
        @update:open="
            (value) =>
                emit(
                    'update:open',
                    value
                )
        "
    >

        <DialogContent
            class="max-h-[85vh] overflow-y-auto sm:max-w-3xl"
        >

            <!-- =====================================================
                 HEADER
            ====================================================== -->

            <DialogHeader>

                <DialogTitle
                    class="text-xl"
                >
                    {{
                        pkg?.package_name
                            ?? 'Package details'
                    }}
                </DialogTitle>


                <DialogDescription
                    v-if="pkg"
                    class="flex items-center gap-1.5"
                >

                    <MapPin
                        class="h-3.5 w-3.5"
                    />

                    <template v-if="pkg.destinations?.length">
                        <Badge
                            v-for="destination in pkg.destinations"
                            :key="destination.id"
                            variant="secondary"
                        >
                            {{ destination.name }}
                        </Badge>
                    </template>

                    <span v-else>
                        {{ pkg.destination }}
                    </span>

                </DialogDescription>

            </DialogHeader>

            <div v-if="pkg" class="grid gap-3 rounded-xl border bg-muted/40 p-4 text-sm sm:grid-cols-2">
                <p>
                    <span class="block text-xs font-semibold uppercase tracking-wide text-muted-foreground">Tour Operator Business</span>
                    {{ pkg.operator_name || 'Unassigned' }}
                </p>
                <p>
                    <span class="block text-xs font-semibold uppercase tracking-wide text-muted-foreground">Operator Admin</span>
                    {{ pkg.admin_name || 'Unassigned' }}
                </p>
            </div>


            <!-- =====================================================
                 LOADING
            ====================================================== -->

            <div
                v-if="loading"
                class="space-y-4"
            >

                <Skeleton
                    class="h-64 w-full rounded-lg"
                />


                <div class="flex gap-3">

                    <Skeleton
                        class="h-16 w-1/2 rounded-md"
                    />

                    <Skeleton
                        class="h-16 w-1/2 rounded-md"
                    />

                </div>


                <Skeleton
                    class="h-4 w-full"
                />

                <Skeleton
                    class="h-4 w-2/3"
                />

            </div>


            <!-- =====================================================
                 PACKAGE
            ====================================================== -->

            <div
                v-else-if="pkg"
                class="space-y-6"
            >


                <!-- =================================================
                     IMAGE CAROUSEL
                ================================================== -->

                <Carousel
                    v-if="pkg.images.length"
                    class="w-full"
                >

                    <CarouselContent>

                        <CarouselItem
                            v-for="(
                                image,
                                index
                            ) in pkg.images"
                            :key="image.path"
                        >

                            <div
                                class="group relative block aspect-video w-full overflow-hidden rounded-lg border"
                            >

                                <!-- Image -->

                                <img
                                    :src="image.url"
                                    :alt="
                                        `${pkg.package_name} image ${index + 1}`
                                    "
                                    class="h-full w-full object-cover"
                                />


                                <!-- Hover Overlay -->

                                <div
                                    class="absolute inset-0 flex items-center justify-center bg-black/0 transition group-hover:bg-black/40"
                                >

                                    <button
                                        type="button"
                                        class="rounded-full bg-white/20 p-2.5 text-white opacity-0 transition hover:bg-white/40 group-hover:opacity-100"
                                        title="Open image in new tab"
                                        @click="
                                            openImageInNewTab(
                                                image.url
                                            )
                                        "
                                    >

                                        <ExternalLink
                                            class="h-5 w-5"
                                        />

                                    </button>

                                </div>


                                <!-- Image Counter -->

                                <span
                                    class="absolute bottom-2 right-2 rounded-full bg-black/60 px-2 py-0.5 text-xs text-white"
                                >

                                    {{ index + 1 }}
                                    /
                                    {{ pkg.images.length }}

                                </span>

                            </div>

                        </CarouselItem>

                    </CarouselContent>


                    <!-- Navigation -->

                    <template
                        v-if="
                            pkg.images.length > 1
                        "
                    >

                        <CarouselPrevious
                            class="left-2"
                        />

                        <CarouselNext
                            class="right-2"
                        />

                    </template>

                </Carousel>


                <!-- =================================================
                     NO IMAGES
                ================================================== -->

                <div
                    v-else
                    class="flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed py-10 text-muted-foreground"
                >

                    <ImageOff
                        class="h-6 w-6"
                    />

                    <p class="text-sm">
                        No images uploaded.
                    </p>

                </div>


                <!-- =================================================
                     PRICE / STATUS
                ================================================== -->

                <div
                    class="grid grid-cols-2 gap-3"
                >

                    <!-- Price -->

                    <div
                        class="rounded-lg border bg-muted/30 p-3"
                    >

                        <p
                            class="text-xs text-muted-foreground"
                        >
                            Price
                        </p>

                        <p
                            class="text-lg font-semibold"
                        >
                            ₱{{ pkg.price }}
                        </p>

                    </div>


                    <!-- Status -->

                    <div
                        class="rounded-lg border bg-muted/30 p-3"
                    >

                        <p
                            class="text-xs text-muted-foreground"
                        >
                            Status
                        </p>


                        <Badge
                            class="mt-1"
                            :variant="
                                pkg.status === 'active'
                                    ? 'default'
                                    : 'secondary'
                            "
                        >

                            {{ pkg.status }}

                        </Badge>

                    </div>

                </div>


                <!-- =================================================
                     DESCRIPTION
                ================================================== -->

                <div
                    v-if="pkg.description"
                >

                    <p
                        class="mb-1.5 text-sm font-medium"
                    >
                        Description
                    </p>


                    <p
                        class="whitespace-pre-line text-sm leading-relaxed text-muted-foreground"
                    >
                        {{ pkg.description }}
                    </p>

                </div>


                <Separator />


                <!-- =================================================
                     INCLUSIONS + REMINDERS
                ================================================== -->

                <div
                    class="grid grid-cols-1 gap-4 sm:grid-cols-2"
                >

                    <!-- Inclusions -->

                    <div
                        class="rounded-lg border p-4"
                    >

                        <div
                            class="mb-3 flex items-center justify-between"
                        >

                            <p
                                class="flex items-center gap-1.5 text-sm font-medium"
                            >

                                <CheckCircle2
                                    class="h-4 w-4 text-emerald-600"
                                />

                                Inclusions

                            </p>


                            <Badge variant="outline">

                                {{
                                    pkg.inclusions.length
                                }}

                            </Badge>

                        </div>


                        <ul
                            v-if="
                                pkg.inclusions.length
                            "
                            class="space-y-1.5 text-sm"
                        >

                            <li
                                v-for="
                                    inclusion in pkg.inclusions
                                "
                                :key="inclusion.id"
                                class="flex items-start gap-2"
                            >

                                <span
                                    class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-muted-foreground"
                                ></span>


                                <span>
                                    {{
                                        inclusion.description
                                    }}
                                </span>

                            </li>

                        </ul>


                        <p
                            v-else
                            class="text-sm text-muted-foreground"
                        >
                            None listed.
                        </p>

                    </div>


                    <!-- Reminders -->

                    <div
                        class="rounded-lg border p-4"
                    >

                        <div
                            class="mb-3 flex items-center justify-between"
                        >

                            <p
                                class="flex items-center gap-1.5 text-sm font-medium"
                            >

                                <BellRing
                                    class="h-4 w-4 text-amber-600"
                                />

                                Reminders

                            </p>


                            <Badge variant="outline">

                                {{
                                    pkg.reminders.length
                                }}

                            </Badge>

                        </div>


                        <ul
                            v-if="
                                pkg.reminders.length
                            "
                            class="space-y-1.5 text-sm"
                        >

                            <li
                                v-for="
                                    reminder in pkg.reminders
                                "
                                :key="reminder.id"
                                class="flex items-start gap-2"
                            >

                                <span
                                    class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-muted-foreground"
                                ></span>


                                <span>
                                    {{
                                        reminder.description
                                    }}
                                </span>

                            </li>

                        </ul>


                        <p
                            v-else
                            class="text-sm text-muted-foreground"
                        >
                            None listed.
                        </p>

                    </div>

                </div>


                <Separator />


                <!-- =================================================
                     TOUR DATES
                ================================================== -->

                <div>

                    <div
                        class="mb-3 flex items-center justify-between"
                    >

                        <p
                            class="flex items-center gap-1.5 text-sm font-medium"
                        >

                            <CalendarDays
                                class="h-4 w-4"
                            />

                            Tour dates

                        </p>


                        <Badge variant="outline">

                            {{
                                pkg.tour_dates.length
                            }}

                        </Badge>

                    </div>


                    <!-- Dates -->

                    <div
                        v-if="
                            pkg.tour_dates.length
                        "
                        class="space-y-3"
                    >

                        <div
                            v-for="
                                tourDate in pkg.tour_dates
                            "
                            :key="tourDate.id"
                            class="rounded-lg border p-4"
                        >

                            <!-- Date / Capacity -->

                            <div
                                class="flex items-center justify-between"
                            >

                                <span
                                    class="font-medium"
                                >

                                    {{
                                        formatDate(
                                            tourDate.tour_date
                                        )
                                    }}

                                </span>


                                <span
                                    class="flex items-center gap-1 text-sm text-muted-foreground"
                                >

                                    <Users
                                        class="h-3.5 w-3.5"
                                    />

                                    {{
                                        tourDate.capacity
                                    }}
                                    pax

                                </span>

                            </div>


                            <!-- Times -->

                            <div
                                v-if="
                                    tourDate.times.length
                                "
                                class="mt-3 flex flex-wrap gap-2"
                            >

                                <span
                                    v-for="
                                        time in tourDate.times
                                    "
                                    :key="time.id"
                                    class="inline-flex items-center gap-1.5 rounded-full border bg-muted/40 px-2.5 py-1 text-xs"
                                >

                                    <Clock
                                        class="h-3 w-3"
                                    />

                                    {{
                                        formatTime(
                                            time.time
                                        )
                                    }}


                                    <span
                                        v-if="
                                            time.description
                                        "
                                        class="text-muted-foreground"
                                    >

                                        —
                                        {{
                                            time.description
                                        }}

                                    </span>

                                </span>

                            </div>


                            <!-- Pickup Schedules -->

                            <div
                                v-if="
                                    tourDate.pickup_schedules.length
                                "
                                class="mt-3 space-y-1.5 border-t pt-3"
                            >

                                <p
                                    class="flex items-center gap-1.5 text-xs uppercase text-muted-foreground"
                                >

                                    <Bus
                                        class="h-3 w-3"
                                    />

                                    Pickup schedule

                                </p>


                                <div
                                    v-for="
                                        pickup in tourDate.pickup_schedules
                                    "
                                    :key="pickup.id"
                                    class="flex items-center justify-between text-sm"
                                >

                                    <span>

                                        {{
                                            pickup
                                                .pickup_location
                                                ?.name
                                                ??
                                            'Unknown location'
                                        }}

                                    </span>


                                    <span
                                        class="text-muted-foreground"
                                    >

                                        {{
                                            formatTime(
                                                pickup.pickup_time
                                            )
                                        }}

                                    </span>

                                </div>

                            </div>

                        </div>

                    </div>


                    <!-- No Dates -->

                    <div
                        v-else
                        class="flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed py-8 text-muted-foreground"
                    >

                        <CalendarDays
                            class="h-6 w-6"
                        />

                        <p class="text-sm">
                            No tour dates scheduled.
                        </p>

                    </div>

                </div>

            </div>


            <!-- =====================================================
                 ERROR / EMPTY
            ====================================================== -->

            <div
                v-else
                class="py-8 text-center text-sm text-muted-foreground"
            >

                Couldn't load package details.

            </div>

        </DialogContent>

    </Dialog>

</template>