<!-- resources/js/components/weather/WeatherAdvisoryBadge.vue -->
<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import {
    CheckCircle2,
    AlertTriangle,
    AlertCircle,
    ShieldAlert,
    HelpCircle,
} from '@lucide/vue';

const props = withDefaults(
    defineProps<{
        level?: 'green' | 'yellow' | 'orange' | 'red' | string | null;
        badgeText?: string | null;
        showIcon?: boolean;
        size?: 'sm' | 'md' | 'lg';
    }>(),
    {
        level: 'green',
        badgeText: undefined,
        showIcon: true,
        size: 'sm',
    }
);

const normalizedLevel = computed(() => {
    return (props.level || 'green').toLowerCase();
});

const badgeStyle = computed(() => {
    switch (normalizedLevel.value) {
        case 'green':
            return {
                class: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-500/30',
                dot: 'bg-emerald-500',
                label: 'Suitable',
                icon: CheckCircle2,
            };
        case 'yellow':
            return {
                class: 'bg-amber-500/10 text-amber-700 dark:text-amber-300 border-amber-500/30',
                dot: 'bg-amber-500',
                label: 'Caution / Monitor',
                icon: AlertTriangle,
            };
        case 'orange':
            return {
                class: 'bg-orange-500/10 text-orange-700 dark:text-orange-300 border-orange-500/30',
                dot: 'bg-orange-500',
                label: 'Unfavorable',
                icon: AlertCircle,
            };
        case 'red':
            return {
                class: 'bg-red-500/10 text-red-700 dark:text-red-300 border-red-500/30',
                dot: 'bg-red-500',
                label: 'Dangerous',
                icon: ShieldAlert,
            };
        default:
            return {
                class: 'bg-muted text-muted-foreground border-border',
                dot: 'bg-muted-foreground',
                label: 'Unknown',
                icon: HelpCircle,
            };
    }
});

const displayText = computed(() => {
    return props.badgeText || badgeStyle.value.label;
});
</script>

<template>
    <Badge
        variant="outline"
        :class="[
            'inline-flex items-center gap-1.5 font-semibold transition-colors',
            badgeStyle.class,
            size === 'sm' ? 'px-2.5 py-0.5 text-xs' : '',
            size === 'md' ? 'px-3 py-1 text-sm' : '',
            size === 'lg' ? 'px-4 py-1.5 text-base' : '',
        ]"
    >
        <span
            class="h-2 w-2 rounded-full shrink-0 animate-pulse"
            :class="badgeStyle.dot"
        />
        <component
            :is="badgeStyle.icon"
            v-if="showIcon"
            class="h-3.5 w-3.5 shrink-0"
        />
        <span>{{ displayText }}</span>
    </Badge>
</template>

