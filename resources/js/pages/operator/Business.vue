<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

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

const form = useForm({
    name: props.business?.name ?? '',
    contact_email: props.business?.contact_email ?? '',
    contact_phone: props.business?.contact_phone ?? '',
    description: props.business?.description ?? '',
});

const requestForm = useForm({
    package_id: '' as string | number,
});

function saveBusiness(): void {
    if (props.business) {
        form.patch('/operator/business', { preserveScroll: true });
        return;
    }

    form.post('/operator/business', { preserveScroll: true });
}

function requestPackage(): void {
    requestForm.post('/operator/business/package-requests', { preserveScroll: true });
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
            <h1 class="text-2xl font-bold tracking-tight text-foreground">My Business</h1>
            <p class="mt-2 text-sm text-muted-foreground">Register the tour operator business this account will manage.</p>
        </div>

        <section class="rounded-2xl border border-border bg-card p-5 sm:p-6">
            <p class="text-sm font-semibold text-foreground">
                Business Registration Status:
                <span class="text-foreground">{{ business?.status_label ?? 'Unassigned' }}</span>
            </p>
            <p v-if="business?.rejection_reason" class="mt-2 text-sm text-destructive">
                Rejection reason: {{ business.rejection_reason }}
            </p>
            <p v-if="business?.approval_status === 'rejected'" class="mt-1 text-sm text-muted-foreground">
                Update the details and save to resubmit the application.
            </p>

            <form class="mt-5 space-y-4" @submit.prevent="saveBusiness">
                <div>
                    <label class="mb-1 block text-sm font-semibold text-foreground" for="business-name">Business Name</label>
                    <input id="business-name" v-model="form.name" required maxlength="150" class="w-full rounded-xl border border-input bg-background px-4 py-2.5 text-sm text-foreground" />
                    <p v-if="form.errors.name" class="mt-1 text-xs text-destructive">{{ form.errors.name }}</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-foreground" for="business-email">Contact Email</label>
                        <input id="business-email" v-model="form.contact_email" required type="email" class="w-full rounded-xl border border-input bg-background px-4 py-2.5 text-sm text-foreground" />
                        <p v-if="form.errors.contact_email" class="mt-1 text-xs text-destructive">{{ form.errors.contact_email }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-foreground" for="business-phone">Contact Number</label>
                        <input id="business-phone" v-model="form.contact_phone" required maxlength="30" class="w-full rounded-xl border border-input bg-background px-4 py-2.5 text-sm text-foreground" />
                        <p v-if="form.errors.contact_phone" class="mt-1 text-xs text-destructive">{{ form.errors.contact_phone }}</p>
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold text-foreground" for="business-description">Business Description</label>
                    <textarea id="business-description" v-model="form.description" rows="4" class="w-full rounded-xl border border-input bg-background px-4 py-2.5 text-sm text-foreground" />
                </div>
                <button type="submit" class="rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground disabled:opacity-60" :disabled="form.processing">
                    {{ business ? 'Save business details' : 'Submit registration' }}
                </button>
            </form>
        </section>

        <section v-if="business" class="rounded-2xl border border-border bg-card p-5 sm:p-6">
            <h2 class="text-base font-bold text-foreground">Assigned Packages</h2>
            <p v-if="business.packages.length === 0" class="mt-2 text-sm text-muted-foreground">Unassigned</p>
            <ul v-else class="mt-2 space-y-1 text-sm text-foreground">
                <li v-for="item in business.packages" :key="item.id">{{ item.package_name }}</li>
            </ul>

            <form v-if="business.approval_status === 'active' && unassignedPackages.length > 0" class="mt-5 space-y-3" @submit.prevent="requestPackage">
                <label class="block text-sm font-semibold text-foreground" for="package-request">Request an unassigned package</label>
                <select id="package-request" v-model="requestForm.package_id" required class="w-full rounded-xl border border-input bg-background px-4 py-2.5 text-sm text-foreground">
                    <option value="">Select a package</option>
                    <option v-for="item in unassignedPackages" :key="item.id" :value="item.id">{{ item.package_name }}</option>
                </select>
                <p v-if="requestForm.errors.package_id" class="text-xs text-destructive">{{ requestForm.errors.package_id }}</p>
                <button type="submit" class="rounded-xl border border-primary px-4 py-2 text-sm font-semibold text-primary" :disabled="requestForm.processing">
                    Request ownership
                </button>
            </form>
        </section>
    </div>
</template>
