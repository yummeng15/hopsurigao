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
    title?: string;
    description: string;
    action: { url: string; method: 'delete' | 'post' | 'put' } | null;
}>();

const emit = defineEmits<{ 'update:open': [value: boolean] }>();

const processing = ref(false);

const handleDelete = () => {
    if (!props.action) return;
    
    processing.value = true;
    
    // Use router.delete for DELETE requests
    if (props.action.method === 'delete') {
        router.delete(props.action.url, {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
                emit('update:open', false);
            },
            onError: (errors) => {
                console.error('Delete error:', errors);
                processing.value = false;
            }
        });
    } else {
        // For other methods, use the Form component approach
        // This handles POST and PUT requests
        router[props.action.method](props.action.url, {}, {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
                emit('update:open', false);
            },
            onError: (errors) => {
                console.error('Error:', errors);
                processing.value = false;
            }
        });
    }
};
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogContent>
            <DialogHeader class="space-y-3">
                <DialogTitle>{{ title ?? 'Delete this item?' }}</DialogTitle>
                <DialogDescription>{{ description }}</DialogDescription>
            </DialogHeader>

            <DialogFooter class="mt-6 gap-2">
                <Button type="button" variant="secondary" @click="emit('update:open', false)" :disabled="processing">
                    Cancel
                </Button>
                <Button 
                    type="button" 
                    variant="destructive" 
                    :disabled="processing"
                    @click="handleDelete"
                >
                    <Spinner v-if="processing" />
                    Delete
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>