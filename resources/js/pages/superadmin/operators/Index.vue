<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Building2 } from '@lucide/vue';
import { ref } from 'vue';

interface Admin {
    id: number;
    name: string;
}

interface TourPackage {
    id: number;
    package_name: string;
}

interface TourOperator {
    id: number;
    name: string;
    description: string | null;
    contact_email: string | null;
    contact_phone: string | null;
    approval_status: string;
    status_label: string;
    rejection_reason: string | null;
    bookings_count: number;
    admins: Admin[];
    packages: TourPackage[];
}

interface PackageAssignment {
    id: number;
    package_name: string;
    operator_name: string | null;
    admin_name: string | null;
    assignment_status: string;
}

interface OwnershipRequest {
    id: number;
    package_name: string | null;
    operator_name: string | null;
    admin_name: string | null;
}

const props = defineProps<{
    operators: TourOperator[];
    packageAssignments: PackageAssignment[];
    ownershipRequests: OwnershipRequest[];
    filters: { search: string; status: string };
}>();

const search = ref(props.filters.search);
const status = ref(props.filters.status);
const rejectingId = ref<number | null>(null);
const rejectForm = useForm({
    decision: 'reject',
    rejection_reason: '',
});

function applyFilters(): void {
    router.get('/superadmin/operators', {
        search: search.value || undefined,
        status: status.value || undefined,
    }, { preserveState: true, preserveScroll: true });
}

function decide(operatorId: number, decision: string): void {
    router.patch(`/superadmin/operators/${operatorId}/decision`, { decision }, { preserveScroll: true });
}

function submitRejection(operatorId: number): void {
    rejectForm.patch(`/superadmin/operators/${operatorId}/decision`, {
        preserveScroll: true,
        onSuccess: () => {
            rejectingId.value = null;
            rejectForm.reset();
        },
    });
}

function reviewRequest(requestId: number, decision: 'approve' | 'reject'): void {
    router.patch(`/superadmin/package-ownership-requests/${requestId}`, { decision }, { preserveScroll: true });
}

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Tour Operators', href: '/superadmin/operators' }],
    },
});
</script>

