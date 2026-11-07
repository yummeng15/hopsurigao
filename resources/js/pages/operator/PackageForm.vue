<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft, ImagePlus, Save, X } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import PackagePhotoPlaceholder from '@/components/packages/PackagePhotoPlaceholder.vue';

type PackageRecord = {
    id: number;
    package_name: string;
    boat_name: string | null;
    destinations_text: string;
    description: string | null;
    price: string;
    status: string;
    cover_photo_url?: string | null;
    cover_photo_path?: string | null;
};

const props = defineProps<{
    mode: 'create' | 'edit';
    package: PackageRecord | null;
    hasActiveBookings: boolean;
}>();

const isEdit = computed(() => props.mode === 'edit');

const form = useForm({
    package_name: props.package?.package_name ?? '',
    boat_name: props.package?.boat_name ?? '',
    destinations_text: props.package?.destinations_text ?? '',
    description: props.package?.description ?? '',
    price: props.package?.price ?? '',
    cover_photo: null as File | null,
});

const fileInput = ref<HTMLInputElement | null>(null);
const localPreviewUrl = ref<string | null>(null);
const clientPhotoError = ref<string | null>(null);

const ALLOWED_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
const MAX_BYTES = 5 * 1024 * 1024;

const previewUrl = computed(
    () => localPreviewUrl.value ?? props.package?.cover_photo_url ?? null,
);

watch(
    () => form.cover_photo,
    (file) => {
        if (localPreviewUrl.value) {
            URL.revokeObjectURL(localPreviewUrl.value);
            localPreviewUrl.value = null;
        }

        if (file instanceof File) {
            localPreviewUrl.value = URL.createObjectURL(file);
        }
    },
);

onBeforeUnmount(() => {
    if (localPreviewUrl.value) {
        URL.revokeObjectURL(localPreviewUrl.value);
    }
});

function openFilePicker(): void {
    fileInput.value?.click();
}

function clearSelectedPhoto(): void {
    form.cover_photo = null;
    clientPhotoError.value = null;

    if (fileInput.value) {
        fileInput.value.value = '';
    }
}

function onPhotoSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;

    clientPhotoError.value = null;

    if (! file) {
        form.cover_photo = null;
        return;
    }

    if (! ALLOWED_TYPES.includes(file.type)) {
        clientPhotoError.value = 'Please choose a JPG, JPEG, PNG, or WebP image.';
        form.cover_photo = null;
        input.value = '';
        return;
    }

    if (file.size > MAX_BYTES) {
        clientPhotoError.value = 'The package photo must be 5 MB or smaller.';
        form.cover_photo = null;
        input.value = '';
        return;
    }

    form.cover_photo = file;
}

