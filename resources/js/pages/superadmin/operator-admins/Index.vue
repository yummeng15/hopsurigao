
<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import {
    Building2,
    ShieldCheck,
    UserCheck,
    UserPlus,
    Users,
} from '@lucide/vue';

import { Button } from '@/components/ui/button';

interface TourOperator {
    id: number;
    name: string;
}

interface OperatorAdmin {
    id: number;
    name: string;
    email: string;
    operator_name: string;
    operator_active: boolean;
    is_assigned: boolean;
    created_at: string | null;
}

interface AvailableAdmin {
    id: number;
    name: string;
    email: string;
}

const props = defineProps<{
    operators: TourOperator[];
    admins: OperatorAdmin[];
    unassignedAdmins: AvailableAdmin[];
}>();

const createForm = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    tour_operator_id: '',
});

const selectedAdminId = ref('');

const assignForm = useForm({
    tour_operator_id: '',
});

const selectedAdmin = computed(() =>
    props.unassignedAdmins.find(
        (admin) => String(admin.id) === selectedAdminId.value,
    ),
);

function createAdmin(): void {
    if (createForm.processing) return;

    createForm.post('/superadmin/operator-admins', {
        preserveScroll: true,
        onSuccess: () => createForm.reset(),
    });
}

function assignExisting(): void {
    if (
        assignForm.processing ||
        !selectedAdmin.value ||
        !assignForm.tour_operator_id
    ) {
        return;
    }

    const operator = props.operators.find(
        (item) =>
            String(item.id) === assignForm.tour_operator_id,
    );

    if (!operator) return;

    const agreed = window.confirm(
        `Assign ${selectedAdmin.value.name} (${selectedAdmin.value.email}) to ${operator.name}?`,
    );

    if (!agreed) return;

    assignForm.patch(
        `/superadmin/operator-admins/${selectedAdmin.value.id}/assign`,
        {
            preserveScroll: true,
            onSuccess: () => {
                selectedAdminId.value = '';
                assignForm.reset();
            },
        },
    );
}

defineOptions({
    layout: {
        breadcrumbs: [
            {
                href: '/superadmin/operator-admins',
                title: 'Operator Admins',
            },
        ],
    },
});
</script>

