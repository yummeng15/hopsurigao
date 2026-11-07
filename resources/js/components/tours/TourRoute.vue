<script setup lang="ts">
defineProps<{
    stops: string[];
    islandCount?: number;
}>();
</script>

<template>
    <div v-if="stops.length" class="rounded-2xl border bg-background/60 p-4">
        <p class="text-sm font-semibold text-foreground">
            <template v-if="(islandCount ?? 0) > 0">
                {{ islandCount }} {{ islandCount === 1 ? 'island' : 'islands' }} on this tour
            </template>
            <template v-else>
                {{ stops.length }} {{ stops.length === 1 ? 'place' : 'places' }} on this tour
            </template>
        </p>
        <p class="mt-1 text-xs text-muted-foreground">The boat follows this order.</p>

        <ol class="mt-3 space-y-2">
            <li v-for="(stop, index) in stops" :key="`${index}-${stop}`" class="flex items-center gap-3 text-sm">
                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary">
                    {{ index + 1 }}
                </span>
                <span>
                    <span class="block text-[11px] font-medium uppercase tracking-wider text-muted-foreground">
                        {{ index === 0 ? 'First stop' : 'Next' }}
                    </span>
                    <span class="font-medium text-foreground">{{ stop }}</span>
                </span>
            </li>
        </ol>
    </div>
</template>
