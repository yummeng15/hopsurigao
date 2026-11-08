<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { ChevronLeft, ChevronRight } from '@lucide/vue';

export type CalendarTone =
    | 'available'
    | 'limited'
    | 'full'
    | 'booked'
    | 'closed'
    | 'maintenance'
    | 'none';

export type CalendarMarker = {
    tone: CalendarTone;
    count?: number;
};

const props = withDefaults(
    defineProps<{
        markers: Record<string, CalendarMarker>;
        selected?: string | null;
        initialDate?: string | null;
        today?: string | null;
        allowEmpty?: boolean;
    }>(),
    {
        selected: null,
        initialDate: null,
        today: null,
        allowEmpty: false,
    },
);

const emit = defineEmits<{
    select: [date: string];
}>();

const weekdayLabels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

function parseDate(value: string): Date {
    return new Date(`${value.slice(0, 10)}T00:00:00`);
}

function toKey(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function monthStart(value: string | null | undefined): Date | null {
    if (!value) {
        return null;
    }

    const parsed = parseDate(value);

    if (Number.isNaN(parsed.getTime())) {
        return null;
    }

    return new Date(parsed.getFullYear(), parsed.getMonth(), 1);
}

const cursor = ref<Date>(
    monthStart(props.initialDate)
        ?? monthStart(props.selected)
        ?? monthStart(props.today)
        ?? new Date(new Date().getFullYear(), new Date().getMonth(), 1),
);

watch(
    () => props.selected,
    (value) => {
        const next = monthStart(value);

        if (next) {
            cursor.value = next;
        }
    },
);

const monthLabel = computed(() =>
    cursor.value.toLocaleDateString('en-US', {
        month: 'long',
        year: 'numeric',
    }),
);

const cells = computed(() => {
    const year = cursor.value.getFullYear();
    const month = cursor.value.getMonth();
    const firstWeekday = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const items: Array<{
        key: string;
        day: number | null;
        marker: CalendarMarker | null;
    }> = [];

    for (let index = 0; index < firstWeekday; index++) {
        items.push({
            key: `pad-${year}-${month}-${index}`,
            day: null,
            marker: null,
        });
    }

    for (let day = 1; day <= daysInMonth; day++) {
        const key = toKey(new Date(year, month, day));

        items.push({
            key,
            day,
            marker: props.markers[key] ?? null,
        });
    }

    return items;
});

function shiftMonth(amount: number): void {
    cursor.value = new Date(cursor.value.getFullYear(), cursor.value.getMonth() + amount, 1);
}

function canSelect(marker: CalendarMarker | null): boolean {
    return props.allowEmpty || marker !== null;
}

function cellClass(key: string, marker: CalendarMarker | null): string {
    // Empty / no-schedule selection must never reuse the Seats Open primary fill.
    if (props.selected === key) {
        if (! marker || marker.tone === 'none') {
            return 'bg-amber-500/15 font-semibold text-amber-900 ring-1 ring-amber-500/40 dark:text-amber-200';
        }

        if (marker.tone === 'available' || marker.tone === 'booked') {
            return 'bg-primary text-primary-foreground shadow-xs';
        }

        if (marker.tone === 'limited') {
            return 'bg-orange-500 text-white shadow-xs';
        }

        if (marker.tone === 'full') {
            return 'bg-destructive text-white shadow-xs';
        }

        if (marker.tone === 'maintenance') {
            return 'bg-violet-600 text-white shadow-xs';
        }

        return 'bg-slate-600 text-white shadow-xs';
    }

    if (marker?.tone === 'full') {
        return 'bg-destructive/15 font-semibold text-destructive';
    }

    if (marker?.tone === 'maintenance') {
        return 'bg-violet-500/15 font-semibold text-violet-700 dark:text-violet-300';
    }

    if (marker?.tone === 'closed') {
        return 'bg-slate-500/15 font-semibold text-slate-700 dark:text-slate-300';
    }

    if (marker?.tone === 'none') {
        return 'bg-amber-500/10 font-semibold text-amber-800 dark:text-amber-200';
    }

    if (marker?.tone === 'limited') {
        return 'bg-orange-500/15 font-semibold text-orange-700 dark:text-orange-300';
    }

    if (marker?.tone === 'available' || marker?.tone === 'booked') {
        return 'bg-primary/15 font-semibold text-primary';
    }

    if (props.today === key) {
        return 'text-foreground ring-1 ring-primary/40';
    }

    return 'text-foreground hover:bg-muted disabled:text-muted-foreground/70 disabled:hover:bg-transparent';
}

function onSelect(key: string, marker: CalendarMarker | null): void {
    if (!canSelect(marker)) {
        return;
    }

    emit('select', key);
}
</script>

<template>
    <div class="rounded-2xl border bg-background p-4">
        <div class="mb-4 flex items-center justify-between gap-3">
            <button
                type="button"
                class="inline-flex h-8 w-8 items-center justify-center rounded-lg border text-muted-foreground transition hover:bg-muted hover:text-foreground"
                aria-label="Previous month"
                @click="shiftMonth(-1)"
            >
                <ChevronLeft class="h-4 w-4" />
            </button>

            <p class="text-sm font-semibold tracking-tight">{{ monthLabel }}</p>

            <button
                type="button"
                class="inline-flex h-8 w-8 items-center justify-center rounded-lg border text-muted-foreground transition hover:bg-muted hover:text-foreground"
                aria-label="Next month"
                @click="shiftMonth(1)"
            >
                <ChevronRight class="h-4 w-4" />
            </button>
        </div>

        <div class="grid grid-cols-7 gap-1 text-center text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
            <span v-for="label in weekdayLabels" :key="label" class="py-1">{{ label }}</span>
        </div>

        <div class="mt-1 grid grid-cols-7 gap-1">
            <template v-for="cell in cells" :key="cell.key">
                <span v-if="cell.day === null" class="aspect-square" />

                <button
                    v-else
                    type="button"
                    class="relative flex aspect-square items-center justify-center rounded-xl text-sm transition"
                    :class="cellClass(cell.key, cell.marker)"
                    :disabled="!canSelect(cell.marker)"
                    :aria-label="cell.key"
                    :aria-pressed="selected === cell.key"
                    @click="onSelect(cell.key, cell.marker)"
                >
                    {{ cell.day }}
                    <span
                        v-if="cell.marker?.count"
                        class="absolute bottom-1 right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[9px] font-bold leading-none text-primary-foreground"
                        :class="selected === cell.key ? 'bg-primary-foreground text-primary' : ''"
                    >
                        {{ cell.marker.count }}
                    </span>
                </button>
            </template>
        </div>
    </div>
</template>
