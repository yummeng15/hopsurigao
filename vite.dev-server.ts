import { networkInterfaces } from 'node:os';

const virtualAdapter =
    /virtual|vmware|vbox|hyper-v|wsl|docker|loopback|bluetooth|npcap|tunnel|tailscale|zerotier|vethernet/i;

export const devServerPort = 5173;

/**
 * Local development origins only: localhost and private LAN addresses.
 * Public websites are rejected. This is not used by production builds.
 */
export function isLocalDevOrigin(origin: string | undefined): boolean {
    if (!origin) {
        return true;
    }

    let url: URL;

    try {
        url = new URL(origin);
    } catch {
        return false;
    }

    if (url.protocol !== 'http:' && url.protocol !== 'https:') {
        return false;
    }

    const host = url.hostname.replace(/^\[|\]$/g, '');

    if (host === 'localhost' || host === '127.0.0.1' || host === '::1') {
        return true;
    }

    return isPrivateIPv4(host);
}

export function isPrivateIPv4(host: string): boolean {
    const parts = host.split('.');

    if (parts.length !== 4) {
        return false;
    }

    const numbers = parts.map((part) => Number(part));

    if (numbers.some((number) => !Number.isInteger(number) || number < 0 || number > 255)) {
        return false;
    }

    const [first, second] = numbers;

    if (first === 10) {
        return true;
    }

    if (first === 192 && second === 168) {
        return true;
    }

    return first === 172 && second >= 16 && second <= 31;
}

/**
 * Current private IPv4 address, or localhost when the machine is not on a LAN.
 * VITE_DEV_HOST can name a specific adapter without committing that address.
 */
export function detectLanHost(): string {
    const override = process.env.VITE_DEV_HOST?.trim();

    if (override) {
        return override;
    }

    const matches: { name: string; address: string }[] = [];

    for (const [name, addresses] of Object.entries(networkInterfaces())) {
        if (virtualAdapter.test(name)) {
            continue;
        }

        for (const address of addresses ?? []) {
            const ipv4 = address.family === 'IPv4' || address.family === 4;

            if (!ipv4 || address.internal || !isPrivateIPv4(address.address)) {
                continue;
            }

            matches.push({ name, address: address.address });
        }
    }

    matches.sort((left, right) => adapterRank(left.name) - adapterRank(right.name));

    return matches[0]?.address ?? 'localhost';
}

function adapterRank(name: string): number {
    const normalized = name.toLowerCase();

    if (normalized.includes('wi-fi') || normalized.includes('wifi') || normalized.includes('wlan')) {
        return 0;
    }

    if (normalized.includes('ethernet') || normalized.startsWith('eth')) {
        return 1;
    }

    return 2;
}

export function developmentServer() {
    const host = detectLanHost();

    return {
        host: '0.0.0.0' as const,
        port: devServerPort,
        strictPort: true,
        origin: `http://${host}:${devServerPort}`,
        cors: {
            origin: [
                /^https?:\/\/localhost(?::\d+)?$/,
                /^https?:\/\/127\.0\.0\.1(?::\d+)?$/,
                /^https?:\/\/\[::1\](?::\d+)?$/,
                /^https?:\/\/10(?:\.\d{1,3}){3}(?::\d+)?$/,
                /^https?:\/\/192\.168(?:\.\d{1,3}){2}(?::\d+)?$/,
                /^https?:\/\/172\.(?:1[6-9]|2\d|3[0-1])(?:\.\d{1,3}){2}(?::\d+)?$/,
            ],
        },
        hmr: {
            host,
            clientPort: devServerPort,
        },
    };
}
