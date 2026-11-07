<!-- resources/js/components/ui/chart/AreaChart.vue -->
<script setup lang="ts" generic="T extends Record<string, any>">
import { computed } from 'vue';
import {
    VisXYContainer,
    VisArea,
    VisLine,
    VisAxis,
    VisCrosshair,
    VisTooltip,
} from '@unovis/vue';
import { CurveType } from '@unovis/ts';

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
        height?: string;
    }>(),
    {
        colors: () => ['var(--color-primary, #0ea5e9)', '#10b981', '#f59e0b', '#ef4444'],
        valueFormatter: (tick: number) => tick.toLocaleString(),
        xFormatter: undefined,
        showGridLine: true,
        showXAxis: true,
        showYAxis: true,
        showLegend: true,
        showTooltip: true,
        height: '300px',
    }
);

const xAccessor = (d: T, i: number) => i;

const xTicks = (i: number) => {
    const item = props.data[i];
    if (!item) return '';
    if (props.xFormatter) return props.xFormatter(item[props.index]);
    return String(item[props.index] ?? '');
};

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
                class="flex items-center gap-1.5"
            >
                <span
                    class="h-2.5 w-2.5 rounded-sm shrink-0"
                    :style="{ backgroundColor: colors[idx % colors.length] }"
                />
                <span class="capitalize">{{ String(cat).replace(/_/g, ' ') }}</span>
            </div>
        </div>

        <div class="w-full" :style="{ height }">
            <VisXYContainer :data="data" :margin="{ top: 10, right: 10, bottom: 20, left: 20 }">
                <template v-for="(cat, idx) in categories" :key="cat">
                    <VisArea
                        :x="xAccessor"
                        :y="(d: T) => Number(d[cat] || 0)"
                        :color="colors[idx % colors.length]"
                        :opacity="0.25"
                        :curve-type="CurveType.Basis"
                    />
                    <VisLine
                        :x="xAccessor"
                        :y="(d: T) => Number(d[cat] || 0)"
                        :color="colors[idx % colors.length]"
                        :line-width="2.5"
                        :curve-type="CurveType.Basis"
                    />
                </template>

                <VisAxis
                    v-if="showXAxis"
                    type="x"
                    :tick-format="xTicks"
                    :grid-line="false"
                    :num-ticks="data.length"
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
