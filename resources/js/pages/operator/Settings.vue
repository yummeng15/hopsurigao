<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

interface Profile {
    name: string;
    email: string;
}

interface Preferences {
    bookings: boolean;
    trips: boolean;
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
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const notificationForm = useForm({
    bookings: props.preferences.bookings,
    trips: props.preferences.trips,
});

watch(
    () => props.profile,
    (profile) => {
        profileForm.defaults({ name: profile.name, email: profile.email });
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
    'mt-1 w-full rounded-lg border border-border bg-card px-3 py-2 text-sm text-foreground outline-none placeholder:text-foreground/50 focus:border-primary';

const cancelClass =
    'rounded-lg border border-border bg-card px-3 py-1.5 text-sm font-semibold text-foreground hover:bg-muted';

const saveClass =
    'rounded-lg bg-[#00FFFF] px-3 py-1.5 text-sm font-semibold text-cyan-950 hover:opacity-90 disabled:opacity-60';

function saveProfile(): void {
    profileForm.patch('/operator/settings/profile', { preserveScroll: true });
}

function cancelProfile(): void {
    profileForm.clearErrors();
    profileForm.reset();
}

function savePassword(): void {
    passwordForm.put('/operator/settings/password', {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    });
}

function cancelPassword(): void {
    passwordForm.clearErrors();
    passwordForm.reset();
}

function saveNotifications(): void {
    notificationForm.patch('/operator/settings/notifications', { preserveScroll: true });
}

function cancelNotifications(): void {
    notificationForm.clearErrors();
    notificationForm.reset();
}
</script>

<template>
    <Head title="Settings" />

    <main class="mx-auto w-full max-w-3xl space-y-8 px-4 py-6 text-foreground sm:px-6">
        <header class="space-y-1">
            <h1 class="text-2xl font-bold tracking-tight text-foreground">Settings</h1>
            <p class="text-sm text-foreground/70">Manage your tour operator account.</p>
        </header>

        <section class="space-y-4 border-b border-border pb-8">
            <div>
                <h2 class="text-base font-semibold text-foreground">My Profile</h2>
                <p class="text-sm text-foreground/70">Update the name and email for this operator account.</p>
            </div>
            <p v-if="profileStatus" class="text-sm font-medium text-emerald-700 dark:text-emerald-300" data-test="profile-status">
                {{ profileStatus }}
            </p>
            <form class="max-w-lg space-y-4" @submit.prevent="saveProfile">
                <div>
                    <label for="name" class="text-sm font-medium text-foreground">Name</label>
                    <input id="name" v-model="profileForm.name" type="text" autocomplete="name" required :class="fieldClass" />
                    <p v-if="profileForm.errors.name" class="mt-1 text-sm text-destructive">{{ profileForm.errors.name }}</p>
                </div>
                <div>
                    <label for="email" class="text-sm font-medium text-foreground">Email</label>
                    <input id="email" v-model="profileForm.email" type="email" autocomplete="username" required :class="fieldClass" />
                    <p v-if="profileForm.errors.email" class="mt-1 text-sm text-destructive">{{ profileForm.errors.email }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="submit" :class="saveClass" :disabled="profileForm.processing" data-test="save-profile">
                        {{ profileForm.processing ? 'Saving...' : 'Save' }}
                    </button>
                    <button type="button" :class="cancelClass" @click="cancelProfile">
                        Cancel
                    </button>
                </div>
            </form>
        </section>

        <section class="space-y-4 border-b border-border pb-8">
            <div>
                <h2 class="text-base font-semibold text-foreground">Account Security</h2>
                <p class="text-sm text-foreground/70">Enter your current password before choosing a new one.</p>
            </div>
            <p v-if="passwordStatus" class="text-sm font-medium text-emerald-700 dark:text-emerald-300" data-test="password-status">
                {{ passwordStatus }}
            </p>
            <form class="max-w-lg space-y-4" @submit.prevent="savePassword">
                <div>
                    <label for="current_password" class="text-sm font-medium text-foreground">Current password</label>
                    <input id="current_password" v-model="passwordForm.current_password" type="password" autocomplete="current-password" required :class="fieldClass" />
                    <p v-if="passwordForm.errors.current_password" class="mt-1 text-sm text-destructive">{{ passwordForm.errors.current_password }}</p>
                </div>
                <div>
                    <label for="password" class="text-sm font-medium text-foreground">New password</label>
                    <input id="password" v-model="passwordForm.password" type="password" autocomplete="new-password" required :class="fieldClass" />
                    <p v-if="passwordForm.errors.password" class="mt-1 text-sm text-destructive">{{ passwordForm.errors.password }}</p>
                </div>
                <div>
                    <label for="password_confirmation" class="text-sm font-medium text-foreground">Confirm new password</label>
                    <input id="password_confirmation" v-model="passwordForm.password_confirmation" type="password" autocomplete="new-password" required :class="fieldClass" />
                </div>
                <div class="flex items-center gap-2">
                    <button type="submit" :class="saveClass" :disabled="passwordForm.processing" data-test="save-password">
                        {{ passwordForm.processing ? 'Saving...' : 'Save' }}
                    </button>
                    <button type="button" :class="cancelClass" @click="cancelPassword">
                        Cancel
                    </button>
                </div>
            </form>
        </section>

        <section class="space-y-4">
            <div>
                <h2 class="text-base font-semibold text-foreground">Notification Preferences</h2>
                <p class="text-sm text-foreground/70">
                    These choices cover optional booking and trip alerts. Safety weather alerts stay on.
                </p>
            </div>
            <p v-if="notificationStatus" class="text-sm font-medium text-emerald-700 dark:text-emerald-300" data-test="notification-status">
                {{ notificationStatus }}
            </p>
            <form class="space-y-3" @submit.prevent="saveNotifications">
                <label class="flex items-start gap-3 text-sm text-foreground">
                    <input v-model="notificationForm.bookings" type="checkbox" class="mt-1 size-4 rounded border-border text-primary" />
                    <span>
                        <span class="font-medium">New booking alerts</span>
                        <span class="mt-0.5 block text-foreground/70">In-app notices when a guest submits a reservation.</span>
                    </span>
                </label>
                <label class="flex items-start gap-3 text-sm text-foreground">
                    <input v-model="notificationForm.trips" type="checkbox" class="mt-1 size-4 rounded border-border text-primary" />
                    <span>
                        <span class="font-medium">Trip changes</span>
                        <span class="mt-0.5 block text-foreground/70">In-app notices when a reservation for your trips is cancelled.</span>
                    </span>
                </label>
                <div class="flex items-center gap-2 pt-1">
                    <button type="submit" :class="saveClass" :disabled="notificationForm.processing" data-test="save-notifications">
                        {{ notificationForm.processing ? 'Saving...' : 'Save' }}
                    </button>
                    <button type="button" :class="cancelClass" @click="cancelNotifications">
                        Cancel
                    </button>
                </div>
            </form>
        </section>
    </main>
</template>
