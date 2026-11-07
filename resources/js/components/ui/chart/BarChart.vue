<!-- resources/js/components/ui/chart/BarChart.vue -->
<script setup lang="ts" generic="T extends Record<string, any>">
import { computed } from 'vue';
import {
    VisXYContainer,
    VisGroupedBar,
    VisAxis,
    VisCrosshair,
    VisTooltip,
} from '@unovis/vue';

const props = withDefaults(
    defineProps<{
        data: T[];
        index: keyof T & string;
        categories: (keyof T & string)[];
        colors?: string[];
        valueFormatter?: (tick: number) => string;
        xFormatter?: (val: any) => string;
        showGridLine?: boolean;
        showXAxis?: boolean;
        showYAxis?: boolean;
        showLegend?: boolean;
        showTooltip?: boolean;
        roundedCorners?: number;
        height?: string;
    }>(),
    {
        colors: () => ['var(--color-primary, #0ea5e9)', '#10b981', '#f59e0b', '#8b5cf6'],
        valueFormatter: (tick: number) => Number(tick || 0).toLocaleString(),
        xFormatter: undefined,
        showGridLine: true,
        showXAxis: true,
        showYAxis: true,
        showLegend: true,
        showTooltip: true,
        roundedCorners: 4,
        height: '280px',
    }
);

const xAccessor = (d: T, i: number) => i;

const xTicks = (val: number) => {
    const idx = Math.round(val);
    const item = props.data[idx];
    if (!item) return '';
    if (props.xFormatter) return props.xFormatter(item[props.index]);
    return String(item[props.index] ?? '');
};

const yAccessors = props.categories.map((cat) => (d: T) => Number(d[cat] || 0));

const colorAccessors = (d: T, i: number, j?: number) => {
    if (typeof j === 'number') {
        return props.colors[j % props.colors.length];
    }
    return props.colors[i % props.colors.length];
};

const tickValues = computed(() => {
    return props.data.map((_, i) => i);
});

const xDomain = computed(() => {
    if (!props.data.length) return undefined;
    return [-0.5, props.data.length - 0.5];
});

const tooltipTemplate = (d: any) => {
    if (!d) return '';
    const indexVal = d[props.index] ?? '';
    let html = `<div class="rounded-xl border bg-popover/95 p-3 text-xs shadow-md backdrop-blur text-popover-foreground space-y-1.5 min-w-[140px]">`;
    html += `<p class="font-semibold text-foreground border-b pb-1 mb-1">${indexVal}</p>`;

    props.categories.forEach((cat, idx) => {
        const color = props.colors[idx % props.colors.length];
        const val = d[cat];
        const formatted = typeof val === 'number' ? props.valueFormatter(val) : val;
        const label = String(cat).replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase());

        html += `<div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-1.5">
                <span class="h-2 w-2 rounded-full shrink-0" style="background-color: ${color}"></span>
                <span class="text-muted-foreground">${label}:</span>
            </div>
            <span class="font-bold text-foreground">${formatted}</span>
        </div>`;
    });

    html += `</div>`;
    return html;
};
</script>

<template>
    <div class="w-full flex flex-col gap-2">
        <!-- Optional Legend -->
        <div v-if="showLegend && categories.length > 1" class="flex flex-wrap items-center gap-4 text-xs font-medium text-muted-foreground pb-2">
            <div
                v-for="(cat, idx) in categories"
                :key="cat"
                class="flex items-center gap-1.5 rounded-md border bg-background/60 px-2.5 py-1 backdrop-blur shadow-2xs"
            >
                <span
                    class="h-2.5 w-2.5 rounded-xs shrink-0"
                    :style="{ backgroundColor: colors[idx % colors.length] }"
                />
                <span class="capitalize font-semibold text-foreground">{{ String(cat).replace(/_/g, ' ') }}</span>
            </div>
        </div>

        <div class="w-full unovis-bar-chart" :style="{ height }">
            <VisXYContainer
                :data="data"
                :x-domain="xDomain"
                :margin="{ top: 10, right: 25, bottom: 40, left: 35 }"
            >
                <VisGroupedBar
                    :x="xAccessor"
                    :y="yAccessors"
                    :color="colorAccessors"
                    :rounded-corners="roundedCorners"
                />

                <VisAxis
                    v-if="showXAxis"
                    type="x"
                    :tick-values="tickValues"
                    :tick-format="xTicks"
                    :grid-line="false"
                />

                <VisAxis
                    v-if="showYAxis"
                    type="y"
                    :tick-format="valueFormatter"
                    :grid-line="showGridLine"
                />

                <VisCrosshair
                    v-if="showTooltip"
                    :template="tooltipTemplate"
                />

                <VisTooltip v-if="showTooltip" />
            </VisXYContainer>
        </div>
    </div>
</template>

<style scoped>
.unovis-bar-chart {
    --vis-axis-font-family: inherit;
    --vis-axis-tick-label-font-size: 12px;
    --vis-axis-tick-label-color: var(--muted-foreground, #64748b);
    --vis-axis-grid-color: var(--border, rgba(148, 163, 184, 0.15));
}
:deep(.unovis-axis-tick-text) {
    font-size: 12px;
    fill: currentColor;
}
</style>
