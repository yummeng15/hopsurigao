<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { reactive, ref, watch } from 'vue';

import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index, update } from '@/routes/admin/boat-maintenance';

type Boat = {
    id: number;
    boat_name: string;
    route: string | null;
    status: string;
    under_maintenance: boolean;
};

const props = defineProps<{
    boats: Boat[];
}>();

const names = reactive<Record<number, string>>({});

watch(
    () => props.boats,
    (boats) => {
        for (const boat of boats) {
            if (names[boat.id] === undefined) {
                names[boat.id] = boat.boat_name;
            }
        }
    },
    { immediate: true },
);
const nameErrors = ref<Record<number, string>>({});
const savingId = ref<number | null>(null);

function save(boat: Boat, underMaintenance: boolean): void {
    if (savingId.value !== null) {
        return;
    }

    const boatName = (names[boat.id] ?? '').trim();
    names[boat.id] = boatName;

    if (boatName === '') {
        nameErrors.value[boat.id] = 'Enter the boat name.';

        return;
    }

    savingId.value = boat.id;
    nameErrors.value[boat.id] = '';

    router.put(
        update.url(boat.id),
        {
            boat_name: boatName,
            under_maintenance: underMaintenance,
        },
        {
            preserveScroll: true,
            onError: (errors) => {
                nameErrors.value[boat.id] = errors.boat_name ?? 'The boat could not be saved.';
            },
            onFinish: () => {
                savingId.value = null;
            },
        },
    );
}

defineOptions({
    layout: {
        breadcrumbs: [
            {
                href: index(),
                title: 'Boat maintenance',
            },
        ],
    },
});
</script>

<template>
    <Head title="Boat maintenance" />

    <div class="px-4 py-6">
        <Heading
            title="Boat maintenance"
            description="Each row is one boat. Rename it here, then mark it under maintenance when it cannot sail. New bookings for that boat stay closed until it is available again."
        />

        <div class="mt-6 space-y-3">
            <article
                v-for="boat in boats"
                :key="boat.id"
                class="flex flex-col gap-4 rounded-2xl border bg-card/60 p-5 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="min-w-0 flex-1">
                    <label class="text-xs font-medium text-muted-foreground" :for="`boat-name-${boat.id}`">
                        Boat name
                    </label>
                    <div class="mt-1 flex flex-col gap-2 sm:flex-row">
                        <Input
                            :id="`boat-name-${boat.id}`"
                            v-model="names[boat.id]"
                            class="max-w-sm"
                            placeholder="e.g. Daku Bangka"
                        />
                        <Button
                            variant="outline"
                            :disabled="savingId === boat.id || names[boat.id]?.trim() === boat.boat_name"
                            @click="save(boat, boat.under_maintenance)"
                        >
                            {{ savingId === boat.id ? 'Saving...' : 'Save name' }}
                        </Button>
                    </div>
                    <p v-if="nameErrors[boat.id]" class="mt-1 text-sm text-destructive">
                        {{ nameErrors[boat.id] }}
                    </p>
                    <p v-if="boat.route" class="mt-2 text-sm text-muted-foreground">
                        Route: {{ boat.route }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <Badge v-if="boat.status !== 'active'" variant="outline">Inactive</Badge>
                    <Badge :variant="boat.under_maintenance ? 'destructive' : 'default'">
                        {{ boat.under_maintenance ? 'Under maintenance' : 'Available' }}
                    </Badge>
                    <Button
                        :variant="boat.under_maintenance ? 'outline' : 'destructive'"
                        :disabled="savingId === boat.id || boat.status !== 'active'"
                        @click="save(boat, !boat.under_maintenance)"
                    >
                        {{
                            savingId === boat.id
                                ? 'Saving...'
                                : boat.under_maintenance
                                  ? 'Mark available'
                                  : 'Set under maintenance'
                        }}
                    </Button>
                </div>
            </article>
        </div>
    </div>
</template>
