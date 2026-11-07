
<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { onBeforeUnmount, ref } from 'vue';
import jsQR from 'jsqr';
import {
    Camera,
    CameraOff,
    QrCode,
    Upload,
} from '@lucide/vue';

const form = useForm({
    qr_token: '',
});

const video = ref<HTMLVideoElement | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const cameraRunning = ref(false);
const cameraError = ref('');

let stream: MediaStream | null = null;
let frameId = 0;
let busy = false;

const canvas = typeof document !== 'undefined'
    ? document.createElement('canvas')
    : null;

function submitToken(value = form.qr_token): void {
    const token = value.trim();

    if (!token || busy || form.processing) {
        return;
    }

    busy = true;
    form.qr_token = token;

    stopCamera();

    form.post('/operator/qr-scanner/verify', {
        preserveScroll: true,
        onFinish: () => {
            busy = false;
        },
    });
}

function scanFrame(): void {
    if (
        !cameraRunning.value
        || !video.value
        || !canvas
    ) {
        return;
    }

    const element = video.value;

    if (
        element.readyState >= 2
        && element.videoWidth > 0
    ) {
        const width = element.videoWidth;
        const height = element.videoHeight;

        canvas.width = width;
        canvas.height = height;

        const context = canvas.getContext('2d', {
            willReadFrequently: true,
        });

        if (context) {
            context.drawImage(
                element,
                0,
                0,
                width,
                height
            );

            const pixels = context.getImageData(
                0,
                0,
                width,
                height
            );

            const result = jsQR(
                pixels.data,
                width,
                height
            );

            if (result?.data) {
                submitToken(result.data);
                return;
            }
        }
    }

    frameId = requestAnimationFrame(scanFrame);
}

async function startCamera(): Promise<void> {
    cameraError.value = '';
    stopCamera();

    try {
        if (!navigator.mediaDevices?.getUserMedia) {
            throw new Error(
                'Camera access requires a supported browser and HTTPS (or localhost).'
            );
        }

        stream = await navigator.mediaDevices.getUserMedia({
            video: {
                facingMode: 'environment',
            },
            audio: false,
        });

        cameraRunning.value = true;

        if (video.value) {
            video.value.srcObject = stream;

            await video.value.play();

            frameId = requestAnimationFrame(scanFrame);
        }
    } catch (error) {
        stopCamera();

        cameraError.value = error instanceof Error
            ? error.message
            : 'Could not open the camera.';
    }
}

function stopCamera(): void {
    cameraRunning.value = false;

    cancelAnimationFrame(frameId);

    stream?.getTracks().forEach(track => {
        track.stop();
    });

    stream = null;

    if (video.value) {
        video.value.srcObject = null;
    }
}

async function uploadImage(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    if (!file) return;

    cameraError.value = '';

    try {
        const bitmap = await createImageBitmap(file);
        const offscreen = document.createElement('canvas');

        offscreen.width = bitmap.width;
        offscreen.height = bitmap.height;

        const context = offscreen.getContext('2d');

        if (!context) {
            throw new Error(
                'Image decoding is not supported.'
            );
        }

        context.drawImage(bitmap, 0, 0);
        bitmap.close();

        const pixels = context.getImageData(
            0,
            0,
            offscreen.width,
            offscreen.height
        );

        const result = jsQR(
            pixels.data,
            offscreen.width,
            offscreen.height
        );

        if (!result?.data) {
            throw new Error(
                'No readable QR code was found in this image.'
            );
        }

        submitToken(result.data);
    } catch (error) {
        cameraError.value = error instanceof Error
            ? error.message
            : 'Unable to read the image.';
    } finally {
        input.value = '';
    }
}

onBeforeUnmount(stopCamera);
</script>

<template>
    <Head title="Operator QR Scanner" />

    <main class="mx-auto max-w-2xl space-y-5 px-4 py-6 text-foreground sm:px-6">
        <header>
            <div class="flex items-center gap-2 text-primary">
                <QrCode class="size-6" />
                Tour Operator Portal
            </div>

            <h1 class="mt-2 text-3xl font-bold text-foreground">
                Verify Booking QR
            </h1>

            <p class="mt-2 text-sm text-foreground/70">
                Scan a tourist's QR ticket.
                Only your business's bookings can be verified.
            </p>
        </header>

        <section class="space-y-4 rounded-3xl border border-border bg-card p-5 shadow-sm">
            <video
                ref="video"
                autoplay
                muted
                playsinline
                class="aspect-video w-full rounded-lg border border-border bg-background object-cover"
            />

            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground"
                    @click="cameraRunning ? stopCamera() : startCamera()"
                >
                    <CameraOff
                        v-if="cameraRunning"
                        class="size-4"
                    />

                    <Camera
                        v-else
                        class="size-4"
                    />

                    {{
                        cameraRunning
                            ? 'Stop camera'
                            : 'Start camera scan'
                    }}
                </button>

                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl border border-border bg-card px-4 py-2 text-sm font-semibold text-foreground hover:bg-muted"
                    @click="fileInput?.click()"
                >
                    <Upload class="size-4" />
                    Upload QR image
                </button>

                <input
                    ref="fileInput"
                    type="file"
                    accept="image/*"
                    class="hidden"
                    @change="uploadImage"
                />
            </div>

            <form
                class="space-y-3"
                @submit.prevent="submitToken()"
            >
                <label
                    for="qr-token"
                    class="block text-sm font-semibold text-foreground"
                >
                    Or enter the QR token manually
                </label>

                <input
                    id="qr-token"
                    v-model="form.qr_token"
                    maxlength="255"
                    required
                    autocomplete="off"
                    class="w-full rounded-lg border border-border bg-card p-3 font-mono text-sm text-foreground placeholder:text-foreground/50"
                    placeholder="Paste QR token"
                />

                <button
                    type="submit"
                    :disabled="form.processing || busy"
                    class="w-full rounded-xl bg-primary p-3 font-semibold text-primary-foreground disabled:opacity-50"
                >
                    {{
                        form.processing
                            ? 'Verifying…'
                            : 'Verify booking'
                    }}
                </button>
            </form>

            <p
                v-if="form.errors.qr_token || cameraError"
                role="alert"
                class="rounded-lg border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive"
            >
                {{ form.errors.qr_token || cameraError }}
            </p>
        </section>

        <p class="text-xs text-foreground/70">
            Camera scanning on mobile requires HTTPS or
            a secure browser context.

            Matching a QR does not mark check-in or
            collect payment. Confirm the booking status
            before boarding.
        </p>
    </main>
</template>
