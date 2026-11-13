<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import { Pencil, Building2 } from '@lucide/vue';

interface PackageItem {
    id: number;
    package_name: string;
}

interface Business {
    id: number;
    name: string;
    contact_email: string | null;
    contact_phone: string | null;
    description: string | null;
    approval_status: string;
    status_label: string;
    rejection_reason: string | null;
    packages: PackageItem[];
}

const props = defineProps<{
    business: Business | null;
    unassignedPackages: PackageItem[];
}>();

const isEditing = ref(false);

const form = useForm({
    name: props.business?.name ?? '',
    contact_email: props.business?.contact_email ?? '',
    contact_phone: props.business?.contact_phone ?? '',
    description: props.business?.description ?? '',
});

const requestForm = useForm({
    package_id: '' as string | number,
});

const showRegistrationForm = computed(
    () => props.business === null || isEditing.value,
);

const pageDescription = computed(() => {
    if (props.business === null) {
        return 'Register the tour operator business this account will manage.';
    }

    if (isEditing.value) {
        return 'Update your business details, then save to return to the profile view.';
    }

    return 'Review your registered tour operator business details.';
});

watch(
    () => props.business,
    (business) => {
        // Fresh Inertia props after save/login always land on the read-only view.
        isEditing.value = false;

        form.defaults({
            name: business?.name ?? '',
            contact_email: business?.contact_email ?? '',
            contact_phone: business?.contact_phone ?? '',
            description: business?.description ?? '',
        });
        form.reset();
        form.clearErrors();
    },
    { deep: true },
);

function startEditing(): void {
    if (! props.business) {
        return;
    }

    form.defaults({
        name: props.business.name,
        contact_email: props.business.contact_email ?? '',
        contact_phone: props.business.contact_phone ?? '',
        description: props.business.description ?? '',
    });
    form.reset();
    form.clearErrors();
    isEditing.value = true;
}

function cancelEditing(): void {
    if (! props.business) {
        return;
    }

    form.reset();
    form.clearErrors();
    isEditing.value = false;
}

function saveBusiness(): void {
    if (props.business) {
        form.patch('/operator/business', {
            preserveScroll: true,
            onSuccess: () => {
                isEditing.value = false;
            },
        });

        return;
    }

    form.post('/operator/business', {
        preserveScroll: true,
        onSuccess: () => {
            isEditing.value = false;
        },
    });
}

function requestPackage(): void {
    requestForm.post('/operator/business/package-requests', {
        preserveScroll: true,
        onSuccess: () => {
            requestForm.reset();
        },
    });
}

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'My Business', href: '/operator/business' }],
    },
});
</script>

