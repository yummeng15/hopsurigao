<script setup lang="ts">
/**
 * Fixed mobile action bar that sits above the bottom tab bar.
 * Teleported to body so ancestor overflow/transform cannot trap it.
 */
withDefaults(
    defineProps<{
        /** Extra bottom offset when a taller CTA (price row + button) is shown. */
        tall?: boolean;
    }>(),
    {
        tall: false,
    },
);
</script>

<template>
    <Teleport to="body">
        <div
            class="fixed inset-x-0 z-30 border-t border-border bg-background/95 px-4 py-3 backdrop-blur-md md:hidden"
            :class="tall ? 'min-h-[4.5rem]' : ''"
            style="bottom: calc(4.75rem + env(safe-area-inset-bottom, 0px))"
            data-mobile-sticky-action
        >
            <slot />
        </div>
    </Teleport>
</template>
