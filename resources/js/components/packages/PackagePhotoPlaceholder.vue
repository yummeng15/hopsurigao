<script setup lang="ts">
import { ImageIcon } from '@lucide/vue';

withDefaults(
    defineProps<{
        label?: string;
        compact?: boolean;
    }>(),
    {
        label: 'Photo coming soon',
        compact: false,
    },
);
</script>

<template>
    <div
        class="flex h-full w-full flex-col items-center justify-center gap-2 bg-gradient-to-br from-cyan-500/10 via-slate-100 to-slate-200 text-slate-500 dark:from-cyan-400/10 dark:via-slate-800 dark:to-slate-900 dark:text-slate-300"
        :class="compact ? 'gap-1.5' : 'gap-2'"
    >
        <div
            class="flex items-center justify-center rounded-2xl bg-primary/10 text-primary"
            :class="compact ? 'h-10 w-10' : 'h-12 w-12'"
        >
            <ImageIcon :class="compact ? 'h-5 w-5' : 'h-6 w-6'" />
        </div>
        <p
            class="px-3 text-center font-medium"
            :class="compact ? 'text-[11px]' : 'text-xs'"
        >
            {{ label }}
        </p>
    </div>
</template>