<template>
    <Head title="My Business" />

    <div class="mx-auto max-w-3xl space-y-6 px-4 py-7 text-foreground sm:px-6">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-foreground">
                My Business
            </h1>
            <p class="mt-2 text-sm text-muted-foreground">
                {{ pageDescription }}
            </p>
        </div>

        <!-- Empty registration -->
        <section
            v-if="business === null"
            class="rounded-2xl border border-border bg-card p-5 sm:p-6"
        >
            <p class="text-sm font-semibold text-foreground">
                Business Registration Status:
                <span class="text-foreground">Unassigned</span>
            </p>

            <form
                class="mt-5 space-y-4"
                @submit.prevent="saveBusiness"
            >
                <div>
                    <label
                        class="mb-1 block text-sm font-semibold text-foreground"
                        for="business-name"
                    >
                        Business Name
                    </label>
                    <input
                        id="business-name"
                        v-model="form.name"
                        required
                        maxlength="150"
                        class="w-full rounded-xl border border-input bg-background px-4 py-2.5 text-sm text-foreground"
                    />
                    <p
                        v-if="form.errors.name"
                        class="mt-1 text-xs text-destructive"
                    >
                        {{ form.errors.name }}
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label
                            class="mb-1 block text-sm font-semibold text-foreground"
                            for="business-email"
                        >
                            Contact Email
                        </label>
                        <input
                            id="business-email"
                            v-model="form.contact_email"
                            required
                            type="email"
                            class="w-full rounded-xl border border-input bg-background px-4 py-2.5 text-sm text-foreground"
                        />
                        <p
                            v-if="form.errors.contact_email"
                            class="mt-1 text-xs text-destructive"
                        >
                            {{ form.errors.contact_email }}
                        </p>
                    </div>

                    <div>
                        <label
                            class="mb-1 block text-sm font-semibold text-foreground"
                            for="business-phone"
                        >
                            Contact Number
                        </label>
                        <input
                            id="business-phone"
                            v-model="form.contact_phone"
                            required
                            maxlength="30"
                            class="w-full rounded-xl border border-input bg-background px-4 py-2.5 text-sm text-foreground"
                        />
                        <p
                            v-if="form.errors.contact_phone"
                            class="mt-1 text-xs text-destructive"
                        >
                            {{ form.errors.contact_phone }}
                        </p>
                    </div>
                </div>

                <div>
                    <label
                        class="mb-1 block text-sm font-semibold text-foreground"
                        for="business-description"
                    >
                        Business Description
                    </label>
                    <textarea
                        id="business-description"
                        v-model="form.description"
                        rows="4"
                        class="w-full rounded-xl border border-input bg-background px-4 py-2.5 text-sm text-foreground"
                    />
                </div>

                <button
                    type="submit"
                    class="rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground disabled:opacity-60"
                    :disabled="form.processing"
                >
                    Save Business Details
                </button>
            </form>
        </section>

        <!-- Saved business: read-only -->
        <section
            v-else-if="!showRegistrationForm"
            class="rounded-2xl border border-border bg-card p-5 sm:p-6"
        >
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex items-start gap-3">
                    <div class="rounded-xl border border-border bg-muted/40 p-2.5">
                        <Building2 class="h-5 w-5 text-primary" />
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-foreground">
                            Business Information
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Status:
                            <span class="font-semibold text-foreground">
                                {{ business.status_label }}
                            </span>
                        </p>
                    </div>
                </div>

                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl border border-border bg-background px-3 py-2 text-sm font-semibold text-foreground hover:bg-muted"
                    @click="startEditing"
                >
                    <Pencil class="h-4 w-4" />
                    Edit Details
                </button>
            </div>

            <p
                v-if="business.rejection_reason"
                class="mt-4 rounded-xl border border-destructive/30 bg-destructive/5 px-3 py-2 text-sm text-destructive"
            >
                Rejection reason: {{ business.rejection_reason }}
            </p>
            <p
                v-if="business.approval_status === 'rejected'"
                class="mt-2 text-sm text-muted-foreground"
            >
                Update the details and save to resubmit the application.
            </p>

            <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                <div class="rounded-xl border border-border p-3 sm:col-span-2">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        Business name
                    </dt>
                    <dd class="mt-1 text-sm font-semibold text-foreground">
                        {{ business.name }}
                    </dd>
                </div>
                <div class="rounded-xl border border-border p-3">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        Contact email
                    </dt>
                    <dd class="mt-1 break-all text-sm font-semibold text-foreground">
                        {{ business.contact_email || '—' }}
                    </dd>
                </div>
                <div class="rounded-xl border border-border p-3">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        Contact number
                    </dt>
                    <dd class="mt-1 text-sm font-semibold text-foreground">
                        {{ business.contact_phone || '—' }}
                    </dd>
                </div>
                <div class="rounded-xl border border-border p-3 sm:col-span-2">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        Description
                    </dt>
                    <dd class="mt-1 whitespace-pre-wrap text-sm text-foreground">
                        {{ business.description || '—' }}
                    </dd>
                </div>
                <div class="rounded-xl border border-border p-3 sm:col-span-2">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        Registration status
                    </dt>
                    <dd class="mt-1 text-sm font-semibold text-foreground">
                        {{ business.status_label }}
                    </dd>
                </div>
            </dl>
        </section>

        <!-- Saved business: edit form -->
        <section
            v-else
            class="rounded-2xl border border-border bg-card p-5 sm:p-6"
        >
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold text-foreground">
                        Edit Business Details
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Status:
                        <span class="font-semibold text-foreground">
                            {{ business.status_label }}
                        </span>
                    </p>
                </div>
                <button
                    type="button"
                    class="rounded-xl border border-border bg-background px-3 py-2 text-sm font-semibold text-foreground hover:bg-muted"
                    @click="cancelEditing"
                >
                    Cancel
                </button>
            </div>

            <p
                v-if="business.rejection_reason"
                class="mt-4 text-sm text-destructive"
            >
                Rejection reason: {{ business.rejection_reason }}
            </p>
            <p
                v-if="business.approval_status === 'rejected'"
                class="mt-1 text-sm text-muted-foreground"
            >
                Update the details and save to resubmit the application.
            </p>

            <form
                class="mt-5 space-y-4"
                @submit.prevent="saveBusiness"
            >
                <div>
                    <label
                        class="mb-1 block text-sm font-semibold text-foreground"
                        for="edit-business-name"
                    >
                        Business Name
                    </label>
                    <input
                        id="edit-business-name"
                        v-model="form.name"
                        required
                        maxlength="150"
                        class="w-full rounded-xl border border-input bg-background px-4 py-2.5 text-sm text-foreground"
                    />
                    <p
                        v-if="form.errors.name"
                        class="mt-1 text-xs text-destructive"
                    >
                        {{ form.errors.name }}
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label
                            class="mb-1 block text-sm font-semibold text-foreground"
                            for="edit-business-email"
                        >
                            Contact Email
                        </label>
                        <input
                            id="edit-business-email"
                            v-model="form.contact_email"
                            required
                            type="email"
                            class="w-full rounded-xl border border-input bg-background px-4 py-2.5 text-sm text-foreground"
                        />
                        <p
                            v-if="form.errors.contact_email"
                            class="mt-1 text-xs text-destructive"
                        >
                            {{ form.errors.contact_email }}
                        </p>
                    </div>

                    <div>
                        <label
                            class="mb-1 block text-sm font-semibold text-foreground"
                            for="edit-business-phone"
                        >
                            Contact Number
                        </label>
                        <input
                            id="edit-business-phone"
                            v-model="form.contact_phone"
                            required
                            maxlength="30"
                            class="w-full rounded-xl border border-input bg-background px-4 py-2.5 text-sm text-foreground"
                        />
                        <p
                            v-if="form.errors.contact_phone"
                            class="mt-1 text-xs text-destructive"
                        >
                            {{ form.errors.contact_phone }}
                        </p>
                    </div>
                </div>

                <div>
                    <label
                        class="mb-1 block text-sm font-semibold text-foreground"
                        for="edit-business-description"
                    >
                        Business Description
                    </label>
                    <textarea
                        id="edit-business-description"
                        v-model="form.description"
                        rows="4"
                        class="w-full rounded-xl border border-input bg-background px-4 py-2.5 text-sm text-foreground"
                    />
                </div>

                <div class="flex flex-wrap gap-3">
                    <button
                        type="submit"
                        class="rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground disabled:opacity-60"
                        :disabled="form.processing"
                    >
                        Save Business Details
                    </button>
                    <button
                        type="button"
                        class="rounded-xl border border-border px-4 py-2.5 text-sm font-semibold text-foreground hover:bg-muted"
                        :disabled="form.processing"
                        @click="cancelEditing"
                    >
                        Cancel
                    </button>
                </div>
            </form>
        </section>

        <section
            v-if="business"
            class="rounded-2xl border border-border bg-card p-5 sm:p-6"
        >
            <h2 class="text-base font-bold text-foreground">
                Assigned Packages
            </h2>
            <p
                v-if="business.packages.length === 0"
                class="mt-2 text-sm text-muted-foreground"
            >
                Unassigned
            </p>
            <ul
                v-else
                class="mt-2 space-y-1 text-sm text-foreground"
            >
                <li
                    v-for="item in business.packages"
                    :key="item.id"
                >
                    {{ item.package_name }}
                </li>
            </ul>

            <form
                v-if="business.approval_status === 'active' && unassignedPackages.length > 0"
                class="mt-5 space-y-3"
                @submit.prevent="requestPackage"
            >
                <label
                    class="block text-sm font-semibold text-foreground"
                    for="package-request"
                >
                    Request an unassigned package
                </label>
                <select
                    id="package-request"
                    v-model="requestForm.package_id"
                    required
                    class="w-full rounded-xl border border-input bg-background px-4 py-2.5 text-sm text-foreground"
                >
                    <option value="">
                        Select a package
                    </option>
                    <option
                        v-for="item in unassignedPackages"
                        :key="item.id"
                        :value="item.id"
                    >
                        {{ item.package_name }}
                    </option>
                </select>
                <p
                    v-if="requestForm.errors.package_id"
                    class="text-xs text-destructive"
                >
                    {{ requestForm.errors.package_id }}
                </p>
                <button
                    type="submit"
                    class="rounded-xl border border-primary px-4 py-2 text-sm font-semibold text-primary"
                    :disabled="requestForm.processing"
                >
                    Request ownership
                </button>
            </form>
        </section>
    </div>
</template>
