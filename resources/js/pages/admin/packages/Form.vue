<!-- resources/js/pages/admin/packages/Form.vue -->

<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';

import InputError from '@/components/InputError.vue';

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

import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

import {
    AlertCircle,
    Check,
    ChevronsUpDown,
    ExternalLink,
    Lock,
    Plus,
    Trash2,
    X,
} from '@lucide/vue';

import {
    Combobox,
    ComboboxAnchor,
    ComboboxEmpty,
    ComboboxGroup,
    ComboboxInput,
    ComboboxItem,
    ComboboxItemIndicator,
    ComboboxList,
    ComboboxTrigger,
} from '@/components/ui/combobox';


/*
|--------------------------------------------------------------------------
| Types
|--------------------------------------------------------------------------
*/

type PickupLocation = {
    id: number;
    name: string;
};


/**
 * Image discovered from the filesystem.
 *
 * IMPORTANT:
 * There is NO image column required in the packages table.
 */
export type PackageImage = {
    name: string;
    path: string;
    url: string;
};


export type PackageValues = {
    id: number;

    package_name: string;

    boat_name?: string | null;

    destination: string;

    destinations?: {
        name: string;
    }[];

    description: string | null;

    price: number | string;

    status: string;


    /*
     * IMPORTANT:
     *
     * Do NOT keep:
     *
     * image: string[] | null;
     * image_urls?: string[];
     *
     * Those belonged to the old database-image approach.
     */

    inclusions?: {
        description: string;
    }[];

    activities?: {
        description: string;
    }[];

    reminders?: {
        description: string;
    }[];

    tour_dates?: {
        id?: number;
        tour_date: string;
        capacity: number;
        bookings_count?: number;
        times?: {
            id?: number;
            time: string;
            description: string | null;
        }[];
        pickup_schedules?: {
            id?: number;
            pickup_location_id: number | string;
            pickup_time: string;
            bookings_count?: number;
        }[];
    }[];
};

export type FormTourTime = {
    id?: number;
    time: string;
    description: string;
};

export type FormTourPickup = {
    id?: number;
    bookings_count?: number;
    pickup_location_id: string;
    pickup_time: string;
};

export type FormTourDate = {
    id?: number;
    bookings_count?: number;
    tour_date: string;
    capacity: number;
    times: FormTourTime[];
    pickup_schedules: FormTourPickup[];
};


/*
|--------------------------------------------------------------------------
| Props
|--------------------------------------------------------------------------
*/

type DestinationChoice = {
    label: string;
    value: string;
};

const props = defineProps<{
    pickupLocations: PickupLocation[];

    destinationOptions?: string[];

    package?: PackageValues;

    /*
     * Images are now supplied separately from the package.
     *
     * These come from the filesystem through the backend.
     */
    images?: PackageImage[];

    submitAction: {
        url: string;
        method: 'post' | 'put';
    };

    submitLabel: string;

    cancelHref?: string;

    onCancel?: () => void;
}>();


const emit = defineEmits<{
    success: [];
}>();


/*
|--------------------------------------------------------------------------
| Form
|--------------------------------------------------------------------------
*/

function savedDestinationNames(): string[] {
    const saved = props.package?.destinations
        ?.map((destination) => destination.name.trim())
        .filter((name) => name !== '');

    if (saved?.length) {
        return saved;
    }

    if (props.package?.destination) {
        return [props.package.destination];
    }

    return [];
}

const destinationChoices = ref<DestinationChoice[]>(
    [...new Set([...(props.destinationOptions ?? []), ...savedDestinationNames()])]
        .map((name) => name.trim())
        .filter((name) => name !== '')
        .sort((left, right) => left.localeCompare(right))
        .map((name) => ({
            label: name,
            value: name,
        })),
);

const newDestination = ref('');

