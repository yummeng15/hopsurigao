
<script setup lang="ts">
import StaffPortalFrame from '@/components/portal/StaffPortalFrame.vue';
import TripOutcomeMonitor from '@/components/trips/TripOutcomeMonitor.vue';
import { operatorNavItems } from '@/navigation/operator';
</script>

<template>
    <StaffPortalFrame
        title="Tour Operator Portal"
        subtitle="Tour schedules, guests & daily operations"
        home-href="/operator/dashboard"
        :items="operatorNavItems"
        variant="operator"
    >
        <TripOutcomeMonitor />
        <slot />
    </StaffPortalFrame>
</template>
