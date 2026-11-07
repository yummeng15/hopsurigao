
<script setup lang="ts">
import StaffPortalFrame from '@/components/portal/StaffPortalFrame.vue';
import { adminNavItems } from '@/navigation/admin';

/**
 * Exact Super Admin sidebar from the approved portal layout:
 * Overview → … → Operator Finance → Platform Reports → Settings.
 * Never surface Operator Admins here.
 */
const items = adminNavItems.filter(
    (item) => !String(item.href).includes('/operator-admins'),
);
</script>

<template>
    <StaffPortalFrame
        title="Super Admin Portal"
        subtitle="Platform management & oversight"
        home-href="/superadmin/dashboard"
        :items="items"
        variant="superadmin"
    >
        <slot />
    </StaffPortalFrame>
</template>
