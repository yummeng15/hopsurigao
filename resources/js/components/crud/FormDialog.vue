<script setup lang="ts">
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';

const props = withDefaults(defineProps<{
    open: boolean;
    title: string;
    description?: string;
    contentClass?: string;
}>(), {
    contentClass: 'sm:max-w-md',
});

const emit = defineEmits<{ 'update:open': [value: boolean] }>();
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogContent :class="contentClass">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription v-if="description">{{ description }}</DialogDescription>
            </DialogHeader>

            <!-- Any entity's form goes here — Users/Roles/Permissions each
                 provide their own <Form> component via this slot. -->
            <slot :close="() => emit('update:open', false)" />
        </DialogContent>
    </Dialog>
</template>