const form = useForm({
    package_name:
        props.package?.package_name ?? '',

    destinations: savedDestinationNames(),

    destination:
        props.package?.destination ?? '',

    description:
        props.package?.description ?? '',

    price:
        props.package?.price ?? '',

    status:
        props.package?.status ?? 'active',

    /*
     * NEW uploaded files.
     *
     * These are NOT database values.
     */
    images: [] as File[],

    /*
     * Existing filesystem images that
     * the user wants deleted.
     */
    removed_images: [] as string[],

    inclusions:
        (props.package?.inclusions ?? []).map(
            (inclusion) => ({
                description:
                    inclusion.description,
            })
        ),

    activities:
        (props.package?.activities ?? []).map(
            (activity) => ({
                description:
                    activity.description,
            })
        ),

    reminders:
        (props.package?.reminders ?? []).map(
            (reminder) => ({
                description:
                    reminder.description,
            })
        ),

    tour_dates:
        (props.package?.tour_dates ?? []).map(
            (tourDate) => ({
                id: tourDate.id,
                bookings_count: tourDate.bookings_count ?? 0,
                tour_date:
                    tourDate.tour_date?.slice(0, 10) ?? '',
                capacity:
                    tourDate.capacity,
                times:
                    (tourDate.times ?? []).map(
                        (time) => ({
                            id: time.id,
                            // Normalize HH:mm:ss -> HH:mm
                            time: time.time
                                ? String(time.time).slice(0, 5)
                                : '',
                            description:
                                time.description ?? '',
                        })
                    ),
                pickup_schedules:
                    (tourDate.pickup_schedules ?? []).map(
                        (pickup) => ({
                            id: pickup.id,
                            bookings_count: pickup.bookings_count ?? 0,
                            pickup_location_id:
                                String(
                                    pickup.pickup_location_id
                                ),
                            // Normalize HH:mm:ss -> HH:mm
                            pickup_time:
                                pickup.pickup_time
                                    ? String(
                                        pickup.pickup_time
                                    ).slice(0, 5)
                                    : '',
                        })
                    ),
            })
        ),
});


/*
|--------------------------------------------------------------------------
| Existing Images
|--------------------------------------------------------------------------
|
| These are filesystem images.
|
| They are NOT stored inside form.package.
|
*/

const existingImages = ref<PackageImage[]>(
    props.images ?? []
);


/*
|--------------------------------------------------------------------------
| Image Errors
|--------------------------------------------------------------------------
*/

const brokenImages = ref<Set<string>>(
    new Set()
);


function markBroken(path: string) {
    brokenImages.value.add(path);
}


/*
|--------------------------------------------------------------------------
| Remove Existing Image
|--------------------------------------------------------------------------
|
| We keep the image visible until the user removes it.
|
| Once removed:
|
| 1. It disappears from UI.
| 2. Its path is added to removed_images.
| 3. Backend deletes it from storage.
|
*/

function removeExistingImage(
    image: PackageImage
) {
    existingImages.value =
        existingImages.value.filter(
            (item) =>
                item.path !== image.path
        );

    if (
        !form.removed_images.includes(
            image.path
        )
    ) {
        form.removed_images.push(
            image.path
        );
    }
}


/*
|--------------------------------------------------------------------------
| New Image Uploads
|--------------------------------------------------------------------------
*/

const newImagePreviews = ref<string[]>([]);

const MAX_IMAGE_SIZE_BYTES = 2 * 1024 * 1024; // 2MB
const MAX_IMAGES = 10;
const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];

const imageErrorList = computed(() => {
    const errors: string[] = [];
    if (form.errors.images) {
        errors.push(form.errors.images);
    }
    Object.entries(form.errors).forEach(([key, msg]) => {
        if (key.startsWith('images.') && msg && !errors.includes(msg)) {
            errors.push(msg);
        }
    });
    return errors;
});

function onFilesSelected(event: Event) {
    const input = event.target as HTMLInputElement;
    if (!input.files) return;

    const files = Array.from(input.files);

    const totalAfter =
        existingImages.value.length +
        form.images.length +
        files.length;

    if (totalAfter > MAX_IMAGES) {
        toast.error(`You can only have up to ${MAX_IMAGES} images per package.`);
        input.value = '';
        return;
    }

    let hasInvalidType = false;
    let hasOversizedFile = false;

    for (const file of files) {
        if (!ALLOWED_IMAGE_TYPES.includes(file.type)) {
            hasInvalidType = true;
            continue;
        }

        if (file.size > MAX_IMAGE_SIZE_BYTES) {
            hasOversizedFile = true;
            continue;
        }

        form.images.push(file);
        newImagePreviews.value.push(URL.createObjectURL(file));
    }

    if (hasInvalidType) {
        toast.error('Some files were skipped because they are not valid JPG, PNG, or WEBP images.');
    }

    if (hasOversizedFile) {
        toast.error('Some files were skipped because they exceed the 2MB size limit per image.');
    }

    input.value = '';
}


