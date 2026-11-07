<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

interface Profile {
    name: string;
    email: string;
}

interface Preferences {
    bookings: boolean;
    cancellations: boolean;
    payments: boolean;
    weather: boolean;
}

interface About {
    name: string;
    version: string;
    framework: string;
    php: string;
}

const props = defineProps<{
    profile: Profile;
    preferences: Preferences;
    about: About;
    profileStatus?: string | null;
    passwordStatus?: string | null;
    notificationStatus?: string | null;
}>();

const profileForm = useForm({
    name: props.profile.name,
    email: props.profile.email,
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const notificationForm = useForm({
    bookings: props.preferences.bookings,
    cancellations: props.preferences.cancellations,
    payments: props.preferences.payments,
    weather: props.preferences.weather,
});

watch(
    () => props.profile,
    (profile) => {
        profileForm.defaults({
            name: profile.name,
            email: profile.email,
        });
    },
    { deep: true },
);

watch(
    () => props.preferences,
    (preferences) => {
        notificationForm.defaults({ ...preferences });
    },
    { deep: true },
);

const notificationOptions = [
    {
        key: 'bookings' as const,
        label: 'New booking alerts',
        detail: 'In-app notices when a reservation is created or confirmed.',
    },
    {
        key: 'cancellations' as const,
        label: 'Cancellation alerts',
        detail: 'In-app notices when a reservation is cancelled.',
    },
    {
        key: 'payments' as const,
        label: 'Payment updates',
        detail: 'In-app notices about submitted, confirmed, or rejected payments.',
    },
    {
        key: 'weather' as const,
        label: 'Routine weather advisories',
        detail: 'Caution and suitable conditions. Dangerous weather alerts stay on.',
    },
];

function saveProfile(): void {
    profileForm.patch('/superadmin/settings/profile', {
        preserveScroll: true,
    });
}

function cancelProfile(): void {
    profileForm.clearErrors();
    profileForm.reset();
}

function savePassword(): void {
    passwordForm.put('/superadmin/settings/password', {
        preserveScroll: true,
        onSuccess: () => {
            passwordForm.reset();
        },
    });
}

function cancelPassword(): void {
    passwordForm.clearErrors();
    passwordForm.reset();
}

function saveNotifications(): void {
    notificationForm.patch('/superadmin/settings/notifications', {
        preserveScroll: true,
    });
}

function cancelNotifications(): void {
    notificationForm.clearErrors();
    notificationForm.reset();
}

const fieldClass =
    'mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-[#00FFFF] dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 dark:focus:border-[#00FFFF]';
</script>

<template>
    <Head title="Settings" />

    <main class="mx-auto w-full max-w-3xl space-y-8 px-4 py-6 sm:px-6">
        <header class="space-y-1">
            <h1 class="text-2xl font-bold tracking-tight">Settings</h1>
            <p class="text-sm text-foreground/70">
                Manage your Super Admin account.
            </p>
        </header>

        <section class="space-y-4 border-b border-slate-200 pb-8 dark:border-slate-800">
            <div>
                <h2 class="text-base font-semibold">My Profile</h2>
                <p class="text-sm text-foreground/70">
                    This is the name and email for the account you are using now.
                </p>
            </div>

            <p
                v-if="profileStatus"
                class="text-sm font-medium text-emerald-700"
                data-test="profile-status"
            >
                {{ profileStatus }}
            </p>

            <form class="max-w-lg space-y-4" @submit.prevent="saveProfile">
                <div>
                    <label for="name" class="text-sm font-medium">Name</label>
                    <input
                        id="name"
                        v-model="profileForm.name"
                        type="text"
                        autocomplete="name"
                        required
                        :class="fieldClass"
                    />
                    <p v-if="profileForm.errors.name" class="mt-1 text-sm text-rose-600">
                        {{ profileForm.errors.name }}
                    </p>
                </div>

                <div>
                    <label for="email" class="text-sm font-medium">Email</label>
                    <input
                        id="email"
                        v-model="profileForm.email"
                        type="email"
                        autocomplete="username"
                        required
                        :class="fieldClass"
                    />
                    <p v-if="profileForm.errors.email" class="mt-1 text-sm text-rose-600">
                        {{ profileForm.errors.email }}
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="submit"
                        class="rounded-lg bg-[#00FFFF] px-3 py-1.5 text-sm font-semibold text-cyan-950 hover:opacity-90 disabled:opacity-60"
                        :disabled="profileForm.processing"
                        data-test="save-profile"
                    >
                        {{ profileForm.processing ? 'Saving...' : 'Save' }}
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border border-border bg-card px-3 py-1.5 text-sm font-semibold text-foreground hover:bg-muted"
                        @click="cancelProfile"
                    >
                        Cancel
                    </button>
                </div>
            </form>
        </section>

        <section class="space-y-4 border-b border-slate-200 pb-8 dark:border-slate-800">
            <div>
                <h2 class="text-base font-semibold">Account Security</h2>
                <p class="text-sm text-foreground/70">
                    Enter your current password before choosing a new one.
                </p>
            </div>

            <p
                v-if="passwordStatus"
                class="text-sm font-medium text-emerald-700"
                data-test="password-status"
            >
                {{ passwordStatus }}
            </p>

            <form class="max-w-lg space-y-4" @submit.prevent="savePassword">
                <div>
                    <label for="current_password" class="text-sm font-medium">Current password</label>
                    <input
                        id="current_password"
                        v-model="passwordForm.current_password"
                        type="password"
                        autocomplete="current-password"
                        required
                        :class="fieldClass"
                    />
                    <p
                        v-if="passwordForm.errors.current_password"
                        class="mt-1 text-sm text-rose-600"
                    >
                        {{ passwordForm.errors.current_password }}
                    </p>
                </div>

                <div>
                    <label for="password" class="text-sm font-medium">New password</label>
                    <input
                        id="password"
                        v-model="passwordForm.password"
                        type="password"
                        autocomplete="new-password"
                        required
                        :class="fieldClass"
                    />
                    <p v-if="passwordForm.errors.password" class="mt-1 text-sm text-rose-600">
                        {{ passwordForm.errors.password }}
                    </p>
                </div>

                <div>
                    <label for="password_confirmation" class="text-sm font-medium">Confirm new password</label>
                    <input
                        id="password_confirmation"
                        v-model="passwordForm.password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        required
                        :class="fieldClass"
                    />
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="submit"
                        class="rounded-lg bg-[#00FFFF] px-3 py-1.5 text-sm font-semibold text-cyan-950 hover:opacity-90 disabled:opacity-60"
                        :disabled="passwordForm.processing"
                        data-test="save-password"
                    >
                        {{ passwordForm.processing ? 'Saving...' : 'Save' }}
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border border-border bg-card px-3 py-1.5 text-sm font-semibold text-foreground hover:bg-muted"
                        @click="cancelPassword"
                    >
                        Cancel
                    </button>
                </div>
            </form>
        </section>

        <section class="space-y-4 border-b border-slate-200 pb-8 dark:border-slate-800">
            <div>
                <h2 class="text-base font-semibold">Notification Preferences</h2>
                <p class="text-sm text-foreground/70">
                    These choices apply only to your in-app alerts. Essential safety alerts are not turned off.
                </p>
            </div>

            <p
                v-if="notificationStatus"
                class="text-sm font-medium text-emerald-700"
                data-test="notification-status"
            >
                {{ notificationStatus }}
            </p>
            <p
                v-if="notificationForm.errors.bookings"
                class="text-sm text-rose-600"
            >
                {{ notificationForm.errors.bookings }}
            </p>

            <form class="space-y-3" @submit.prevent="saveNotifications">
                <label
                    v-for="option in notificationOptions"
                    :key="option.key"
                    class="flex items-start gap-3 text-sm"
                >
                    <input
                        v-model="notificationForm[option.key]"
                        type="checkbox"
                        class="mt-1 size-4 rounded border-border text-cyan-700"
                    />
                    <span>
                        <span class="font-medium">{{ option.label }}</span>
                        <span class="mt-0.5 block text-foreground/70">{{ option.detail }}</span>
                    </span>
                </label>

                <div class="flex items-center gap-2 pt-1">
                    <button
                        type="submit"
                        class="rounded-lg bg-[#00FFFF] px-3 py-1.5 text-sm font-semibold text-cyan-950 hover:opacity-90 disabled:opacity-60"
                        :disabled="notificationForm.processing"
                        data-test="save-notifications"
                    >
                        {{ notificationForm.processing ? 'Saving...' : 'Save' }}
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border border-border bg-card px-3 py-1.5 text-sm font-semibold text-foreground hover:bg-muted"
                        @click="cancelNotifications"
                    >
                        Cancel
                    </button>
                </div>
            </form>
        </section>

        <section class="space-y-3">
            <div>
                <h2 class="text-base font-semibold">About HopSurigao</h2>
                <p class="text-sm text-foreground/70">
                    Basic application information.
                </p>
            </div>

            <dl class="max-w-lg space-y-2 text-sm">
                <div class="flex justify-between gap-4 border-b border-slate-100 py-2 dark:border-slate-800">
                    <dt class="text-foreground/70">Application</dt>
                    <dd class="font-medium">{{ about.name }}</dd>
                </div>
                <div class="flex justify-between gap-4 border-b border-slate-100 py-2 dark:border-slate-800">
                    <dt class="text-foreground/70">Version</dt>
                    <dd class="font-medium">{{ about.version }}</dd>
                </div>
                <div class="flex justify-between gap-4 border-b border-slate-100 py-2 dark:border-slate-800">
                    <dt class="text-foreground/70">Framework</dt>
                    <dd class="font-medium">{{ about.framework }}</dd>
                </div>
                <div class="flex justify-between gap-4 py-2">
                    <dt class="text-foreground/70">PHP</dt>
                    <dd class="font-medium">{{ about.php }}</dd>
                </div>
            </dl>
        </section>
    </main>
</template>
