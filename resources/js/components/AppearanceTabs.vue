<script setup lang="ts">
import { Moon, Sun } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/composables/useAppearance';

const props = withDefaults(
    defineProps<{
        variant?: 'icon' | 'segmented';
        labels?: boolean;
        block?: boolean;
        tone?: 'default' | 'on-dark';
    }>(),
    {
        variant: 'icon',
        labels: false,
        block: false,
        tone: 'default',
    },
);

const { resolvedAppearance, updateAppearance } = useAppearance();

const isDark = computed(() => resolvedAppearance.value === 'dark');

const toggle = () => {
    updateAppearance(isDark.value ? 'light' : 'dark');
};
</script>

<template>
    <div
        v-if="variant === 'segmented'"
        class="items-center rounded-xl border p-0.5 text-xs font-semibold"
        :class="[
            props.block ? 'flex w-full' : 'inline-flex',
            props.tone === 'on-dark'
                ? 'border-white/15 bg-white/10'
                : 'border-border bg-muted/70',
        ]"
        role="group"
        aria-label="Color mode"
    >
        <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 transition"
            :class="[
                props.block ? 'flex-1 justify-center' : '',
                props.tone === 'on-dark'
                    ? isDark
                        ? 'text-slate-300 hover:text-white'
                        : 'bg-white text-slate-900 shadow-sm'
                    : isDark
                      ? 'text-muted-foreground hover:text-foreground'
                      : 'bg-background text-foreground shadow-sm',
            ]"
            aria-label="Light mode"
            :aria-pressed="!isDark"
            @click="updateAppearance('light')"
        >
            <Sun class="h-3.5 w-3.5" />
            <span :class="props.labels ? '' : 'hidden sm:inline'">Light</span>
        </button>
        <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 transition"
            :class="[
                props.block ? 'flex-1 justify-center' : '',
                props.tone === 'on-dark'
                    ? isDark
                        ? 'bg-white text-slate-900 shadow-sm'
                        : 'text-slate-300 hover:text-white'
                    : isDark
                      ? 'bg-background text-foreground shadow-sm'
                      : 'text-muted-foreground hover:text-foreground',
            ]"
            aria-label="Dark mode"
            :aria-pressed="isDark"
            @click="updateAppearance('dark')"
        >
            <Moon class="h-3.5 w-3.5" />
            <span :class="props.labels ? '' : 'hidden sm:inline'">Dark</span>
        </button>
    </div>

    <Button
        v-else
        variant="ghost"
        size="icon"
        class="h-9 w-9 rounded-full"
        :aria-label="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
        @click="toggle"
    >
        <Sun v-if="isDark" class="h-4 w-4" />
        <Moon v-else class="h-4 w-4" />
    </Button>
</template>
