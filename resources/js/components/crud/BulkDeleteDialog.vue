<!-- components/crud/BulkDeleteDialog.vue -->
<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { ref } from 'vue';

const props = defineProps<{
    open: boolean;
    count: number;
    ids: number[];
    itemLabel?: string;
    action: string;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    'deleted': [];
}>();

const processing = ref(false);
const error = ref<string | null>(null);

const handleBulkDelete = () => {
    if (props.ids.length === 0) {
        error.value = 'No items selected for deletion.';
        return;
    }

    processing.value = true;
    error.value = null;

    router.delete(props.action, {
        data: { ids: props.ids },
        preserveScroll: true,
        onSuccess: () => {
            emit('update:open', false);
            emit('deleted');
        },
        onError: (errors) => {
            console.error('Bulk delete error:', errors);
            error.value = `An error occurred while deleting selected ${props.itemLabel || 'items'}s. Please try again.`;
        },
        onFinish: () => {
            processing.value = false;
        },
    });
};
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Delete Selected {{ itemLabel || 'Items' }}</DialogTitle>
                <DialogDescription>
                    Are you sure you want to delete {{ count }} selected {{ itemLabel || 'items' }}?
                    This action cannot be undone.
                </DialogDescription>
            </DialogHeader>

            <div v-if="error" class="text-sm text-red-600 bg-red-50 p-3 rounded-md">
                {{ error }}
            </div>

            <DialogFooter class="mt-6 gap-2">
                <Button type="button" variant="secondary" @click="emit('update:open', false)" :disabled="processing">
                    Cancel
                </Button>
                <Button type="button" variant="destructive" :disabled="processing" @click="handleBulkDelete">
                    <Spinner v-if="processing" class="mr-2" />
                    Delete {{ count }} {{ itemLabel || 'items' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>