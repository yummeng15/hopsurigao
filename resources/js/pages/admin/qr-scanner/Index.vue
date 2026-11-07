<script setup lang="ts">
/*
|--------------------------------------------------------------------------
| Imports
|--------------------------------------------------------------------------
*/

import { Head, router } from '@inertiajs/vue3';
import { nextTick, onBeforeUnmount, ref } from 'vue';

import { Button } from '@/components/ui/button';
import jsQR from 'jsqr';

import {
    AlertCircle,
    Camera,
    CheckCircle2,
    ImageOff,
    LoaderCircle,
    QrCode,
    ScanLine,
    ShieldCheck,
    Sparkles,
    Upload,
    UploadCloud,
    XCircle,
} from '@lucide/vue';

import { qrscanner } from '@/routes/admin';
import { useNotificationSound } from '@/composables/useNotificationSound';

/*
|--------------------------------------------------------------------------
| Breadcrumbs
|--------------------------------------------------------------------------
*/

defineOptions({
    layout: {
        breadcrumbs: [
            {
                href: qrscanner(),
                title: 'QR Scanner',
            },
        ],
    },
});

/*
|--------------------------------------------------------------------------
| State
|--------------------------------------------------------------------------
*/

const qrInput = ref('');
const fileInput = ref<HTMLInputElement | null>(null);
const scanning = ref(false);
const verifying = ref(false);
const errorMessage = ref('');
const cameraError = ref('');
const videoElement = ref<HTMLVideoElement | null>(null);
const cameraStream = ref<MediaStream | null>(null);
const { playSuccessSound, playErrorSound } = useNotificationSound();

/*
 * Reused across camera frames so we're not
 * allocating a new canvas every animation frame.
 */
const scanCanvas = document.createElement('canvas');

/*
|--------------------------------------------------------------------------
| QR Verification
|--------------------------------------------------------------------------
*/

type VerifyResponse = {
    success: boolean;
    message: string;
    booking_id?: number;
    redirect_url?: string;
};

async function verifyQr(token = qrInput.value) {
    const cleanToken = token.trim();

    if (!cleanToken) {
        errorMessage.value = 'Please scan or enter a QR token.';
        return;
    }

    verifying.value = true;
    errorMessage.value = '';

    try {
        const response = await fetch('/admin/qrscanner/verify', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN':
                    document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute('content') ?? '',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                qr_token: cleanToken,
            }),
        });

        let data: VerifyResponse;

        try {
            data = (await response.json()) as VerifyResponse;
        } catch {
            throw new Error(
                `Server error (${response.status}). Please check the server logs.`
            );
        }

        if (!response.ok || !data.success) {
            throw new Error(data.message ?? 'Unable to verify this QR code.');
        }

        stopCamera();

        // Audio feedback on successful verification
        playSuccessSound();

        // Redirect directly to the dedicated verified show page
        if (data.redirect_url) {
            router.visit(data.redirect_url);
        }
    } catch (error) {
        // Audio feedback on failed/invalid verification
        playErrorSound();

        errorMessage.value =
            error instanceof Error
                ? error.message
                : 'Unable to verify QR code.';
    } finally {
        verifying.value = false;
    }
}

function verifyManualToken() {
    verifyQr();
}

/*
|--------------------------------------------------------------------------
| File Upload QR Decoder
|--------------------------------------------------------------------------
*/

function openFilePicker() {
    fileInput.value?.click();
}

async function handleFileUpload(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    if (!file) return;

    errorMessage.value = '';

    try {
        await decodeQrFromImage(file);
    } catch (error) {
        errorMessage.value =
            error instanceof Error
                ? error.message
                : 'Unable to read the QR image.';
    } finally {
        input.value = '';
    }
}

async function decodeQrFromImage(file: File) {
    const bitmap = await createImageBitmap(file);
    const canvas = document.createElement('canvas');

    canvas.width = bitmap.width;
    canvas.height = bitmap.height;

    const context = canvas.getContext('2d');

    if (!context) {
        bitmap.close();
        throw new Error('Unable to read the uploaded image.');
    }

    context.drawImage(bitmap, 0, 0);

    const imageData = context.getImageData(
        0,
        0,
        canvas.width,
        canvas.height
    );

    bitmap.close();

    const result = jsQR(imageData.data, imageData.width, imageData.height);

    if (!result?.data) {
        throw new Error('No QR code was detected in the uploaded image.');
    }

    const value = result.data.trim();

    if (!value) {
        throw new Error('The QR code does not contain a readable value.');
    }

    qrInput.value = value;
    await verifyQr(value);
}

/*
|--------------------------------------------------------------------------
| Camera Scanner
|--------------------------------------------------------------------------
*/

