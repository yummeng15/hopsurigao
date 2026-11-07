import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import type { FlashToast } from '@/types/ui';

export function initializeFlashToast(): void {
    router.on('flash', (event) => {
        const flash = (event as CustomEvent).detail?.flash;
        const data = flash?.toast as FlashToast | undefined;

        if (!data) return;

        toast[data.type](data.message, {
            description: data.description,
            duration: data.duration ?? 4000,
            class: 'my-custom-toast', // hook for your own CSS
        });
    });
}