<template>
    <Head title="Operator Admins" />

    <main class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6">
        <!-- HEADER -->
        <header class="flex items-start gap-3">
            <div class="rounded-xl bg-cyan-50 dark:bg-cyan-950/40 p-3">
                <ShieldCheck class="size-6 text-cyan-700 dark:text-cyan-300" />
            </div>

            <div>
                <h1 class="text-2xl font-bold">
                    Operator Admins
                </h1>

                <p class="mt-1 text-sm text-muted-foreground">
                    Create new Admin accounts or assign existing
                    unassigned Admin accounts to active Tour Operators.
                </p>
            </div>
        </header>

        <!-- SUMMARY -->
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border bg-card p-5">
                <Users class="mb-3 size-5 text-cyan-700 dark:text-cyan-300" />

                <p class="text-sm text-muted-foreground">
                    Total Operator Admins
                </p>

                <p class="mt-1 text-2xl font-bold">
                    {{ admins.length }}
                </p>
            </div>

            <div class="rounded-xl border bg-card p-5">
                <Building2 class="mb-3 size-5 text-cyan-700 dark:text-cyan-300" />

                <p class="text-sm text-muted-foreground">
                    Active Tour Operators
                </p>

                <p class="mt-1 text-2xl font-bold">
                    {{ operators.length }}
                </p>
            </div>

            <div class="rounded-xl border bg-card p-5">
                <UserCheck
                    class="mb-3 size-5 text-amber-600"
                />

                <p class="text-sm text-muted-foreground">
                    Admins Needing Assignment
                </p>

                <p class="mt-1 text-2xl font-bold">
                    {{ unassignedAdmins.length }}
                </p>
            </div>
        </div>

        <!-- ACCOUNT MANAGEMENT -->
        <div class="grid gap-5 lg:grid-cols-2">

            <!-- CREATE NEW ADMIN -->
            <section class="rounded-xl border bg-card p-5 sm:p-6">
                <h2 class="mb-1 flex items-center gap-2 text-lg font-semibold">
                    <UserPlus class="size-5 text-cyan-700 dark:text-cyan-300" />
                    Create New Admin
                </h2>

                <p class="mb-5 text-sm text-muted-foreground">
                    Use this only when the person does not have
                    an Admin account yet.
                </p>

                <form
                    class="space-y-4"
                    @submit.prevent="createAdmin"
                >
                    <div class="space-y-1.5">
                        <label
                            for="new-admin-name"
                            class="text-sm font-medium"
                        >
                            Full name
                        </label>

                        <input
                            id="new-admin-name"
                            v-model="createForm.name"
                            type="text"
                            required
                            autocomplete="name"
                            class="w-full rounded-lg border bg-background px-3 py-2 text-sm"
                        />

                        <p
                            v-if="createForm.errors.name"
                            class="text-sm text-destructive"
                        >
                            {{ createForm.errors.name }}
                        </p>
                    </div>

                    <div class="space-y-1.5">
                        <label
                            for="new-admin-email"
                            class="text-sm font-medium"
                        >
                            Email
                        </label>

                        <input
                            id="new-admin-email"
                            v-model="createForm.email"
                            type="email"
                            required
                            autocomplete="off"
                            class="w-full rounded-lg border bg-background px-3 py-2 text-sm"
                        />

                        <p
                            v-if="createForm.errors.email"
                            class="text-sm text-destructive"
                        >
                            {{ createForm.errors.email }}
                        </p>
                    </div>

                    <div class="space-y-1.5">
                        <label
                            for="new-admin-operator"
                            class="text-sm font-medium"
                        >
                            Tour Operator
                        </label>

                        <select
                            id="new-admin-operator"
                            v-model="createForm.tour_operator_id"
                            required
                            class="w-full rounded-lg border bg-background px-3 py-2 text-sm"
                        >
                            <option value="" disabled>
                                Select an active tour operator
                            </option>

                            <option
                                v-for="operator in operators"
                                :key="operator.id"
                                :value="String(operator.id)"
                            >
                                {{ operator.name }}
                            </option>
                        </select>

                        <p
                            v-if="createForm.errors.tour_operator_id"
                            class="text-sm text-destructive"
                        >
                            {{ createForm.errors.tour_operator_id }}
                        </p>
                    </div>

                    <div class="space-y-1.5">
                        <label
                            for="new-admin-password"
                            class="text-sm font-medium"
                        >
                            Password
                        </label>

                        <input
                            id="new-admin-password"
                            v-model="createForm.password"
                            type="password"
                            required
                            minlength="8"
                            autocomplete="new-password"
                            class="w-full rounded-lg border bg-background px-3 py-2 text-sm"
                        />

                        <p class="text-xs text-muted-foreground">
                            At least 8 characters, uppercase,
                            lowercase and a number.
                        </p>

                        <p
                            v-if="createForm.errors.password"
                            class="text-sm text-destructive"
                        >
                            {{ createForm.errors.password }}
                        </p>
                    </div>

                    <div class="space-y-1.5">
                        <label
                            for="new-admin-confirm"
                            class="text-sm font-medium"
                        >
                            Confirm password
                        </label>

                        <input
                            id="new-admin-confirm"
                            v-model="createForm.password_confirmation"
                            type="password"
                            required
                            autocomplete="new-password"
                            class="w-full rounded-lg border bg-background px-3 py-2 text-sm"
                        />
                    </div>

                    <Button
                        type="submit"
                        class="w-full"
                        :disabled="
                            createForm.processing ||
                            operators.length === 0
                        "
                    >
                        {{
                            createForm.processing
                                ? 'Creating...'
                                : 'Create Operator Admin'
                        }}
                    </Button>
                </form>
            </section>

            <!-- ASSIGN EXISTING ADMIN -->
            <section class="rounded-xl border bg-card p-5 sm:p-6">
                <h2 class="mb-1 flex items-center gap-2 text-lg font-semibold">
                    <UserCheck class="size-5 text-cyan-700 dark:text-cyan-300" />
                    Assign Existing Admin
                </h2>

                <p class="mb-5 text-sm text-muted-foreground">
                    Fix an existing Admin account that says it is
                    not assigned to a tour operator. No new
                    password or account is needed.
                </p>

                <div
                    v-if="unassignedAdmins.length === 0"
                    class="mb-4 rounded-lg border border-dashed p-4 text-sm text-muted-foreground"
                >
                    All existing Admin accounts are already assigned.
                </div>

                <form
                    class="space-y-4"
                    @submit.prevent="assignExisting"
                >
                    <div class="space-y-1.5">
                        <label
                            for="existing-admin"
                            class="text-sm font-medium"
                        >
                            Existing unassigned Admin
                        </label>

                        <select
                            id="existing-admin"
                            v-model="selectedAdminId"
                            required
                            :disabled="unassignedAdmins.length === 0"
                            class="w-full rounded-lg border bg-background px-3 py-2 text-sm"
                        >
                            <option value="" disabled>
                                Select an Admin account
                            </option>

                            <option
                                v-for="admin in unassignedAdmins"
                                :key="admin.id"
                                :value="String(admin.id)"
                            >
                                {{ admin.name }} —
                                {{ admin.email }}
                            </option>
                        </select>
                    </div>

                    <div class="space-y-1.5">
                        <label
                            for="existing-operator"
                            class="text-sm font-medium"
                        >
                            Assign to active Tour Operator
                        </label>

                        <select
                            id="existing-operator"
                            v-model="assignForm.tour_operator_id"
                            required
                            :disabled="operators.length === 0"
                            class="w-full rounded-lg border bg-background px-3 py-2 text-sm"
                        >
                            <option value="" disabled>
                                Select a Tour Operator
                            </option>

                            <option
                                v-for="operator in operators"
                                :key="operator.id"
                                :value="String(operator.id)"
                            >
                                {{ operator.name }}
                            </option>
                        </select>

                        <p
                            v-if="assignForm.errors.tour_operator_id"
                            class="text-sm text-destructive"
                        >
                            {{ assignForm.errors.tour_operator_id }}
                        </p>
                    </div>

                    <p
                        class="rounded-lg border border-amber-500/30 bg-amber-500/5 p-3 text-xs"
                    >
                        This action assigns the chosen Admin to one
                        business. It does not transfer existing
                        assignments or alter bookings, payments,
                        roles or passwords.
                    </p>

                    <Button
                        type="submit"
                        class="w-full"
                        :disabled="
                            assignForm.processing ||
                            !selectedAdminId ||
                            !assignForm.tour_operator_id ||
                            operators.length === 0
                        "
                    >
                        {{
                            assignForm.processing
                                ? 'Assigning...'
                                : 'Assign Existing Admin'
                        }}
                    </Button>
                </form>

                <p
                    v-if="operators.length === 0"
                    class="mt-4 text-sm text-muted-foreground"
                >
                    No active Tour Operator is available.

                    <Link
                        href="/superadmin/operators"
                        class="font-semibold text-cyan-700 dark:text-cyan-300 underline"
                    >
                        Register a Tour Operator
                    </Link>

                    first.
                </p>
            </section>
        </div>

        <!-- ADMIN ACCOUNTS -->
        <section class="rounded-xl border bg-card p-5 sm:p-6">
            <h2 class="mb-4 flex items-center gap-2 text-lg font-semibold">
                <Users class="size-5 text-cyan-700 dark:text-cyan-300" />
                Registered Operator Admins
            </h2>

            <div
                v-if="admins.length === 0"
                class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
            >
                No Operator Admins yet.
            </div>

            <div v-else class="grid gap-3 sm:grid-cols-2">
                <div
                    v-for="admin in admins"
                    :key="admin.id"
                    class="rounded-lg border p-4"
                >
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="font-semibold">
                                {{ admin.name }}
                            </p>

                            <p class="break-all text-sm text-muted-foreground">
                                {{ admin.email }}
                            </p>
                        </div>

                        <span
                            class="rounded-full px-2.5 py-1 text-xs font-medium"
                            :class="
                                admin.is_assigned &&
                                admin.operator_active
                                    ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                                    : 'bg-amber-500/10 text-amber-700 dark:text-amber-300'
                            "
                        >
                            {{
                                !admin.is_assigned
                                    ? 'Unassigned'
                                    : admin.operator_active
                                      ? 'Active assignment'
                                      : 'Inactive operator'
                            }}
                        </span>
                    </div>

                    <p class="mt-3 border-t pt-3 text-sm">
                        <span class="text-muted-foreground">
                            Operator:
                        </span>

                        <span class="ml-1 font-medium">
                            {{ admin.operator_name }}
                        </span>
                    </p>

                    <p class="mt-1 text-xs text-muted-foreground">
                        Created: {{ admin.created_at ?? '—' }}
                    </p>
                </div>
            </div>
        </section>
    </main>
</template>
