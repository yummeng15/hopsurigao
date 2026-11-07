
<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, AlertTriangle, Save } from '@lucide/vue';
import { computed } from 'vue';

type PackageRecord = {
    id: number;
    package_name: string;
    boat_name: string | null;
    destinations_text: string;
    description: string | null;
    price: string;
    status: string;
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
});

function submit(): void {
    if (form.processing) return;

    if (isEdit.value && props.package) {
        form.put(`/operator/packages/${props.package.id}`, {
            preserveScroll: true,
        });
    } else {
        form.post('/operator/packages', {
            preserveScroll: true,
        });
    }
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
                Manage basic tour details here. Schedules, pickup times,
                and images will be moved in a separate stage.
            </p>
        </header>

        <div
            v-if="!isEdit"
            class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200"
        >
            New packages are saved as <strong>inactive</strong>
            until tour schedules are configured and the package
            is ready to publish.
        </div>

        <div
            v-if="hasActiveBookings"
            class="flex gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200"
        >
            <AlertTriangle class="mt-0.5 size-5 shrink-0" />
            <p>
                This package has pending or confirmed reservations.
                Boat, destinations, and price cannot be changed
                while those reservations remain active.
                You can still update the package name and description.
            </p>
        </div>

        <form
            class="space-y-5 rounded-2xl border bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-7"
            @submit.prevent="submit"
        >
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
                <label for="destinations_text" class="block text-sm font-semibold">
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

                <p v-if="form.errors.destinations_text" class="text-sm text-red-600">
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

            <div class="flex flex-wrap items-center justify-end gap-3 border-t pt-5 dark:border-slate-700">
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
                    {{ form.processing ? 'Saving...' : (isEdit ? 'Save changes' : 'Create inactive package') }}
                </button>
            </div>
        </form>
    </main>
</template>