/*
|--------------------------------------------------------------------------
| Remove New Image
|--------------------------------------------------------------------------
*/

function removeNewImage(
    index: number
) {
    /*
     * Release browser object URL.
     */
    const preview =
        newImagePreviews.value[index];

    if (preview) {
        URL.revokeObjectURL(preview);
    }


    form.images.splice(
        index,
        1
    );

    newImagePreviews.value.splice(
        index,
        1
    );
}


/*
|--------------------------------------------------------------------------
| Open Existing Image
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
| Inclusions
|--------------------------------------------------------------------------
*/

function addInclusion() {
    form.inclusions.push({
        description: '',
    });
    toast.info('Inclusion added.');
}


function addActivity() {
    form.activities.push({
        description: '',
    });
}

function removeActivity(index: number) {
    form.activities.splice(index, 1);
}

function removeInclusion(
    index: number
) {
    form.inclusions.splice(
        index,
        1
    );
    toast.info('Inclusion removed.');
}


/*
|--------------------------------------------------------------------------
| Reminders
|--------------------------------------------------------------------------
*/

function addReminder() {
    form.reminders.push({
        description: '',
    });
    toast.info('Reminder added.');
}


function removeReminder(
    index: number
) {
    form.reminders.splice(
        index,
        1
    );
    toast.info('Reminder removed.');
}


/*
|--------------------------------------------------------------------------
| Tour Dates
|--------------------------------------------------------------------------
*/

function addTourDate() {
    form.tour_dates.push({
        id: undefined,
        bookings_count: 0,
        tour_date: '',
        capacity: 1,
        times: [],
        pickup_schedules: [],
    });
    toast.info('Tour date added.');
}


function removeTourDate(index: number) {
    const td = form.tour_dates[index];
    if (td && td.bookings_count && td.bookings_count > 0) {
        toast.error('This tour date cannot be removed because it has active bookings.');
        return;
    }

    form.tour_dates.splice(index, 1);
    toast.info('Tour date removed.');
}


/*
|--------------------------------------------------------------------------
| Tour Times
|--------------------------------------------------------------------------
*/

function addTime(
    tourDateIndex: number
) {
    form.tour_dates[
        tourDateIndex
    ].times.push({
        id: undefined,
        time: '',
        description: '',
    });
}


function removeTime(
    tourDateIndex: number,
    timeIndex: number
) {
    form.tour_dates[
        tourDateIndex
    ].times.splice(
        timeIndex,
        1
    );
}


/*
|--------------------------------------------------------------------------
| Pickup Schedules
|--------------------------------------------------------------------------
*/

function addPickup(
    tourDateIndex: number
) {
    form.tour_dates[
        tourDateIndex
    ].pickup_schedules.push({
        id: undefined,
        bookings_count: 0,
        pickup_location_id: '',
        pickup_time: '',
    });
}


function removePickup(
    tourDateIndex: number,
    pickupIndex: number
) {
    const pickup = form.tour_dates[tourDateIndex]?.pickup_schedules[pickupIndex];
    if (pickup && pickup.bookings_count && pickup.bookings_count > 0) {
        toast.error('This pickup schedule cannot be removed because it has active bookings.');
        return;
    }

    form.tour_dates[
        tourDateIndex
    ].pickup_schedules.splice(
        pickupIndex,
        1
    );
}


/*
|--------------------------------------------------------------------------
| Edit State
|--------------------------------------------------------------------------
*/

const isEditing = computed(
    () => !!props.package?.id
);

const selectedDestinations = computed({
    get(): DestinationChoice[] {
        return form.destinations
            .filter((name) => name.trim() !== '')
            .map((name) => {
                return destinationChoices.value.find((choice) => choice.value === name)
                    ?? { label: name, value: name };
            });
    },
    set(choices: DestinationChoice[]) {
        form.destinations = choices.map((choice) => choice.value);
    },
});

const destinationError = computed(() => {
    if (form.errors.destinations || form.errors.destination) {
        return form.errors.destinations || form.errors.destination;
    }

    const match = Object.entries(form.errors).find(([key]) => key.startsWith('destinations.'));

    return match?.[1];
});

