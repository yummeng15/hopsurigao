<!-- resources/js/components/ui/chart/DonutChart.vue -->
<script setup lang="ts" generic="T extends Record<string, any>">
import { computed } from 'vue';
import {
    VisSingleContainer,
    VisDonut,
    VisTooltip,
} from '@unovis/vue';
import { Donut } from '@unovis/ts';

const props = withDefaults(
    defineProps<{
        data: T[];
        index: keyof T & string;
        category: keyof T & string;
        colors?: string[];
        valueFormatter?: (val: number) => string;
        showLegend?: boolean;
        showTooltip?: boolean;
        height?: string | number;
        centralLabel?: string;
        centralSubLabel?: string;
        arcWidth?: number;
        padAngle?: number;
    }>(),
    {
        colors: () => ['#10b981', '#f59e0b', '#0ea5e9', '#ef4444', '#8b5cf6', '#64748b'],
        valueFormatter: (val: number) => val.toLocaleString(),
        showLegend: true,
        showTooltip: true,
        height: 190,
        centralLabel: undefined,
        centralSubLabel: undefined,
        arcWidth: 24,
        padAngle: 0.02,
    }
);

const numericHeight = computed(() => {
    if (typeof props.height === 'number') return props.height;
    const parsed = parseInt(props.height, 10);
    return isNaN(parsed) ? 190 : parsed;
});

const totalValue = computed(() => {
    return props.data.reduce((sum, item) => sum + Number(item[props.category] || 0), 0);
});

// If all values are 0, provide placeholder data for empty circle
const chartData = computed(() => {
    if (totalValue.value === 0 && props.data.length > 0) {
        return props.data.map((item) => ({
            ...item,
            [props.category]: 1, // small equal segments for empty ring
        }));
    }
    return props.data;
});

const valueAccessor = (d: T) => {
    if (totalValue.value === 0) return 1;
    return Number(d[props.category] || 0);
};

const colorAccessor = (d: T, i: number) => {
    if (totalValue.value === 0) return '#cbd5e1'; // neutral placeholder ring
    return props.colors[i % props.colors.length];
};

const tooltipTriggers = {
    [Donut.selectors.segment]: (d: any) => {
        if (!d || !d.data) return '';
        const item = d.data;
        const name = item[props.index];
        const val = Number(item[props.category] || 0);
        const percent = totalValue.value > 0 ? ((val / totalValue.value) * 100).toFixed(1) : '0';

        return `<div class="rounded-xl border bg-popover/95 p-2.5 text-xs shadow-md backdrop-blur text-popover-foreground space-y-1 min-w-[130px]">
            <p class="font-bold text-foreground capitalize">${name}</p>
            <div class="flex items-center justify-between gap-3 text-muted-foreground">
                <span>Count: <strong class="text-foreground">${props.valueFormatter(val)}</strong></span>
                <span>(${percent}%)</span>
            </div>
        </div>`;
    },
};
</script>

<template>
    <div class="w-full flex flex-col items-center justify-center">
        <!-- Centered Donut Container -->
        <div
            class="relative w-full flex items-center justify-center overflow-hidden"
            :style="{ height: `${numericHeight}px` }"
        >
            <VisSingleContainer
                :data="chartData"
                :height="numericHeight"
                :margin="{ top: 5, right: 5, bottom: 5, left: 5 }"
                class="w-full flex items-center justify-center"
            >
                <VisDonut
                    :value="valueAccessor"
                    :color="colorAccessor"
                    :arc-width="arcWidth"
                    :pad-angle="padAngle"
                    :central-label="centralLabel || (totalValue ? totalValue.toLocaleString() : '0')"
                    :central-sub-label="centralSubLabel || 'Total'"
                />
                <VisTooltip v-if="showTooltip && totalValue > 0" :triggers="tooltipTriggers" />
            </VisSingleContainer>
        </div>

        <!-- Aligned Legend -->
        <div
            v-if="showLegend"
            class="mt-3 w-full flex flex-wrap items-center justify-center gap-x-4 gap-y-2 text-xs font-medium text-muted-foreground"
        >
            <div
                v-for="(item, idx) in data"
                :key="String(item[index])"
                class="flex items-center gap-1.5 rounded-lg px-2 py-0.5 border bg-background/50 backdrop-blur shadow-2xs"
            >
                <span
                    class="h-2 w-2 rounded-full shrink-0"
                    :style="{ backgroundColor: colors[idx % colors.length] }"
                />
                <span class="capitalize text-foreground font-medium truncate max-w-[110px]">{{ item[index] }}</span>
                <span class="text-muted-foreground text-[11px]">({{ item[category] }})</span>
            </div>
        </div>
    </div>
</template>

<style scoped>
:deep(.unovis-single-container) {
    display: flex !important;
    justify-content: center !important;
    align-items: center !important;
    margin: 0 auto !important;
    width: 100% !important;
    max-width: 100% !important;
}

:deep(svg) {
    margin: 0 auto !important;
    display: block !important;
}

:deep(.unovis-donut-central-label) {
    font-size: 20px !important;
    font-weight: 800 !important;
    fill: var(--color-foreground, currentColor) !important;
}

:deep(.unovis-donut-central-sub-label) {
    font-size: 11px !important;
    font-weight: 500 !important;
    fill: var(--color-muted-foreground, #64748b) !important;
}
</style>
