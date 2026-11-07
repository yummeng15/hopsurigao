
<script setup lang="ts">
import { useRole } from '@/composables/useRole';
import TripOutcomeMonitor from '@/components/trips/TripOutcomeMonitor.vue';

import SuperAdminPortalLayout from './SuperAdminPortalLayout.vue';
import OperatorPortalLayout from './OperatorPortalLayout.vue';

const { roleName } = useRole();
</script>

<template>
    <SuperAdminPortalLayout v-if="roleName === 'Superadmin'">
        <TripOutcomeMonitor />
        <slot />
    </SuperAdminPortalLayout>

    <OperatorPortalLayout v-else-if="roleName === 'Admin'">
        <slot />
    </OperatorPortalLayout>

    <div
        v-else
        class="flex min-h-screen items-center justify-center p-8"
    >
        <div class="text-center">
            <h1 class="text-xl font-bold text-red-600">
                Access Denied
            </h1>

            <p class="mt-2 text-sm text-gray-600">
                This page is not available to your account.
            </p>
        </div>
    </div>
</template>