function addDestination(): void {
    const name = newDestination.value.trim();

    if (name === '') {
        return;
    }

    const existing = destinationChoices.value.find(
        (choice) => choice.value.toLowerCase() === name.toLowerCase(),
    );
    const value = existing?.value ?? name;

    if (!existing) {
        destinationChoices.value = [
            ...destinationChoices.value,
            { label: name, value },
        ].sort((left, right) => left.label.localeCompare(right.label));
    }

    if (!form.destinations.some((item) => item.toLowerCase() === value.toLowerCase())) {
        form.destinations.push(value);
    }

    newDestination.value = '';
}

function removeDestination(name: string): void {
    form.destinations = form.destinations.filter((item) => item !== name);
}


/*
|--------------------------------------------------------------------------
| Submit
|--------------------------------------------------------------------------
*/

function submit() {

    const options = {

        forceFormData: true,

        onSuccess: () => {
            emit('success');
        },
    };


    /*
     * Laravel multipart PUT issue.
     *
     * Use POST + _method=put.
     */
    if (
        props.submitAction.method ===
        'put'
    ) {

        form
            .transform((data) => ({
                ...data,
                destinations: data.destinations
                    .map((name) => name.trim())
                    .filter((name) => name !== ''),
                _method: 'put',
            }))
            .post(
                props.submitAction.url,
                options
            );

        return;
    }


    form
        .transform((data) => ({
            ...data,
            destinations: data.destinations
                .map((name) => name.trim())
                .filter((name) => name !== ''),
        }))
        .post(
            props.submitAction.url,
            options
        );
}
</script>


