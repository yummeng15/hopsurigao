<script setup lang="ts">
import SeaWaves from '@/components/SeaWaves.vue';
</script>

<template>
    <div
        aria-hidden="true"
        class="pointer-events-none absolute inset-0 z-0 overflow-hidden bg-linear-to-b from-background via-cyan-50/40 to-cyan-100/50 dark:via-cyan-950/10 dark:to-cyan-950/40"
    >
        <div class="absolute -top-32 right-0 size-96 rounded-full bg-primary/20 blur-3xl dark:bg-primary/10" />
        <SeaWaves class="absolute inset-x-0 bottom-0 h-44 w-full opacity-70" />
    </div>
</template>