function submit(): void {
    if (form.processing) {
        return;
    }

    if (isEdit.value && props.package) {
        form
            .transform((data) => ({
                ...data,
                _method: 'put',
            }))
            .post(`/operator/packages/${props.package.id}`, {
                forceFormData: true,
                preserveScroll: true,
            });

        return;
    }

    form.post('/operator/packages', {
        forceFormData: true,
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="isEdit ? 'Edit Tour Package' : 'Create Tour Package'" />

    <main class="mx-auto w-full max-w-3xl space-y-6 px-4 py-6 sm:px-6 lg:py-8">
        <header class="space-y-3">
            <Link
                href="/operator/packages"
                class="inline-flex items-center gap-2 text-sm font-medium text-primary hover:underline"
            >
                <ArrowLeft class="size-4" />
                Back to my packages
            </Link>

            <h1 class="text-3xl font-bold tracking-tight">
                {{ isEdit ? 'Edit Tour Package' : 'Create Tour Package' }}
            </h1>

            <p class="text-sm text-slate-500 dark:text-slate-400">
                Enter your tour package details. After creating it, add a tour
                schedule and publish it to accept reservations.
            </p>
        </header>

        <div
            v-if="!isEdit"
            class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200"
        >
            Your package will first be saved as a <strong>draft</strong>. After
            adding a future tour date, departure time, and pickup location,
            select <strong>Publish Package</strong> from My Tour Packages so
            tourists can book it.
        </div>

        <div
            v-if="hasActiveBookings"
            class="flex gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200"
        >
            <AlertTriangle class="mt-0.5 size-5 shrink-0" />
            <p>
                This package has pending or confirmed reservations. Boat,
                destinations, and price cannot be changed while those
                reservations remain active. You can still update the package
                name, description, and photo.
            </p>
        </div>

        <form
            class="space-y-5 rounded-2xl border bg-white p-5 shadow-sm sm:p-7 dark:border-slate-800 dark:bg-slate-900"
            @submit.prevent="submit"
        >
            <div class="space-y-2">
                <label class="block text-sm font-semibold">
                    Package Photo
                </label>

                <div class="overflow-hidden rounded-2xl border dark:border-slate-700">
                    <div class="aspect-video bg-muted">
                        <img
                            v-if="previewUrl"
                            :src="previewUrl"
                            alt="Package photo preview"
                            class="h-full w-full object-cover"
                        />
                        <PackagePhotoPlaceholder
                            v-else
                            label="No package photo yet"
                        />
                    </div>

                    <div class="flex flex-wrap items-center gap-3 border-t bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-950/40">
                        <input
                            ref="fileInput"
                            type="file"
                            accept="image/jpeg,image/jpg,image/png,image/webp"
                            class="hidden"
                            @change="onPhotoSelected"
                        />

                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-lg border bg-white px-3 py-2 text-sm font-medium hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:hover:bg-slate-800"
                            @click="openFilePicker"
                        >
                            <ImagePlus class="size-4 text-primary" />
                            {{ previewUrl ? 'Replace photo' : 'Upload photo' }}
                        </button>

                        <button
                            v-if="form.cover_photo"
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg px-2 py-2 text-sm text-slate-500 hover:text-red-600"
                            @click="clearSelectedPhoto"
                        >
                            <X class="size-4" />
                            Clear selection
                        </button>

                        <p class="text-xs text-slate-500">
                            JPG, JPEG, PNG, or WebP. Max 5 MB.
                            {{ isEdit ? 'Leave empty to keep the current photo.' : 'Optional.' }}
                        </p>
                    </div>
                </div>

                <p
                    v-if="clientPhotoError || form.errors.cover_photo"
                    class="text-sm text-red-600"
                >
                    {{ clientPhotoError || form.errors.cover_photo }}
                </p>
            </div>

            <div class="space-y-2">
                <label for="package_name" class="block text-sm font-semibold">
                    Package name <span class="text-red-600">*</span>
                </label>

                <input
                    id="package_name"
                    v-model="form.package_name"
                    type="text"
                    required
                    maxlength="255"
                    class="w-full rounded-lg border bg-transparent px-3 py-2.5 outline-none focus:ring-2 focus:ring-cyan-600 dark:border-slate-700"
                />

                <p v-if="form.errors.package_name" class="text-sm text-red-600">
                    {{ form.errors.package_name }}
                </p>
            </div>

            <div class="space-y-2">
                <label for="boat_name" class="block text-sm font-semibold">
                    Boat name <span class="text-red-600">*</span>
                </label>

                <input
                    id="boat_name"
                    v-model="form.boat_name"
                    type="text"
                    required
                    maxlength="255"
                    :disabled="hasActiveBookings"
                    class="w-full rounded-lg border bg-transparent px-3 py-2.5 outline-none focus:ring-2 focus:ring-cyan-600 disabled:opacity-60 dark:border-slate-700"
                />

                <p v-if="form.errors.boat_name" class="text-sm text-red-600">
                    {{ form.errors.boat_name }}
                </p>
            </div>

            <div class="space-y-2">
                <label
                    for="destinations_text"
                    class="block text-sm font-semibold"
                >
                    Destinations <span class="text-red-600">*</span>
                </label>

                <textarea
                    id="destinations_text"
                    v-model="form.destinations_text"
                    required
                    rows="5"
                    maxlength="2000"
                    :disabled="hasActiveBookings"
                    placeholder="Daku Island&#10;Guyam Island&#10;Naked Island"
                    class="w-full rounded-lg border bg-transparent px-3 py-2.5 outline-none focus:ring-2 focus:ring-cyan-600 disabled:opacity-60 dark:border-slate-700"
                ></textarea>

                <p class="text-xs text-slate-500">
                    Enter one destination per line (maximum 12).
                </p>

                <p
                    v-if="form.errors.destinations_text"
                    class="text-sm text-red-600"
                >
                    {{ form.errors.destinations_text }}
                </p>
            </div>

            <div class="space-y-2">
                <label for="price" class="block text-sm font-semibold">
                    Price (PHP) <span class="text-red-600">*</span>
                </label>

                <input
                    id="price"
                    v-model="form.price"
                    type="number"
                    required
                    min="0"
                    max="99999999.99"
                    step="0.01"
                    :disabled="hasActiveBookings"
                    class="w-full rounded-lg border bg-transparent px-3 py-2.5 outline-none focus:ring-2 focus:ring-cyan-600 disabled:opacity-60 dark:border-slate-700"
                />

                <p v-if="form.errors.price" class="text-sm text-red-600">
                    {{ form.errors.price }}
                </p>
            </div>

            <div class="space-y-2">
                <label for="description" class="block text-sm font-semibold">
                    Description
                </label>

                <textarea
                    id="description"
                    v-model="form.description"
                    rows="4"
                    maxlength="10000"
                    class="w-full rounded-lg border bg-transparent px-3 py-2.5 outline-none focus:ring-2 focus:ring-cyan-600 dark:border-slate-700"
                ></textarea>

                <p v-if="form.errors.description" class="text-sm text-red-600">
                    {{ form.errors.description }}
                </p>
            </div>

            <div
                class="flex flex-wrap items-center justify-end gap-3 border-t pt-5 dark:border-slate-700"
            >
                <Link
                    href="/operator/packages"
                    class="rounded-lg border px-4 py-2.5 text-sm font-medium hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800"
                >
                    Cancel
                </Link>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <Save class="size-4" />
                    {{
                        form.processing
                            ? 'Saving...'
                            : isEdit
                              ? 'Save changes'
                              : 'Create Package'
                    }}
                </button>
            </div>
        </form>
    </main>
</template>