<template>

    <form class="flex flex-col gap-6" @submit.prevent="submit">

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

            <!-- =====================================================
                 BASIC INFORMATION
            ====================================================== -->

            <Card class="lg:col-span-2">

                <CardHeader>
                    <CardTitle>
                        Package details
                    </CardTitle>
                </CardHeader>


                <CardContent class="grid grid-cols-1 gap-6 md:grid-cols-2">

                    <!-- Package Name -->

                    <div class="grid gap-2">

                        <Label for="package_name">
                            Package name
                        </Label>

                        <Input id="package_name" v-model="form.package_name"
                            placeholder="e.g. Camiguin Island Hopping" />

                        <InputError :message="form.errors.package_name
                            " />

                    </div>


                    <!-- Destinations -->

                    <div class="grid gap-2 md:col-span-2">

                        <Label for="new-destination">
                            Destinations
                        </Label>

                        <div class="flex gap-2">
                            <Input
                                id="new-destination"
                                v-model="newDestination"
                                placeholder="Add a destination, e.g. Daku Island"
                                @keydown.enter.prevent="addDestination"
                            />
                            <Button type="button" variant="outline" @click="addDestination">
                                <Plus class="mr-2 h-4 w-4" />
                                Add
                            </Button>
                        </div>

                        <Combobox v-model="selectedDestinations" by="value" multiple>
                            <ComboboxAnchor as-child>
                                <ComboboxTrigger as-child>
                                    <Button type="button" variant="outline" class="w-full justify-between font-normal">
                                        <span class="truncate">
                                            {{ selectedDestinations.length
                                                ? selectedDestinations.map((choice) => choice.label).join(', ')
                                                : 'Select destinations' }}
                                        </span>
                                        <ChevronsUpDown class="h-4 w-4 shrink-0 opacity-50" />
                                    </Button>
                                </ComboboxTrigger>
                            </ComboboxAnchor>
                            <ComboboxList>
                                <ComboboxInput placeholder="Search destinations..." />
                                <ComboboxEmpty>No destination found. Use Add to create one.</ComboboxEmpty>
                                <ComboboxGroup>
                                    <ComboboxItem
                                        v-for="choice in destinationChoices"
                                        :key="choice.value"
                                        :value="choice"
                                    >
                                        {{ choice.label }}
                                        <ComboboxItemIndicator>
                                            <Check class="h-4 w-4" />
                                        </ComboboxItemIndicator>
                                    </ComboboxItem>
                                </ComboboxGroup>
                            </ComboboxList>
                        </Combobox>

                        <div v-if="selectedDestinations.length" class="flex flex-wrap gap-2">
                            <Badge
                                v-for="choice in selectedDestinations"
                                :key="choice.value"
                                variant="secondary"
                                class="gap-1 pr-1"
                            >
                                {{ choice.label }}
                                <button
                                    type="button"
                                    class="rounded-full p-0.5 hover:bg-muted-foreground/20"
                                    :aria-label="`Remove ${choice.label}`"
                                    @click="removeDestination(choice.value)"
                                >
                                    <X class="h-3 w-3" />
                                </button>
                            </Badge>
                        </div>

                        <InputError :message="destinationError" />

                    </div>


                    <!-- Price -->

                    <div class="grid gap-2">

                        <Label for="price">
                            Price
                        </Label>

                        <Input id="price" v-model="form.price" type="number" step="0.01" min="0" placeholder="0.00" />

                        <InputError :message="form.errors.price
                            " />

                    </div>


                    <!-- Status -->

                    <div class="grid gap-2">

                        <Label for="status">
                            Status
                        </Label>

                        <Select v-model="form.status">

                            <SelectTrigger id="status" class="w-full">
                                <SelectValue placeholder="Select status" />
                            </SelectTrigger>


                            <SelectContent>

                                <SelectItem value="active">
                                    Active
                                </SelectItem>

                                <SelectItem value="inactive">
                                    Inactive
                                </SelectItem>

                            </SelectContent>

                        </Select>


                        <InputError :message="form.errors.status
                            " />

                    </div>


                    <!-- Description -->

                    <div class="grid gap-2 md:col-span-2">

                        <Label for="description">
                            Description
                        </Label>

                        <Textarea id="description" v-model="form.description" rows="4"
                            placeholder="Package description..." />

                        <InputError :message="form.errors.description
                            " />

                    </div>

                </CardContent>

            </Card>


            <!-- =====================================================
                 IMAGES
            ====================================================== -->

            <Card class="lg:col-span-1">

                <CardHeader>

                    <CardTitle>
                        Images
                    </CardTitle>

                </CardHeader>


                <CardContent class="space-y-4">

                    <div class="flex flex-wrap gap-3">

                        <!-- =================================================
                             EXISTING FILESYSTEM IMAGES
                        ================================================== -->

                        <div v-for="img in existingImages" :key="img.path" class="group relative h-20 w-20">

                            <div class="relative h-20 w-20 overflow-hidden rounded-md border">

                                <img v-if="
                                    !brokenImages.has(
                                        img.path
                                    )
                                " :src="img.url" :alt="img.name" class="h-20 w-20 object-cover" @error="
                                    markBroken(
                                        img.path
                                    )
                                    " />


                                <div v-else
                                    class="flex h-20 w-20 items-center justify-center rounded-md border border-dashed bg-muted text-center text-[10px] text-muted-foreground">
                                    Failed to load
                                </div>


                                <!-- Open image -->

                                <button type="button"
                                    class="absolute inset-0 flex items-center justify-center bg-black/0 opacity-0 transition hover:bg-black/40 group-hover:opacity-100"
                                    title="Open image in new tab" @click="
                                        openImageInNewTab(
                                            img.url
                                        )
                                        ">

                                    <ExternalLink class="h-5 w-5 text-white" />

                                </button>

                            </div>


                            <!-- Remove -->

                            <button type="button"
                                class="absolute -right-2 -top-2 rounded-full bg-destructive p-1 text-destructive-foreground"
                                title="Remove image" @click="
                                    removeExistingImage(
                                        img
                                    )
                                    ">

                                <X class="h-3 w-3" />

                            </button>

                        </div>


                        <!-- =================================================
                             NEW IMAGE PREVIEWS
                        ================================================== -->

                        <div v-for="(
