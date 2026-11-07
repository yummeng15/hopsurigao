<script setup lang="ts">
withDefaults(
    defineProps<{
        compact?: boolean;
        large?: boolean;
    }>(),
    {
        compact: false,
        large: false,
    },
);
</script>

<template>
    <span class="inline-flex max-w-full items-center">
        <img
            src="/images/hop-surigao-logo-light.png"
            alt="HopSurigao — Island Hopping & Tour Reservations"
            class="w-auto object-contain group-data-[collapsible=icon]:hidden dark:hidden"
            :class="large ? 'h-16' : compact ? 'h-8' : 'h-11'"
        />
        <img
            src="/images/hop-surigao-logo.png"
            alt="HopSurigao — Island Hopping & Tour Reservations"
            class="hidden w-auto object-contain group-data-[collapsible=icon]:hidden! dark:inline"
            :class="large ? 'h-16' : compact ? 'h-8' : 'h-11'"
        />
        <img
            src="/images/hop-surigao-mark.png"
            alt=""
            class="hidden size-8 object-contain group-data-[collapsible=icon]:block!"
        />
    </span>
</template>