<template>
    <Head title="Tour Operators" />

    <div class="mx-auto max-w-7xl space-y-6 px-4 py-7 text-foreground sm:px-6 lg:px-8">
        <div>
            <div class="mb-2 flex items-center gap-2 text-sm font-medium text-cyan-700 dark:text-cyan-300">
                <Building2 class="h-4 w-4" />
                Platform Management
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">Tour Operators</h1>
            <p class="mt-2 text-sm text-foreground/70">Review registrations, then approve, reject, suspend, or reactivate a business.</p>
        </div>

        <form class="flex flex-col gap-3 rounded-3xl border border-border bg-card p-4 shadow-sm sm:flex-row" @submit.prevent="applyFilters">
            <input
                v-model="search"
                type="search"
                placeholder="Search business or email"
                class="w-full rounded-xl border border-border bg-card px-4 py-2.5 text-sm text-foreground placeholder:text-foreground/50"
            />
            <select
                v-model="status"
                class="rounded-xl border border-border bg-card px-4 py-2.5 text-sm text-foreground"
            >
                <option value="">All statuses</option>
                <option value="pending">Pending Approval</option>
                <option value="active">Active</option>
                <option value="rejected">Rejected</option>
                <option value="suspended">Suspended</option>
            </select>
            <button type="submit" class="rounded-xl bg-[#00FFFF] px-4 py-2.5 text-sm font-semibold text-cyan-950">Filter</button>
        </form>

        <section class="overflow-hidden rounded-3xl border border-border bg-card shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm text-foreground">
                    <thead class="border-b border-border text-xs uppercase tracking-wide text-foreground/65">
                        <tr>
                            <th class="px-4 py-3">Business Name</th>
                            <th class="px-4 py-3">Registered Operator Admin</th>
                            <th class="px-4 py-3">Contact Information</th>
                            <th class="px-4 py-3">Assigned Packages</th>
                            <th class="px-4 py-3">Approval Status</th>
                            <th class="px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="operator in operators" :key="operator.id" class="border-t border-border align-top">
                            <td class="px-4 py-3">
                                <p class="font-semibold text-foreground">{{ operator.name }}</p>
                                <p class="mt-1 text-xs text-foreground/70">{{ operator.bookings_count }} existing booking{{ operator.bookings_count === 1 ? '' : 's' }}</p>
                                <p v-if="operator.description" class="mt-1 max-w-xs text-xs text-foreground/70">{{ operator.description }}</p>
                            </td>
                            <td class="px-4 py-3 text-foreground">
                                {{ operator.admins.map((admin) => admin.name).join(', ') || 'Unassigned' }}
                            </td>
                            <td class="px-4 py-3 text-foreground">
                                <p>{{ operator.contact_email || 'Unassigned' }}</p>
                                <p>{{ operator.contact_phone || 'Unassigned' }}</p>
                            </td>
                            <td class="px-4 py-3 text-foreground">
                                <p v-if="operator.packages.length === 0">Unassigned</p>
                                <p v-for="item in operator.packages" :key="item.id">{{ item.package_name }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full border border-cyan-300 bg-cyan-50 px-2.5 py-1 text-xs font-bold text-cyan-800 dark:border-cyan-700 dark:bg-cyan-950/40 dark:text-cyan-200">{{ operator.status_label }}</span>
                                <p v-if="operator.rejection_reason" class="mt-2 max-w-xs text-xs text-destructive">{{ operator.rejection_reason }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-2">
                                    <button v-if="operator.approval_status === 'pending'" type="button" class="rounded-lg bg-[#00FFFF] px-3 py-1.5 text-xs font-semibold text-cyan-950" @click="decide(operator.id, 'approve')">Approve</button>
                                    <button v-if="operator.approval_status === 'pending'" type="button" class="rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-foreground" @click="rejectingId = operator.id">Reject</button>
                                    <button v-if="operator.approval_status === 'active'" type="button" class="rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-foreground" @click="decide(operator.id, 'suspend')">Suspend</button>
                                    <button v-if="operator.approval_status === 'suspended'" type="button" class="rounded-lg bg-[#00FFFF] px-3 py-1.5 text-xs font-semibold text-cyan-950" @click="decide(operator.id, 'reactivate')">Reactivate</button>
                                </div>
                                <form v-if="rejectingId === operator.id" class="mt-3 space-y-2" @submit.prevent="submitRejection(operator.id)">
                                    <textarea
                                        v-model="rejectForm.rejection_reason"
                                        required
                                        rows="2"
                                        placeholder="Rejection reason"
                                        class="w-full rounded-lg border border-border bg-card px-3 py-2 text-xs text-foreground placeholder:text-foreground/50"
                                    />
                                    <p v-if="rejectForm.errors.rejection_reason" class="text-xs text-destructive">{{ rejectForm.errors.rejection_reason }}</p>
                                    <button type="submit" class="rounded-lg bg-foreground px-3 py-1.5 text-xs font-semibold text-background" :disabled="rejectForm.processing">Save rejection</button>
                                </form>
                            </td>
                        </tr>
                        <tr v-if="operators.length === 0">
                            <td colspan="6" class="px-4 py-8 text-center text-foreground/70">No businesses match this filter.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-3xl border border-border bg-card p-5 shadow-sm">
            <h2 class="text-base font-bold text-foreground">Package ownership requests</h2>
            <p v-if="ownershipRequests.length === 0" class="mt-2 text-sm text-foreground/70">No pending requests.</p>
            <div v-for="item in ownershipRequests" :key="item.id" class="mt-3 flex flex-col gap-2 border-t border-border pt-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-foreground">
                    <span class="font-semibold">{{ item.package_name }}</span>
                    requested by {{ item.operator_name }} ({{ item.admin_name }})
                </p>
                <div class="flex gap-2">
                    <button type="button" class="rounded-lg bg-[#00FFFF] px-3 py-1.5 text-xs font-semibold text-cyan-950" @click="reviewRequest(item.id, 'approve')">Approve</button>
                    <button type="button" class="rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-foreground" @click="reviewRequest(item.id, 'reject')">Reject</button>
                </div>
            </div>
        </section>

        <section class="rounded-3xl border border-border bg-card p-5 shadow-sm">
            <h2 class="text-base font-bold text-foreground">Package oversight</h2>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-left text-sm text-foreground">
                    <thead class="border-b border-border text-xs uppercase tracking-wide text-foreground/65">
                        <tr>
                            <th class="py-2 pr-4">Package Name</th>
                            <th class="py-2 pr-4">Assigned Tour Operator Business</th>
                            <th class="py-2 pr-4">Assigned Operator Admin</th>
                            <th class="py-2">Assignment Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in packageAssignments" :key="item.id" class="border-t border-border">
                            <td class="py-2 pr-4 font-medium text-foreground">{{ item.package_name }}</td>
                            <td class="py-2 pr-4 text-foreground">{{ item.operator_name || 'Unassigned' }}</td>
                            <td class="py-2 pr-4 text-foreground">{{ item.admin_name || 'Unassigned' }}</td>
                            <td class="py-2 text-foreground">{{ item.assignment_status }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