preview,
    i
                            ) in newImagePreviews" :key="`new-${i}`" class="group relative h-20 w-20">

                            <div class="relative h-20 w-20 overflow-hidden rounded-md border">

                                <img :src="preview" alt="New image preview" class="h-20 w-20 object-cover" />

                            </div>


                            <button type="button"
                                class="absolute -right-2 -top-2 rounded-full bg-destructive p-1 text-destructive-foreground"
                                title="Remove selected image" @click="
                                    removeNewImage(i)
                                    ">

                                <X class="h-3 w-3" />

                            </button>

                        </div>


                        <!-- =================================================
                             UPLOAD BUTTON
                        ================================================== -->

                        <label
                            class="flex h-20 w-20 cursor-pointer items-center justify-center rounded-md border border-dashed text-muted-foreground hover:bg-muted">

                            <Plus class="h-5 w-5" />

                            <input type="file" accept="image/jpeg,image/png,image/webp,image/jpg" multiple
                                class="hidden" @change="
                                    onFilesSelected
                                " />

                        </label>

                    </div>


                    <p class="text-xs text-muted-foreground">
                        Multiple images allowed (up to 10 total).
                        JPG/PNG/WEBP, up to 2MB each.
                    </p>

                    <div v-if="imageErrorList.length" class="space-y-1">
                        <InputError
                            v-for="(err, idx) in imageErrorList"
                            :key="idx"
                            :message="err"
                        />
                    </div>

                </CardContent>

            </Card>


            <!-- =====================================================
                 INCLUSIONS + REMINDERS
            ====================================================== -->

            <div class="grid grid-cols-1 gap-6 lg:col-span-3 lg:grid-cols-3">

                <!-- Inclusions -->

                <Card>

                    <CardHeader class="flex-row items-center justify-between">

                        <CardTitle>
                            Inclusions
                        </CardTitle>

                        <Button type="button" size="sm" variant="outline" @click="addInclusion">

                            <Plus class="mr-2 h-4 w-4" />

                            Add

                        </Button>

                    </CardHeader>


                    <CardContent class="space-y-3">

                        <div v-for="(
inclusion,
    i
                            ) in form.inclusions" :key="i" class="flex items-start gap-2">

                            <div class="flex-1">

                                <Input v-model="inclusion.description
                                    " placeholder="e.g. Roundtrip boat transfer" />

                                <InputError :message="(form.errors as any)[
                                    `inclusions.${i}.description`
                                ]
                                    " />

                            </div>


                            <Button type="button" size="icon" variant="ghost" @click="
                                removeInclusion(i)
                                ">

                                <Trash2 class="h-4 w-4 text-destructive" />

                            </Button>

                        </div>


                        <p v-if="
                            !form.inclusions.length
                        " class="text-sm text-muted-foreground">
                            No inclusions added yet.
                        </p>

                    </CardContent>

                </Card>

                <Card>
                    <CardHeader class="flex-row items-center justify-between">
                        <CardTitle>Activities</CardTitle>
                        <Button type="button" size="sm" variant="outline" @click="addActivity">
                            <Plus class="mr-2 h-4 w-4" />
                            Add
                        </Button>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div v-for="(activity, i) in form.activities" :key="i" class="flex items-start gap-2">
                            <div class="flex-1">
                                <Input v-model="activity.description" placeholder="e.g. Swimming and beach time" />
                                <InputError :message="(form.errors as any)[`activities.${i}.description`]" />
                            </div>
                            <Button type="button" size="icon" variant="ghost" @click="removeActivity(i)">
                                <Trash2 class="h-4 w-4 text-destructive" />
                            </Button>
                        </div>
                        <p v-if="!form.activities.length" class="text-sm text-muted-foreground">
                            No activities added yet.
                        </p>
                    </CardContent>
                </Card>

                <!-- Reminders -->

                <Card>

                    <CardHeader class="flex-row items-center justify-between">

                        <CardTitle>
                            Reminders
                        </CardTitle>

                        <Button type="button" size="sm" variant="outline" @click="addReminder">

                            <Plus class="mr-2 h-4 w-4" />

                            Add

                        </Button>

                    </CardHeader>


                    <CardContent class="space-y-3">

                        <div v-for="(