async function startCamera() {
    cameraError.value = '';

    if (!navigator.mediaDevices?.getUserMedia) {
        cameraError.value = 'Camera access is not supported by this browser.';
        return;
    }

    try {
        scanning.value = true;

        cameraStream.value = await navigator.mediaDevices.getUserMedia({
            video: {
                facingMode: {
                    ideal: 'environment',
                },
            },
            audio: false,
        });

        await nextTick();

        if (videoElement.value) {
            videoElement.value.srcObject = cameraStream.value;
            await videoElement.value.play();
            scanCameraFrame();
        }
    } catch (error) {
        scanning.value = false;
        cameraError.value =
            'Unable to access the camera. Please allow camera permissions or upload a QR image.';
    }
}

function scanCameraFrame() {
    if (!scanning.value || !videoElement.value) {
        return;
    }

    const video = videoElement.value;

    if (video.readyState === video.HAVE_ENOUGH_DATA) {
        scanCanvas.width = video.videoWidth;
        scanCanvas.height = video.videoHeight;

        const context = scanCanvas.getContext('2d');

        if (context) {
            context.drawImage(video, 0, 0, scanCanvas.width, scanCanvas.height);

            const imageData = context.getImageData(
                0,
                0,
                scanCanvas.width,
                scanCanvas.height
            );

            const result = jsQR(
                imageData.data,
                imageData.width,
                imageData.height,
                {
                    inversionAttempts: 'dontInvert',
                }
            );

            if (result?.data) {
                const value = result.data.trim();

                if (value) {
                    qrInput.value = value;
                    stopCamera();
                    verifyQr(value);
                    return;
                }
            }
        }
    }

    if (scanning.value) {
        window.requestAnimationFrame(scanCameraFrame);
    }
}

function stopCamera() {
    scanning.value = false;

    if (cameraStream.value) {
        cameraStream.value.getTracks().forEach((track) => track.stop());
        cameraStream.value = null;
    }

    if (videoElement.value) {
        videoElement.value.srcObject = null;
    }
}

/*
|--------------------------------------------------------------------------
| Lifecycle
|--------------------------------------------------------------------------
*/

onBeforeUnmount(() => {
    stopCamera();
});
</script>

