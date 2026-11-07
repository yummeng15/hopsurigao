import { ref } from 'vue';

const SOUND_STORAGE_KEY = 'notification_sound_enabled';

// Shared state so toggle is consistent across all components
const isSoundEnabled = ref<boolean>(
    typeof window !== 'undefined'
        ? localStorage.getItem(SOUND_STORAGE_KEY) !== 'false'
        : true
);

function toggleSound(): boolean {
    isSoundEnabled.value = !isSoundEnabled.value;
    if (typeof window !== 'undefined') {
        localStorage.setItem(SOUND_STORAGE_KEY, String(isSoundEnabled.value));
    }
    return isSoundEnabled.value;
}

function setSoundEnabled(enabled: boolean): void {
    isSoundEnabled.value = enabled;
    if (typeof window !== 'undefined') {
        localStorage.setItem(SOUND_STORAGE_KEY, String(enabled));
    }
}

/**
 * Web Audio Context singleton.
 */
let audioCtx: AudioContext | null = null;

function getAudioContext(): AudioContext | null {
    if (typeof window === 'undefined') {
        return null;
    }
    try {
        if (!audioCtx) {
            const AudioContextClass = window.AudioContext || (window as unknown as { webkitAudioContext: typeof AudioContext }).webkitAudioContext;
            if (AudioContextClass) {
                audioCtx = new AudioContextClass();
            }
        }
        if (audioCtx && audioCtx.state === 'suspended') {
            audioCtx.resume().catch(() => {
                // Browser policy blocked autoplay until user gesture
            });
        }
        return audioCtx;
    } catch {
        return null;
    }
}

/**
 * Play a tone with an envelope.
 */
function playTone(freq: number, type: OscillatorType, startTime: number, duration: number, gainValue = 0.15): void {
    const ctx = getAudioContext();
    if (!ctx) return;

    try {
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();

        osc.type = type;
        osc.frequency.setValueAtTime(freq, startTime);

        gain.gain.setValueAtTime(0.001, startTime);
        gain.gain.exponentialRampToValueAtTime(gainValue, startTime + 0.03);
        gain.gain.exponentialRampToValueAtTime(0.001, startTime + duration);

        osc.connect(gain);
        gain.connect(ctx.destination);

        osc.start(startTime);
        osc.stop(startTime + duration + 0.05);
    } catch {
        // Ignore audio playback error
    }
}

/**
 * Standard pleasant notification chime (2 tones: A5 -> D6).
 */
export function playNotificationSound(): void {
    if (!isSoundEnabled.value) return;
    const ctx = getAudioContext();
    if (!ctx) return;

    const now = ctx.currentTime;
    playTone(880, 'sine', now, 0.12, 0.12);
    playTone(1174.66, 'sine', now + 0.09, 0.22, 0.14);
}

/**
 * Urgent warning alert chime (3 sharp ascending tones).
 */
export function playUrgentNotificationSound(): void {
    if (!isSoundEnabled.value) return;
    const ctx = getAudioContext();
    if (!ctx) return;

    const now = ctx.currentTime;
    playTone(587.33, 'triangle', now, 0.1, 0.18);
    playTone(783.99, 'triangle', now + 0.1, 0.1, 0.2);
    playTone(1046.50, 'triangle', now + 0.2, 0.3, 0.22);
}

/**
 * Valid / Success chime for QR scanner.
 */
export function playSuccessSound(): void {
    if (!isSoundEnabled.value) return;
    const ctx = getAudioContext();
    if (!ctx) return;

    const now = ctx.currentTime;
    playTone(523.25, 'sine', now, 0.08, 0.14);
    playTone(659.25, 'sine', now + 0.08, 0.08, 0.14);
    playTone(783.99, 'sine', now + 0.16, 0.2, 0.16);
}

/**
 * Invalid / Error buzzer for QR scanner.
 */
export function playErrorSound(): void {
    if (!isSoundEnabled.value) return;
    const ctx = getAudioContext();
    if (!ctx) return;

    const now = ctx.currentTime;
    playTone(369.99, 'sawtooth', now, 0.12, 0.12);
    playTone(293.66, 'sawtooth', now + 0.12, 0.22, 0.14);
}

export function useNotificationSound() {
    return {
        isSoundEnabled,
        toggleSound,
        setSoundEnabled,
        playNotificationSound,
        playUrgentNotificationSound,
        playSuccessSound,
        playErrorSound,
    };
}

