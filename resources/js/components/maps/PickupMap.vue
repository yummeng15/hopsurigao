<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { MapPin, Minus, Plus } from '@lucide/vue';

const props = withDefaults(
    defineProps<{
        latitude?: number | string | null;
        longitude?: number | string | null;
        label?: string | null;
        interactive?: boolean;
        zoomable?: boolean;
        hint?: string | null;
    }>(),
    {
        latitude: null,
        longitude: null,
        label: null,
        interactive: false,
        zoomable: true,
        hint: null,
    },
);

const emit = defineEmits<{
    pick: [value: { latitude: number; longitude: number }];
}>();

const TILE = 256;
const SIARGAO = { lat: 9.86, lng: 126.08 };

const zoom = ref(15);
const center = ref(readCenter());
const pin = ref({ x: 0, y: 0 });
const dragging = ref(false);
const draggingPin = ref(false);
const pointers = new Map<number, { x: number; y: number }>();

let dragStart: { x: number; y: number; lat: number; lng: number } | null = null;
let pinDragStart: { x: number; y: number; pinX: number; pinY: number } | null = null;
let pinchStart: { distance: number; zoom: number } | null = null;
const mapEl = ref<HTMLElement | null>(null);

function asNumber(value: number | string | null | undefined): number | null {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const parsed = Number(value);

    return Number.isFinite(parsed) ? parsed : null;
}

function readCenter(): { lat: number; lng: number } {
    const lat = asNumber(props.latitude);
    const lng = asNumber(props.longitude);

    if (lat === null || lng === null) {
        return { ...SIARGAO };
    }

    return { lat, lng };
}

watch(
    () => [props.latitude, props.longitude],
    () => {
        if (dragging.value || draggingPin.value) {
            return;
        }

        const lat = asNumber(props.latitude);
        const lng = asNumber(props.longitude);

        if (lat === null || lng === null) {
            return;
        }

        const current = pointToLatLng(pin.value.x, pin.value.y);

        if (Math.abs(current.lat - lat) < 0.00002 && Math.abs(current.lng - lng) < 0.00002) {
            return;
        }

        center.value = { lat, lng };
        pin.value = { x: 0, y: 0 };
    },
);

function pointToLatLng(x: number, y: number): { lat: number; lng: number } {
    const origin = project(center.value.lat, center.value.lng, zoom.value);

    return unproject(origin.x + x / TILE, origin.y + y / TILE, zoom.value);
}

function emitPin(): void {
    const point = pointToLatLng(pin.value.x, pin.value.y);

    emit('pick', {
        latitude: Number(point.lat.toFixed(7)),
        longitude: Number(point.lng.toFixed(7)),
    });
}

const hasPin = computed(() => asNumber(props.latitude) !== null && asNumber(props.longitude) !== null);

function project(lat: number, lng: number, level: number): { x: number; y: number } {
    const scale = 2 ** level;
    const latRad = (lat * Math.PI) / 180;
    const x = ((lng + 180) / 360) * scale;
    const y = (1 - Math.log(Math.tan(latRad) + 1 / Math.cos(latRad)) / Math.PI) / 2 * scale;

    return { x, y };
}

function unproject(x: number, y: number, level: number): { lat: number; lng: number } {
    const scale = 2 ** level;
    const lng = (x / scale) * 360 - 180;
    const n = Math.PI - (2 * Math.PI * y) / scale;
    const lat = (180 / Math.PI) * Math.atan(Math.sinh(n));

    return {
        lat: Math.min(85, Math.max(-85, lat)),
        lng,
    };
}

const tiles = computed(() => {
    const point = project(center.value.lat, center.value.lng, zoom.value);
    const originX = Math.floor(point.x);
    const originY = Math.floor(point.y);
    const limit = 2 ** zoom.value;
    const items: Array<{ key: string; url: string; left: number; top: number }> = [];

    for (let dx = -2; dx <= 2; dx++) {
        for (let dy = -2; dy <= 2; dy++) {
            const tileX = originX + dx;
            const tileY = originY + dy;

            if (tileY < 0 || tileY >= limit) {
                continue;
            }

            const wrappedX = ((tileX % limit) + limit) % limit;

            items.push({
                key: `${zoom.value}-${tileX}-${tileY}`,
                url: `https://tile.openstreetmap.org/${zoom.value}/${wrappedX}/${tileY}.png`,
                left: (tileX - point.x) * TILE,
                top: (tileY - point.y) * TILE,
            });
        }
    }

    return items;
});

function changeZoom(amount: number): void {
    zoom.value = Math.min(18, Math.max(10, zoom.value + amount));
}

function onWheel(event: WheelEvent): void {
    if (!props.zoomable) {
        return;
    }

    changeZoom(event.deltaY < 0 ? 1 : -1);
}

function placePin(clientX: number, clientY: number): void {
    if (!mapEl.value || !props.interactive) {
        return;
    }

    const rect = mapEl.value.getBoundingClientRect();
    pin.value = {
        x: clientX - (rect.left + rect.width / 2),
        y: clientY - (rect.top + rect.height / 2),
    };
    emitPin();
}

function pointerDistance(): number | null {
    const points = [...pointers.values()];

    if (points.length < 2) {
        return null;
    }

    return Math.hypot(points[0].x - points[1].x, points[0].y - points[1].y);
}

function onPointerDown(event: PointerEvent): void {
    pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
    (event.currentTarget as HTMLElement).setPointerCapture(event.pointerId);

    if (pointers.size >= 2 && props.zoomable) {
        dragStart = null;
        pinDragStart = null;
        dragging.value = false;
        draggingPin.value = false;
        const distance = pointerDistance();

        if (distance) {
            pinchStart = { distance, zoom: zoom.value };
        }

        return;
    }

    if (!props.interactive) {
        return;
    }

    dragging.value = true;
    dragStart = {
        x: event.clientX,
        y: event.clientY,
        lat: center.value.lat,
        lng: center.value.lng,
    };
}

