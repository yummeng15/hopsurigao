<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

interface Profile {
    name: string;
    email: string;
    contact_number: string | null;
}

interface Preferences {
    cancellations: boolean;
}

const props = defineProps<{
    profile: Profile;
    preferences: Preferences;
    profileStatus?: string | null;
    passwordStatus?: string | null;
    notificationStatus?: string | null;
}>();

const profileForm = useForm({
    name: props.profile.name,
    email: props.profile.email,
    contact_number: props.profile.contact_number ?? '',
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const notificationForm = useForm({
    cancellations: props.preferences.cancellations,
});

watch(
    () => props.profile,
    (profile) => {
        profileForm.defaults({
            name: profile.name,
            email: profile.email,
            contact_number: profile.contact_number ?? '',
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

const fieldClass =
    'mt-1 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-[#00FFFF] dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100 dark:focus:border-[#00FFFF]';

function saveProfile(): void {
    profileForm.patch('/user/settings/profile', { preserveScroll: true });
}

function cancelProfile(): void {
    profileForm.clearErrors();
    profileForm.reset();
}

function savePassword(): void {
    passwordForm.put('/user/settings/password', {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    });
}

function cancelPassword(): void {
    passwordForm.clearErrors();
    passwordForm.reset();
}

function saveNotifications(): void {
    notificationForm.patch('/user/settings/notifications', { preserveScroll: true });
}

function cancelNotifications(): void {
    notificationForm.clearErrors();
    notificationForm.reset();
}
</script>

<template>
    <Head title="Settings" />

    <main class="mx-auto w-full max-w-lg space-y-8 px-4 py-5">
        <header class="space-y-1">
            <h1 class="text-2xl font-bold tracking-tight">Settings</h1>
            <p class="text-sm text-foreground/70">Update your tourist account.</p>
        </header>

        <section class="space-y-4 border-b border-slate-200 pb-8 dark:border-slate-800">
            <div>
                <h2 class="text-base font-semibold">Personal Information</h2>
                <p class="text-sm text-foreground/70">Your name, email, and contact number.</p>
            </div>
            <p v-if="profileStatus" class="text-sm font-medium text-emerald-700" data-test="profile-status">
                {{ profileStatus }}
            </p>
            <form class="space-y-4" @submit.prevent="saveProfile">
                <div>
                    <label for="name" class="text-sm font-medium">Name</label>
                    <input id="name" v-model="profileForm.name" type="text" autocomplete="name" required :class="fieldClass" />
                    <p v-if="profileForm.errors.name" class="mt-1 text-sm text-rose-600">{{ profileForm.errors.name }}</p>
                </div>
                <div>
                    <label for="email" class="text-sm font-medium">Email</label>
                    <input id="email" v-model="profileForm.email" type="email" autocomplete="username" required :class="fieldClass" />
                    <p v-if="profileForm.errors.email" class="mt-1 text-sm text-rose-600">{{ profileForm.errors.email }}</p>
                </div>
                <div>
                    <label for="contact_number" class="text-sm font-medium">Contact number</label>
                    <input id="contact_number" v-model="profileForm.contact_number" type="tel" autocomplete="tel" :class="fieldClass" />
                    <p v-if="profileForm.errors.contact_number" class="mt-1 text-sm text-rose-600">{{ profileForm.errors.contact_number }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="submit" class="rounded-lg bg-[#00FFFF] px-3 py-1.5 text-sm font-semibold text-cyan-950 hover:opacity-90 disabled:opacity-60" :disabled="profileForm.processing" data-test="save-profile">
                        {{ profileForm.processing ? 'Saving...' : 'Save' }}
                    </button>
                    <button type="button" class="rounded-lg border border-border bg-card px-3 py-1.5 text-sm font-semibold text-foreground hover:bg-muted" @click="cancelProfile">
                        Cancel
                    </button>
                </div>
            </form>
        </section>

        <section class="space-y-4 border-b border-slate-200 pb-8 dark:border-slate-800">
            <div>
                <h2 class="text-base font-semibold">Change Password</h2>
                <p class="text-sm text-foreground/70">Enter your current password before choosing a new one.</p>
            </div>
            <p v-if="passwordStatus" class="text-sm font-medium text-emerald-700" data-test="password-status">
                {{ passwordStatus }}
            </p>
            <form class="space-y-4" @submit.prevent="savePassword">
                <div>
                    <label for="current_password" class="text-sm font-medium">Current password</label>
                    <input id="current_password" v-model="passwordForm.current_password" type="password" autocomplete="current-password" required :class="fieldClass" />
                    <p v-if="passwordForm.errors.current_password" class="mt-1 text-sm text-rose-600">{{ passwordForm.errors.current_password }}</p>
                </div>
                <div>
                    <label for="password" class="text-sm font-medium">New password</label>
                    <input id="password" v-model="passwordForm.password" type="password" autocomplete="new-password" required :class="fieldClass" />
                    <p v-if="passwordForm.errors.password" class="mt-1 text-sm text-rose-600">{{ passwordForm.errors.password }}</p>
                </div>
                <div>
                    <label for="password_confirmation" class="text-sm font-medium">Confirm new password</label>
                    <input id="password_confirmation" v-model="passwordForm.password_confirmation" type="password" autocomplete="new-password" required :class="fieldClass" />
                </div>
                <div class="flex items-center gap-2">
                    <button type="submit" class="rounded-lg bg-[#00FFFF] px-3 py-1.5 text-sm font-semibold text-cyan-950 hover:opacity-90 disabled:opacity-60" :disabled="passwordForm.processing" data-test="save-password">
                        {{ passwordForm.processing ? 'Saving...' : 'Save' }}
                    </button>
                    <button type="button" class="rounded-lg border border-border bg-card px-3 py-1.5 text-sm font-semibold text-foreground hover:bg-muted" @click="cancelPassword">
                        Cancel
                    </button>
                </div>
            </form>
        </section>

        <section class="space-y-4">
            <div>
                <h2 class="text-base font-semibold">Notification Preferences</h2>
                <p class="text-sm text-foreground/70">
                    Booking confirmations, payment updates, and safety advisories always stay on.
                </p>
            </div>
            <p v-if="notificationStatus" class="text-sm font-medium text-emerald-700" data-test="notification-status">
                {{ notificationStatus }}
            </p>
            <form class="space-y-3" @submit.prevent="saveNotifications">
                <label class="flex items-start gap-3 text-sm">
                    <input v-model="notificationForm.cancellations" type="checkbox" class="mt-1 size-4 rounded border-border text-primary" />
                    <span>
                        <span class="font-medium">Cancellation notices</span>
                        <span class="mt-0.5 block text-foreground/70">Optional in-app notices when a reservation is cancelled.</span>
                    </span>
                </label>
                <div class="flex items-center gap-2 pt-1">
                    <button type="submit" class="rounded-lg bg-[#00FFFF] px-3 py-1.5 text-sm font-semibold text-cyan-950 hover:opacity-90 disabled:opacity-60" :disabled="notificationForm.processing" data-test="save-notifications">
                        {{ notificationForm.processing ? 'Saving...' : 'Save' }}
                    </button>
                    <button type="button" class="rounded-lg border border-border bg-card px-3 py-1.5 text-sm font-semibold text-foreground hover:bg-muted" @click="cancelNotifications">
                        Cancel
                    </button>
                </div>
            </form>
        </section>
    </main>
</template>
