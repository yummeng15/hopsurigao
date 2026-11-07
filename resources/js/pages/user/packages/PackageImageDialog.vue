<script setup lang="ts">
/*
|--------------------------------------------------------------------------
| Imports
|--------------------------------------------------------------------------
*/

import { ref, watch } from 'vue';

import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

import {
    Carousel,
    CarouselContent,
    CarouselItem,
    CarouselPrevious,
    CarouselNext,
} from '@/components/ui/carousel';

import { Skeleton } from '@/components/ui/skeleton';

import {
    ExternalLink,
    ImageOff,
    Images,
    Maximize2,
    Sparkles,
} from '@lucide/vue';

import { images } from '@/routes/user/packages';

/*
|--------------------------------------------------------------------------
| Types
|--------------------------------------------------------------------------
*/

type PackageImage = {
    name: string;
    path: string;
    url: string;
};

type PackageDetails = {
    id: number;
    package_name: string;
    images: PackageImage[];
};

/*
|--------------------------------------------------------------------------
| Props & Emits
|--------------------------------------------------------------------------
*/

const props = defineProps<{
    open: boolean;
    packageId: number | null;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

/*
|--------------------------------------------------------------------------
| State & Actions
|--------------------------------------------------------------------------
*/

const loading = ref(false);
const pkg = ref<PackageDetails | null>(null);

function openImageInNewTab(url: string) {
    window.open(url, '_blank', 'noopener,noreferrer');
}

async function fetchImages(id: number) {
    loading.value = true;
    pkg.value = null;

    try {
        const response = await fetch(images(id).url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (! response.ok) {
            throw new Error(`Failed to load images (${response.status})`);
        }

        const data = await response.json();

        pkg.value = {
            id: data.id,
            package_name: data.package_name,
            images: data.images ?? [],
        };
    } catch (error) {
        console.error('Failed to load package images:', error);
        pkg.value = null;
    } finally {
        loading.value = false;
    }
}

watch(
    () => [props.open, props.packageId] as const,
    ([isOpen, id]) => {
        if (isOpen && id) {
            fetchImages(id);
        }
    },
    { immediate: true }
);
</script>

<template>
    <Dialog :open="open" @update:open="(value) => emit('update:open', value)">
        <DialogContent
            class="max-h-[92vh] overflow-y-auto rounded-3xl border bg-card/95 p-6 backdrop-blur-md sm:max-w-4xl sm:p-8"
        >
            <!-- Ambient Glow for Dialog -->
            <div class="pointer-events-none absolute -right-16 -top-16 -z-10 h-48 w-48 rounded-full bg-primary/10 blur-3xl" />

            <!-- =====================================================
                 HEADER
            ====================================================== -->
            <DialogHeader class="pb-2">
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full border bg-primary/10 px-2.5 py-0.5 text-xs font-semibold text-primary">
                        <Images class="h-3.5 w-3.5" />
                        <span>Gallery Preview</span>
                    </span>
                </div>

                <DialogTitle class="mt-2 text-xl font-bold tracking-tight sm:text-2xl">
                    {{ pkg?.package_name ?? 'Package Gallery' }}
                </DialogTitle>
            </DialogHeader>

            <!-- =====================================================
                 LOADING STATE
            ====================================================== -->
            <div v-if="loading" class="mt-2 space-y-3">
                <Skeleton class="aspect-video w-full rounded-2xl bg-muted/60" />
                <div class="flex justify-between">
                    <Skeleton class="h-4 w-32 rounded-md" />
                    <Skeleton class="h-4 w-16 rounded-md" />
                </div>
            </div>

            <!-- =====================================================
                 CAROUSEL SHOWCASE
            ====================================================== -->
            <div v-else-if="pkg && pkg.images.length" class="mt-2 w-full">
                <Carousel class="w-full">
                    <CarouselContent>
                        <CarouselItem
                            v-for="(image, imageIndex) in pkg.images"
                            :key="image.path"
                        >
                            <div class="group relative aspect-video w-full overflow-hidden rounded-2xl border bg-muted/50 shadow-xs">
                                <img
                                    :src="image.url"
                                    :alt="`${pkg.package_name} preview ${imageIndex + 1}`"
                                    class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                                />

                                <!-- Hover Backdrop Overlay with Action -->
                                <div class="absolute inset-0 flex items-center justify-center bg-black/30 opacity-0 transition duration-200 group-hover:opacity-100">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-2 rounded-xl bg-background/90 px-4 py-2.5 text-xs font-semibold text-foreground backdrop-blur shadow-sm transition hover:scale-105 hover:bg-background"
                                        @click="openImageInNewTab(image.url)"
                                    >
                                        <ExternalLink class="h-3.5 w-3.5 text-primary" />
                                        <span>Open Full Image</span>
                                    </button>
                                </div>

                                <!-- Photo Indicator Pill -->
                                <span class="absolute bottom-3.5 right-3.5 rounded-full bg-black/60 px-3 py-1 text-xs font-medium text-white backdrop-blur">
                                    {{ imageIndex + 1 }} / {{ pkg.images.length }}
                                </span>
                            </div>
                        </CarouselItem>
                    </CarouselContent>

                    <!-- Carousel Controls -->
                    <template v-if="pkg.images.length > 1">
                        <CarouselPrevious
                            class="left-3 rounded-xl border-border/80 bg-background/80 text-foreground backdrop-blur transition hover:bg-background"
                        />
                        <CarouselNext
                            class="right-3 rounded-xl border-border/80 bg-background/80 text-foreground backdrop-blur transition hover:bg-background"
                        />
                    </template>
                </Carousel>
            </div>

            <!-- =====================================================
                 EMPTY STATE
            ====================================================== -->
            <div
                v-else
                class="mt-2 flex aspect-video flex-col items-center justify-center rounded-2xl border border-dashed bg-muted/20 p-8 text-center"
            >
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                    <ImageOff class="h-6 w-6" />
                </div>
                <h3 class="mt-3 text-sm font-semibold">No images available</h3>
                <p class="mt-1 text-xs text-muted-foreground">
                    There are no showcase photos attached to this package yet.
                </p>
            </div>
        </DialogContent>
    </Dialog>
</template>