function onPinPointerDown(event: PointerEvent): void {
    if (!props.interactive) {
        return;
    }

    pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
    (event.currentTarget as HTMLElement).setPointerCapture(event.pointerId);
    draggingPin.value = true;
    dragging.value = false;
    dragStart = null;
    pinDragStart = {
        x: event.clientX,
        y: event.clientY,
        pinX: pin.value.x,
        pinY: pin.value.y,
    };
}

function onPointerMove(event: PointerEvent): void {
    if (!pointers.has(event.pointerId)) {
        return;
    }

    pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });

    if (props.zoomable && pointers.size >= 2 && pinchStart) {
        const distance = pointerDistance();

        if (!distance || pinchStart.distance === 0) {
            return;
        }

        const next = pinchStart.zoom + Math.round(Math.log2(distance / pinchStart.distance));
        zoom.value = Math.min(18, Math.max(10, next));

        return;
    }

    if (pinDragStart) {
        pin.value = {
            x: pinDragStart.pinX + (event.clientX - pinDragStart.x),
            y: pinDragStart.pinY + (event.clientY - pinDragStart.y),
        };

        return;
    }

    if (!dragStart) {
        return;
    }

    const start = project(dragStart.lat, dragStart.lng, zoom.value);
    const next = unproject(
        start.x - (event.clientX - dragStart.x) / TILE,
        start.y - (event.clientY - dragStart.y) / TILE,
        zoom.value,
    );

    center.value = next;
}

function onPointerUp(event: PointerEvent): void {
    const start = pointers.get(event.pointerId);
    pointers.delete(event.pointerId);
    pinchStart = null;

    if (pinDragStart) {
        const moved = Math.hypot(event.clientX - pinDragStart.x, event.clientY - pinDragStart.y) > 3;
        pinDragStart = null;
        draggingPin.value = false;

        if (moved) {
            emitPin();
        }

        return;
    }

    if (!dragStart) {
        dragging.value = false;

        return;
    }

    const movedMap = center.value.lat !== dragStart.lat || center.value.lng !== dragStart.lng;
    const tapped = start !== undefined && Math.hypot(event.clientX - start.x, event.clientY - start.y) < 6;
    dragStart = null;
    dragging.value = false;

    if (tapped && props.interactive) {
        placePin(event.clientX, event.clientY);

        return;
    }

    if (movedMap && props.interactive) {
        emitPin();
    }
}
</script>

<template>
    <div class="overflow-hidden rounded-2xl border bg-muted/30">
        <div
            v-if="interactive || hasPin"
            ref="mapEl"
            class="relative h-72 touch-none overflow-hidden bg-[#d7e6ef]"
            :class="interactive ? 'cursor-grab active:cursor-grabbing' : ''"
            @pointerdown="onPointerDown"
            @pointermove="onPointerMove"
            @pointerup="onPointerUp"
            @pointercancel="onPointerUp"
            @wheel.prevent="onWheel"
        >
            <img
                v-for="tile in tiles"
                :key="tile.key"
                :src="tile.url"
                alt=""
                draggable="false"
                class="pointer-events-none absolute h-64 w-64 max-w-none select-none"
                :style="{
                    left: `calc(50% + ${tile.left}px)`,
                    top: `calc(50% + ${tile.top}px)`,
                }"
            />

            <div
                class="absolute z-10 -translate-x-1/2 -translate-y-full"
                :class="interactive ? 'cursor-grab touch-none active:cursor-grabbing' : 'pointer-events-none'"
                :style="{
                    left: `calc(50% + ${pin.x}px)`,
                    top: `calc(50% + ${pin.y}px)`,
                }"
                @pointerdown.stop="onPinPointerDown"
                @pointermove.stop="onPointerMove"
                @pointerup.stop="onPointerUp"
                @pointercancel.stop="onPointerUp"
            >
                <MapPin class="h-10 w-10 fill-primary text-primary drop-shadow" />
            </div>

            <div v-if="zoomable" class="absolute right-2 top-2 z-10 flex flex-col gap-1">
                <button
                    type="button"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border bg-background/90 text-foreground shadow-xs"
                    aria-label="Zoom in"
                    @pointerdown.stop
                    @click.stop="changeZoom(1)"
                >
                    <Plus class="h-4 w-4" />
                </button>
                <button
                    type="button"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border bg-background/90 text-foreground shadow-xs"
                    aria-label="Zoom out"
                    @pointerdown.stop
                    @click.stop="changeZoom(-1)"
                >
                    <Minus class="h-4 w-4" />
                </button>
            </div>

            <a
                href="https://www.openstreetmap.org/copyright"
                target="_blank"
                rel="noreferrer"
                class="absolute bottom-1 right-1 z-10 rounded bg-background/80 px-1.5 py-0.5 text-[10px] text-muted-foreground"
                @pointerdown.stop
            >
                © OpenStreetMap
            </a>
        </div>

        <div v-else class="flex h-40 items-center justify-center px-4 text-center text-xs text-muted-foreground">
            This pickup station does not have a map pin yet.
        </div>

        <div v-if="label || hint || interactive" class="border-t px-3 py-2">
            <p v-if="label" class="text-sm font-semibold text-foreground">{{ label }}</p>
            <p v-if="hint" class="text-xs text-muted-foreground">
                {{ hint }}
            </p>
            <p v-else-if="interactive" class="text-xs text-muted-foreground">
                Zoom in, then drag the pin or tap the map.
            </p>
        </div>
    </div>
</template>
