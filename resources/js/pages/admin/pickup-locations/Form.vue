<!-- resources/js/pages/admin/pickup-locations/Form.vue -->
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Spinner } from '@/components/ui/spinner';
import PickupMap from '@/components/maps/PickupMap.vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export type PickupLocationValues = {
    id: number;
    name: string;
    address: string | null;
    latitude: number | string | null;
    longitude: number | string | null;
    description: string | null;
    status: string;
};

const props = defineProps<{
    pickupLocation?: PickupLocationValues;
    submitAction: { url: string; method: 'post' | 'put' };
    submitLabel: string;
    onCancel?: () => void;
}>();

const emit = defineEmits<{ success: [] }>();

const form = useForm({
    name: props.pickupLocation?.name ?? '',
    address: props.pickupLocation?.address ?? '',
    latitude: props.pickupLocation?.latitude ?? null as number | string | null,
    longitude: props.pickupLocation?.longitude ?? null as number | string | null,
    description: props.pickupLocation?.description ?? '',
    status: props.pickupLocation?.status ?? 'active',
});

function onPick(value: { latitude: number; longitude: number }): void {
    form.latitude = value.latitude;
    form.longitude = value.longitude;
}

function submit() {
    form.transform((data) => ({
        ...data,
        latitude: data.latitude === '' || data.latitude === null ? null : Number(data.latitude),
        longitude: data.longitude === '' || data.longitude === null ? null : Number(data.longitude),
    }));

    const options = { onSuccess: () => emit('success') };

    if (props.submitAction.method === 'put') {
        form.put(props.submitAction.url, options);
    } else {
        form.post(props.submitAction.url, options);
    }
}
</script>

<template>
    <form class="flex flex-col gap-6" @submit.prevent="submit">
        <div class="grid grid-cols-1 gap-6">
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input id="name" v-model="form.name" placeholder="e.g. SM City Cagayan de Oro" />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="address">Address</Label>
                <Input id="address" v-model="form.address" placeholder="e.g. Corrales Ave, Cagayan de Oro" />
                <InputError :message="form.errors.address" />
            </div>

            <div class="grid gap-2">
                <Label>Map pin</Label>
                <PickupMap
                    interactive
                    :latitude="form.latitude"
                    :longitude="form.longitude"
                    :label="form.name || 'Pickup station'"
                    @pick="onPick"
                />
                <InputError :message="form.errors.latitude || form.errors.longitude" />
            </div>

            <div class="grid gap-2">
                <Label for="description">Description</Label>
                <Textarea id="description" v-model="form.description" rows="3"
                    placeholder="Notes for drivers, landmarks, etc." />
                <InputError :message="form.errors.description" />
            </div>

            <div class="grid gap-2">
                <Label for="status">Status</Label>
                <Select v-model="form.status">
                    <SelectTrigger id="status" class="w-full">
                        <SelectValue placeholder="Select status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="active">Active</SelectItem>
                        <SelectItem value="inactive">Inactive</SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.status" />
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <Button v-if="onCancel" variant="outline" type="button" :disabled="form.processing" @click="onCancel">
                Cancel
            </Button>

            <Button type="submit" :disabled="form.processing">
                <Spinner v-if="form.processing" />
                {{ submitLabel }}
            </Button>
        </div>
    </form>
</template>