<template>
    <Head title="QR Scanner" />

    <div class="relative min-h-screen overflow-x-clip bg-background pb-8 text-foreground md:pb-20">
        <!-- Ambient Background Glows -->
        <div class="pointer-events-none absolute inset-0 -z-10 overflow-hidden">
            <div class="absolute left-1/2 top-[-10rem] h-[28rem] w-[42rem] -translate-x-1/2 rounded-full bg-primary/10 blur-3xl" />
            <div class="absolute right-[-10rem] top-64 h-72 w-72 rounded-full bg-primary/5 blur-3xl" />
        </div>

        <div class="mx-auto max-w-5xl px-4 py-4 md:px-5 md:py-8 lg:px-8">
            <!-- Header Area — compact on phones; app bar already shows the page title -->
            <div class="mb-4 hidden md:mb-8 md:block">
                <div class="inline-flex items-center gap-2 rounded-full border bg-background/80 px-3 py-1 text-xs font-medium backdrop-blur shadow-xs">
                    <Sparkles class="h-3.5 w-3.5 text-primary" />
                    <span>Manifest & Gate Check-in</span>
                </div>

                <h1 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                    QR Verification Scanner
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Scan or upload guest vouchers to confirm booking attendance and check registration validity.
                </p>
            </div>

            <p class="mb-3 text-xs text-muted-foreground md:hidden">
                Scan, upload, or type a guest QR token to verify the booking.
            </p>

            <!-- ======================================================
                 MAIN SCANNER PANEL
            ======================================================= -->
            <section class="overflow-hidden rounded-3xl border bg-card/60 backdrop-blur-md shadow-sm">
                <!-- Card Header -->
                <div class="border-b border-border/60 bg-muted/20 p-4 sm:p-7">
                    <div class="flex items-start gap-3 sm:gap-4">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary sm:h-11 sm:w-11">
                            <ScanLine class="h-5 w-5" />
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-foreground sm:text-xl">
                                Verify Booking Token
                            </h2>
                            <p class="mt-0.5 text-xs text-muted-foreground sm:text-sm">
                                Use your live camera, drop a digital voucher image, or manually type the code below.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Card Body -->
                <div class="grid grid-cols-1 gap-5 p-4 sm:gap-8 sm:p-8 lg:grid-cols-12 lg:items-start">
                    <!-- Left: Camera Preview Stage -->
                    <div class="space-y-4 lg:col-span-6">
                        <div class="relative aspect-video w-full overflow-hidden rounded-2xl border border-border/80 bg-muted/40 shadow-inner flex items-center justify-center">
                            <!-- Live Video Stream -->
                            <video
                                ref="videoElement"
                                v-if="scanning"
                                autoplay
                                muted
                                playsinline
                                class="h-full w-full object-cover"
                            />

                            <!-- Idle Camera Placeholder -->
                            <div
                                v-else
                                class="flex h-full flex-col items-center justify-center p-6 text-center"
                            >
                                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-background/80 shadow-xs backdrop-blur text-primary">
                                    <Camera class="h-6 w-6" />
                                </div>
                                <p class="mt-3 font-bold text-sm text-foreground">
                                    Live Video Feed
                                </p>
                                <p class="mt-1 max-w-xs text-xs text-muted-foreground leading-relaxed">
                                    Camera scanner is currently standby. Click below to request lens access.
                                </p>
                            </div>

                            <!-- Scanning Target Outline Overlay -->
                            <div
                                v-if="scanning"
                                class="pointer-events-none absolute inset-0 flex items-center justify-center"
                            >
                                <div class="relative h-44 w-44 rounded-2xl border-2 border-primary/90 shadow-[0_0_0_9999px_rgba(0,0,0,0.4)] backdrop-blur-xs">
                                    <div class="absolute inset-x-2 top-0 h-0.5 bg-primary animate-pulse" />
                                </div>
                            </div>
                        </div>

                        <!-- Camera Error Alert -->
                        <div
                            v-if="cameraError"
                            class="flex items-start gap-2.5 rounded-2xl border border-destructive/30 bg-destructive/5 p-4 text-xs text-destructive"
                        >
                            <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" />
                            <span class="leading-relaxed">{{ cameraError }}</span>
                        </div>

                        <!-- Camera Controls -->
                        <div>
                            <Button
                                v-if="!scanning"
                                class="w-full gap-2 rounded-xl py-6 text-sm font-semibold shadow-xs"
                                @click="startCamera"
                            >
                                <Camera class="h-4 w-4" />
                                <span>Start Live Camera</span>
                            </Button>

                            <Button
                                v-else
                                variant="outline"
                                class="w-full gap-2 rounded-xl border-border/80 bg-background/80 py-6 text-sm font-semibold backdrop-blur hover:bg-muted"
                                @click="stopCamera"
                            >
                                <XCircle class="h-4 w-4" />
                                <span>Stop Camera</span>
                            </Button>
                        </div>
                    </div>

                    <!-- Right: File Upload & Manual Input -->
                    <div class="flex flex-col justify-between space-y-6 lg:col-span-6">
                        <!-- File Upload Box -->
                        <div>
                            <input
                                ref="fileInput"
                                type="file"
                                accept="image/*"
                                class="hidden"
                                @change="handleFileUpload"
                            />

                            <button
                                type="button"
                                class="group flex w-full flex-col items-center justify-center rounded-2xl border-2 border-dashed border-border/80 bg-background/60 px-6 py-8 text-center backdrop-blur transition-all hover:border-primary/60 hover:bg-muted/30 focus:outline-none"
                                @click="openFilePicker"
                            >
                                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/10 text-primary transition group-hover:scale-110">
                                    <UploadCloud class="h-6 w-6" />
                                </div>

                                <p class="mt-3 text-sm font-bold text-foreground">
                                    Upload QR Voucher
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Select a photo, screenshot, or downloaded pass file
                                </p>
                            </button>
                        </div>

                        <!-- Divider -->
                        <div class="relative py-1">
                            <div class="absolute inset-0 flex items-center">
                                <span class="w-full border-t border-border/60" />
                            </div>
                            <div class="relative flex justify-center">
                                <span class="rounded-full border bg-card px-3.5 py-0.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground shadow-2xs">
                                    Or verify string
                                </span>
                            </div>
                        </div>

                        <!-- Manual Token Input Form -->
                        <div class="space-y-2">
                            <label for="qr-token" class="block text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Manual Token String
                            </label>

                            <div class="flex gap-2">
                                <input
                                    id="qr-token"
                                    v-model="qrInput"
                                    type="text"
                                    placeholder="Paste booking QR token..."
                                    class="h-12 min-w-0 flex-1 rounded-xl border border-border/80 bg-background/80 px-4 text-sm text-foreground outline-none backdrop-blur transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                                    @keyup.enter="verifyManualToken"
                                />

                                <Button
                                    :disabled="verifying || !qrInput.trim()"
                                    class="h-12 gap-2 rounded-xl px-5 shadow-xs"
                                    @click="verifyManualToken"
                                >
                                    <LoaderCircle
                                        v-if="verifying"
                                        class="h-4 w-4 animate-spin"
                                    />
                                    <ScanLine
                                        v-else
                                        class="h-4 w-4"
                                    />
                                    <span>Verify</span>
                                </Button>
                            </div>
                        </div>

                        <!-- Error Message Alert -->
                        <div
                            v-if="errorMessage"
                            class="flex items-start gap-2.5 rounded-2xl border border-destructive/30 bg-destructive/5 p-4 text-xs text-destructive"
                        >
                            <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" />
                            <span class="leading-relaxed">{{ errorMessage }}</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Dispatch Operator Note -->
            <div class="mt-6 flex items-start gap-3.5 rounded-2xl border border-primary/20 bg-primary/5 p-4.5 text-xs backdrop-blur">
                <ShieldCheck class="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                <div class="leading-relaxed text-muted-foreground">
                    <span class="font-semibold text-foreground">Operator Guidance: </span>
                    Successful validation redirects to the customer's reservation manifest. Ensure network connectivity is stable when operating on-dock or in transit.
                </div>
            </div>
        </div>
    </div>
</template>