reminder,
    i
                            ) in form.reminders" :key="i" class="flex items-start gap-2">

                            <div class="flex-1">

                                <Input v-model="reminder.description
                                    " placeholder="e.g. Bring waterproof bag" />

                                <InputError :message="(form.errors as any)[
                                    `reminders.${i}.description`
                                ]
                                    " />

                            </div>


                            <Button type="button" size="icon" variant="ghost" @click="
                                removeReminder(i)
                                ">

                                <Trash2 class="h-4 w-4 text-destructive" />

                            </Button>

                        </div>


                        <p v-if="
                            !form.reminders.length
                        " class="text-sm text-muted-foreground">
                            No reminders added yet.
                        </p>

                    </CardContent>

                </Card>

            </div>


            <!-- =====================================================
                 TOUR DATES
            ====================================================== -->

            <Card class="lg:col-span-3">
                <CardHeader class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <CardTitle>Tour dates</CardTitle>
                        <CardDescription class="text-xs text-muted-foreground mt-0.5">
                            Configure schedule dates, guest capacities, itinerary times, and pickup schedules.
                        </CardDescription>
                    </div>

                    <Button type="button" size="sm" variant="outline" @click="addTourDate">
                        <Plus class="mr-2 h-4 w-4" />
                        Add date
                    </Button>
                </CardHeader>

                <CardContent class="space-y-6">
                    <!-- Global Tour Dates Error Banner -->
                    <div
                        v-if="form.errors.tour_dates"
                        class="rounded-xl border border-destructive/30 bg-destructive/10 p-3.5 text-xs text-destructive flex items-center gap-2.5"
                    >
                        <AlertCircle class="h-4 w-4 shrink-0" />
                        <span class="font-medium">{{ form.errors.tour_dates }}</span>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-1">
                        <div
                            v-for="(tourDate, tdIndex) in form.tour_dates"
                            :key="tdIndex"
                            class="space-y-4 rounded-xl border p-4 transition-colors"
                            :class="tourDate.bookings_count && tourDate.bookings_count > 0 ? 'bg-muted/10 border-border/80' : 'bg-card'"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div class="grid flex-1 grid-cols-1 gap-4 sm:grid-cols-2">
                                    <!-- Date -->
                                    <div class="grid gap-2">
                                        <div class="flex items-center justify-between">
                                            <Label>Date</Label>
                                            <Badge
                                                v-if="tourDate.bookings_count && tourDate.bookings_count > 0"
                                                variant="outline"
                                                class="text-[11px] bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20 font-medium flex items-center gap-1"
                                            >
                                                <Lock class="w-3 h-3" />
                                                <span>{{ tourDate.bookings_count }} active booking{{ tourDate.bookings_count > 1 ? 's' : '' }}</span>
                                            </Badge>
                                        </div>

                                        <Input
                                            v-model="tourDate.tour_date"
                                            type="date"
                                            :disabled="Boolean(tourDate.bookings_count && tourDate.bookings_count > 0)"
                                            :class="tourDate.bookings_count && tourDate.bookings_count > 0 ? 'opacity-85 bg-muted/40 cursor-not-allowed' : ''"
                                        />

                                        <p
                                            v-if="tourDate.bookings_count && tourDate.bookings_count > 0"
                                            class="text-[11px] text-muted-foreground"
                                        >
                                            Date locked to preserve existing guest reservations.
                                        </p>

                                        <InputError
                                            :message="(form.errors as any)[`tour_dates.${tdIndex}.tour_date`]"
                                        />
                                    </div>

                                    <!-- Capacity -->
                                    <div class="grid gap-2">
                                        <Label>Capacity</Label>
                                        <Input
                                            v-model.number="tourDate.capacity"
                                            type="number"
                                            min="1"
                                        />
                                        <InputError
                                            :message="(form.errors as any)[`tour_dates.${tdIndex}.capacity`]"
                                        />
                                    </div>
                                </div>

                                <Button
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    :disabled="Boolean(tourDate.bookings_count && tourDate.bookings_count > 0)"
                                    :title="tourDate.bookings_count && tourDate.bookings_count > 0 ? 'Cannot remove date with active bookings' : 'Remove tour date'"
                                    :class="tourDate.bookings_count && tourDate.bookings_count > 0 ? 'opacity-40 cursor-not-allowed' : ''"
                                    @click="removeTourDate(tdIndex)"
                                >
                                    <Trash2 class="h-4 w-4 text-destructive" />
                                </Button>
                            </div>


                            <!-- Times -->

                            <div class="space-y-2 rounded-md bg-muted/40 p-3">

                                <div class="flex items-center justify-between">

                                    <Label class="text-xs uppercase text-muted-foreground">
                                        Times
                                    </Label>

                                    <Button type="button" size="sm" variant="ghost" @click="
                                        addTime(
                                            tdIndex
                                        )
                                        ">

                                        <Plus class="mr-1 h-3 w-3" />

                                        Add time

                                    </Button>

                                </div>


                                <div v-for="(time, tIndex) in tourDate.times" :key="tIndex" class="space-y-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <Input v-model="time.time" type="time" class="w-40 shrink-0" />
                                        <Input v-model="time.description"
                                            placeholder="e.g. Breakfast, Snorkeling, Free time"
                                            class="min-w-[8rem] flex-1" />

                                        <Button type="button" size="icon" variant="ghost"
                                            @click="removeTime(tdIndex, tIndex)">
                                            <X class="h-4 w-4 text-destructive" />
                                        </Button>
                                    </div>

                                    <InputError
                                        :message="(form.errors as any)[`tour_dates.${tdIndex}.times.${tIndex}.time`]" />

                                    <InputError
                                        :message="(form.errors as any)[`tour_dates.${tdIndex}.times.${tIndex}.description`]" />
                                </div>

                            </div>


                            <!-- Pickup Schedules -->

                            <div class="space-y-2 rounded-md bg-muted/40 p-3">

                                <div class="flex items-center justify-between">

                                    <Label class="text-xs uppercase text-muted-foreground">
                                        Pickup schedules
                                    </Label>

                                    <Button type="button" size="sm" variant="ghost" @click="
                                        addPickup(
                                            tdIndex
                                        )
                                        ">

                                        <Plus class="mr-1 h-3 w-3" />

                                        Add pickup

                                    </Button>

                                </div>


                                <div
                                    v-for="(pickup, pIndex) in tourDate.pickup_schedules"
                                    :key="pIndex"
                                    class="space-y-2"
                                >
                                    <div class="flex flex-wrap items-center gap-2">
                                        <Select
                                            :model-value="pickup.pickup_location_id"
                                            :disabled="Boolean(pickup.bookings_count && pickup.bookings_count > 0)"
                                            @update:model-value="
                                                (v) => (pickup.pickup_location_id = String(v))
                                            "
                                        >
                                            <SelectTrigger
                                                class="w-full min-w-[10rem] flex-1"
                                                :class="pickup.bookings_count && pickup.bookings_count > 0 ? 'opacity-85 bg-muted/40 cursor-not-allowed' : ''"
                                            >
                                                <SelectValue placeholder="Pickup location" />
                                            </SelectTrigger>

                                            <SelectContent>
                                                <SelectItem
                                                    v-for="loc in pickupLocations"
                                                    :key="loc.id"
                                                    :value="loc.id.toString()"
                                                >
                                                    {{ loc.name }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>

                                        <Input
                                            v-model="pickup.pickup_time"
                                            type="time"
                                            class="w-40 shrink-0"
                                        />

                                        <Button
                                            type="button"
                                            size="icon"
                                            variant="ghost"
                                            :disabled="Boolean(pickup.bookings_count && pickup.bookings_count > 0)"
                                            :title="pickup.bookings_count && pickup.bookings_count > 0 ? 'Cannot remove pickup schedule with active bookings' : 'Remove pickup'"
                                            :class="pickup.bookings_count && pickup.bookings_count > 0 ? 'opacity-40 cursor-not-allowed' : ''"
                                            @click="removePickup(tdIndex, pIndex)"
                                        >
                                            <X class="h-4 w-4 text-destructive" />
                                        </Button>
                                    </div>

                                    <p
                                        v-if="pickup.bookings_count && pickup.bookings_count > 0"
                                        class="text-[11px] text-muted-foreground flex items-center gap-1"
                                    >
                                        <Lock class="h-3 w-3 text-amber-500 shrink-0" />
                                        <span>Location locked due to {{ pickup.bookings_count }} active reservation{{ pickup.bookings_count > 1 ? 's' : '' }} (pickup time can still be edited).</span>
                                    </p>

                                    <InputError :message="(form.errors as any)[
                                        `tour_dates.${tdIndex}.pickup_schedules.${pIndex}.pickup_location_id`
                                    ]" />

                                    <InputError :message="(form.errors as any)[
                                        `tour_dates.${tdIndex}.pickup_schedules.${pIndex}.pickup_time`
                                    ]" />
                                </div>

                            </div>

                        </div>

                    </div>


                    <p v-if="
                        !form.tour_dates.length
                    " class="text-sm text-muted-foreground">
                        No tour dates added yet.
                    </p>

                </CardContent>

            </Card>

        </div>


        <!-- ==========================================================
             ACTIONS
        =========================================================== -->

        <div class="flex items-center justify-end gap-3">

            <Button v-if="cancelHref" as-child variant="outline" type="button" :disabled="form.processing">

                <Link :href="cancelHref">
                    Cancel
                </Link>

            </Button>


            <Button v-else-if="onCancel" variant="outline" type="button" :disabled="form.processing" @click="onCancel">
                Cancel
            </Button>


            <Button type="submit" :disabled="form.processing">

                <Spinner v-if="form.processing" />

                {{ submitLabel }}

            </Button>

        </div>

    </form>

</template>